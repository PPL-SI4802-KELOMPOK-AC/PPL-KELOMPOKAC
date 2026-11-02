<?php

namespace App\Services\Map;

use RuntimeException;

class CsvDataset
{
    public function compile(string $path): array
    {
        if (! is_readable($path)) {
            throw new RuntimeException('CSV tidak ditemukan atau tidak dapat dibaca. Atur MAP_DATASET_CSV.');
        }
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle, 0, ';');
        if (! $header) {
            fclose($handle);
            throw new RuntimeException('Header CSV kosong.');
        }
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        $required = ['kode_referensi', 'kemendagri_kode_desa_kelurahan', 'bps_kode_desa_kelurahan', 'kode_provinsi',
            'bps_kode_kabupaten_kota', 'bps_nama_kabupaten_kota', 'jumlah_keluarga_sumber_belum_listrik',
            'jumlah_keluarga_sumber_pln', 'tahun', 'satuan', 'status_verifikasi_indikator', 'sumber_data'];
        if (array_diff($required, $header)) {
            fclose($handle);
            throw new RuntimeException('Kolom wajib CSV tidak lengkap.');
        }
        $records = [];
        $duplicates = [];
        $report = ['input_rows' => 0, 'period_rows' => 0, 'skipped_other_period_or_province' => 0, 'duplicate_codes' => 0, 'positive' => 0, 'zero' => 0, 'null' => 0];
        try {
            while (($values = fgetcsv($handle, 0, ';')) !== false) {
                if ($values === [null]) {
                    continue;
                }
                $report['input_rows']++;
                if (count($values) !== count($header)) {
                    throw new RuntimeException('Jumlah kolom tidak sesuai pada baris '.($report['input_rows'] + 1).'.');
                }
                $row = array_combine($header, $values);
                if ($row['tahun'] !== '2024' || $row['kode_provinsi'] !== '32') {
                    $report['skipped_other_period_or_province']++;
                    continue;
                }
                $report['period_rows']++;
                $code = trim($row['kemendagri_kode_desa_kelurahan']);
                if (! preg_match('/^32\.\d{2}\.\d{2}\.\d{4}$/', $code) || $code !== trim($row['kode_referensi']) || $row['satuan'] !== 'KELUARGA') {
                    throw new RuntimeException('Kode Kemendagri, kode referensi, atau satuan tidak valid pada baris '.($report['input_rows'] + 1).'.');
                }
                if (isset($records[$code])) {
                    $duplicates[$code] = true;
                }
                $number = function (string $field) use ($row): ?int {
                    $value = trim($row[$field]);
                    if ($value === '') {
                        return null;
                    }
                    if (! preg_match('/^\d+$/', $value) || (float) $value > 4294967295) {
                        throw new RuntimeException('Angka keluarga tidak valid: '.$field.'.');
                    }

                    return (int) $value;
                };
                $records[$code] = [
                    'kemendagri_code' => $code,
                    'bps_code' => $row['bps_kode_desa_kelurahan'],
                    'kabupaten_code' => $row['bps_kode_kabupaten_kota'],
                    'kabupaten_name' => $row['bps_nama_kabupaten_kota'],
                    'belum_listrik' => $number('jumlah_keluarga_sumber_belum_listrik'),
                    'pln' => $number('jumlah_keluarga_sumber_pln'),
                    'year' => 2024,
                    'unit' => $row['satuan'],
                    'source_status' => $row['status_verifikasi_indikator'],
                    'source' => $row['sumber_data'],
                ];
            }
        } finally {
            fclose($handle);
        }
        // Kode ganda tidak pernah dipilih sembarang atau dijumlahkan.
        foreach (array_keys($duplicates) as $code) {
            unset($records[$code]);
        }
        $report['duplicate_codes'] = count($duplicates);
        $report['unique_usable_codes'] = count($records);
        foreach ($records as $record) {
            $report[$record['belum_listrik'] === null ? 'null' : ($record['belum_listrik'] > 0 ? 'positive' : 'zero')]++;
        }

        return [
            'version' => 1, 'year' => 2024, 'source_file' => basename($path), 'source_sha256' => hash_file('sha256', $path),
            'processed_at' => now()->toIso8601String(), 'report' => $report, 'duplicate_codes' => array_keys($duplicates), 'records' => $records,
        ];
    }

    public static function writeJson(string $path, array $data): void
    {
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        $temp = $path.'.'.bin2hex(random_bytes(4)).'.tmp';
        if (file_put_contents($temp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)) === false) {
            throw new RuntimeException('Artefak data tidak dapat ditulis.');
        }
        if (! rename($temp, $path)) {
            @unlink($temp);
            throw new RuntimeException('Artefak data tidak dapat diganti.');
        }
    }
}
