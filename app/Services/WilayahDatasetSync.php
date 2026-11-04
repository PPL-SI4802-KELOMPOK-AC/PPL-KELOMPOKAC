<?php

namespace App\Services;

use App\Models\Wilayah;
use App\Services\Map\CsvDataset;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WilayahDatasetSync
{
    public const SOURCE = 'Dataset acuan proyek; tahun 2024; satuan keluarga';

    public const LEGACY_SOURCE = 'Data Desa Belum Teraliri Listrik.csv | Data Desa Sumber Listrik PLN.csv; periode 2024; indikator listrik belum diverifikasi';

    public function run(array $dataset, bool $apply, string $auditPath): array
    {
        if (($dataset['year'] ?? null) !== 2024 || empty($dataset['records']) || ($dataset['report']['duplicate_codes'] ?? 0) > 0) {
            throw new RuntimeException('Dataset 2024 kosong, tidak valid, atau mengandung kode ganda.');
        }

        return DB::transaction(function () use ($dataset, $apply, $auditPath) {
            $report = ['mode' => $apply ? 'apply' : 'preview', 'dataset_codes' => count($dataset['records']),
                'matched' => 0, 'missing_values' => 0, 'protected_rows' => 0, 'unchanged' => 0,
                'changed_rows' => 0, 'filled_counts' => 0, 'filled_conditions' => 0];
            $changes = [];
            $query = Wilayah::query()->whereIn('kode_referensi', array_keys($dataset['records']))->orderBy('id');
            if ($apply) $query->lockForUpdate();
            foreach ($query->get() as $region) {
                $report['matched']++;
                $csv = $dataset['records'][$region->kode_referensi];
                $families = $csv['belum_listrik'] ?? null;
                if ($families === null) { $report['missing_values']++; continue; }
                if (! is_int($families) || $families < 0 || $families > 4294967295
                    || ($csv['year'] ?? null) !== 2024 || ($csv['unit'] ?? null) !== 'KELUARGA'
                    || ($csv['kemendagri_code'] ?? null) !== $region->kode_referensi) {
                    throw new RuntimeException('Angka, satuan, periode, atau kode desa tidak valid.');
                }
                if ($region->jumlah_kk_terdampak !== null && $region->kondisi_listrik !== null) {
                    $report['unchanged']++; continue;
                }
                // Isian parsial/NULL yang sengaja dikosongkan setelah impor juga dilindungi.
                if ($region->jumlah_kk_terdampak !== null || $region->kondisi_listrik !== null
                    || $region->sumber_data === self::SOURCE
                    || $region->created_by !== null || $region->data_per_tanggal !== null
                    || ! in_array($region->sumber_data, [null, '', self::SOURCE, self::LEGACY_SOURCE], true)) {
                    $report['protected_rows']++; continue;
                }
                $after = [];
                if ($region->jumlah_kk_terdampak === null) $after['jumlah_kk_terdampak'] = $families;
                if ($region->kondisi_listrik === null) {
                    $after['kondisi_listrik'] = $families > 0 ? 'ada_kk_belum_berlistrik' : 'tidak_tercatat_kk_belum_berlistrik';
                }
                if (! $after) { $report['unchanged']++; continue; }
                $after['sumber_data'] = self::SOURCE;
                $before = array_intersect_key($region->getAttributes(), $after);
                $changes[] = ['id' => $region->id, 'code' => $region->kode_referensi, 'before' => $before, 'after' => $after];
                $report['changed_rows']++;
                $report['filled_counts'] += array_key_exists('jumlah_kk_terdampak', $after) ? 1 : 0;
                $report['filled_conditions'] += array_key_exists('kondisi_listrik', $after) ? 1 : 0;
                if ($apply) $region->fill($after)->save();
            }
            $report['unmatched_dataset_codes'] = $report['dataset_codes'] - $report['matched'];
            // Backup nilai lama disimpan sebelum commit; gagal menulis audit membatalkan transaksi.
            CsvDataset::writeJson($auditPath, ['created_at' => now()->toIso8601String(),
                'source_sha256' => $dataset['source_sha256'], 'report' => $report, 'changes' => $changes]);

            return $report;
        });
    }
}
