<?php

namespace App\Console\Commands;

use App\Services\Map\VillageLocationMatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class FillVillageLocations extends Command
{
    protected $signature = 'map:fill-village-locations {reference : JSON referensi geografis terverifikasi} {--apply : Isi hanya pasangan koordinat NULL}';

    protected $description = 'Cocokkan kode, kabupaten, kecamatan, dan desa; pratinjau tanpa perubahan secara default';

    public function handle(VillageLocationMatcher $matcher): int
    {
        try {
            $path = $this->argument('reference');
            $reference = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            if (empty($reference['references']) || empty($reference['source_url'])) throw new RuntimeException('Referensi tidak lengkap.');
            $boundary = json_decode(file_get_contents(config('map.boundary_path')), true, 512, JSON_THROW_ON_ERROR);
            $db = DB::connection();
            if ($db->getDriverName() !== 'mysql' || ! str_ends_with($db->getConfig('host'), '.tidbcloud.com')
                || $db->getDatabaseName() !== 'terangdesa') throw new RuntimeException('Perintah ini memerlukan koneksi TiDB terangdesa.');
            $db->beginTransaction();
            try {
                $query = $db->table('wilayah')->orderBy('id');
                if ($this->option('apply')) $query->lockForUpdate();
                $rows = $query->get()->map(fn ($r) => (array) $r)->all();
                $plan = $matcher->plan($rows, $reference['references'], $boundary['geometry']);
                $stamp = now()->format('Ymd-His').'-'.bin2hex(random_bytes(3));
                $audit = storage_path('app/map-reference/'.$stamp);
                File::ensureDirectoryExists($audit);
                // Persist rollback evidence before changing the online database.
                File::put($audit.'/before.json', json_encode($rows, JSON_THROW_ON_ERROR));
                File::put($audit.'/plan.json', json_encode([
                    'mode' => $this->option('apply') ? 'apply' : 'preview',
                    'reference_sha256' => hash_file('sha256', $path),
                    'source' => $reference['source'] ?? null, 'source_url' => $reference['source_url'],
                    'points_retrieved_at' => $reference['points_retrieved_at'] ?? null,
                    'administration_verified_at' => $reference['administration_verified_at'] ?? null,
                    ...$plan,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
                $affected = 0;
                if ($this->option('apply')) {
                    foreach (array_chunk($plan['updates'], 100) as $batch) {
                        $lat = $lng = $ids = [];
                        $cases = implode(' ', array_fill(0, count($batch), 'WHEN ? THEN ?'));
                        foreach ($batch as $item) {
                            array_push($lat, $item['id'], $item['latitude']);
                            array_push($lng, $item['id'], $item['longitude']);
                            $ids[] = $item['id'];
                        }
                        $placeholders = implode(',', array_fill(0, count($ids), '?'));
                        $affected += $db->update("UPDATE wilayah SET latitude = CASE id $cases END, longitude = CASE id $cases END WHERE id IN ($placeholders) AND latitude IS NULL AND longitude IS NULL", [...$lat, ...$lng, ...$ids]);
                    }
                    if ($affected !== count($plan['updates'])) throw new RuntimeException('Jumlah perubahan tidak sesuai rencana.');
                    $expected = array_column($plan['updates'], null, 'id');
                    $after = $db->table('wilayah')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
                    if (count($after) !== count($rows)) throw new RuntimeException('Jumlah wilayah berubah.');
                    foreach ($after as $i => $row) {
                        $before = $rows[$i];
                        if (isset($expected[$row['id']])) {
                            foreach (['latitude', 'longitude'] as $column) {
                                if (abs((float) $row[$column] - $expected[$row['id']][$column]) > 0.0000001) throw new RuntimeException('Koordinat tersimpan tidak sesuai.');
                                unset($row[$column], $before[$column]);
                            }
                        }
                        if ($row !== $before) throw new RuntimeException('Atribut di luar rencana berubah.');
                    }
                    $db->commit();
                } else {
                    $db->rollBack();
                }
                $this->line(json_encode(['mode' => $this->option('apply') ? 'apply' : 'preview',
                    'matched' => count($plan['updates']), 'updated' => $affected,
                    'skipped' => array_count_values(array_column($plan['skipped'], 'reason')),
                    'audit' => $audit], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                return self::SUCCESS;
            } catch (\Throwable $e) {
                if ($db->transactionLevel() > 0) $db->rollBack();
                throw $e;
            }
        } catch (\Throwable $e) {
            report($e);
            $this->error('Pengisian lokasi gagal; transaksi yang belum selesai dibatalkan. Periksa log aplikasi.');

            return self::FAILURE;
        }
    }
}
