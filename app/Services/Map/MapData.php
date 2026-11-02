<?php

namespace App\Services\Map;

use App\Models\Wilayah;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class MapData
{
    public function artifact(string $path): array
    {
        if (! is_readable($path)) {
            throw new RuntimeException('Data pendukung peta belum disiapkan. Pengelola perlu menjalankan map:prepare dan map:geography.');
        }

        return Cache::remember('map-artifact:'.hash_file('sha256', $path), now()->addDay(), function () use ($path) {
            return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        });
    }

    public function response(array $filters): array
    {
        $dataset = $this->artifact(config('map.dataset_path'));
        $geography = $this->artifact(config('map.locations_path'));
        $boundary = $this->artifact(config('map.boundary_path'));
        if (($dataset['year'] ?? null) !== 2024 || ! isset($dataset['records'], $geography['points'], $boundary['geometry'])
            || ($geography['boundary_id'] ?? null) !== ($boundary['properties']['boundary_id'] ?? null)) {
            throw new RuntimeException('Versi artefak peta tidak cocok; jalankan ulang persiapan dataset/geografi.');
        }
        $districts = [];
        foreach ($dataset['records'] as $row) {
            $districts[$row['kabupaten_code']] = ['code' => $row['kabupaten_code'], 'name' => $row['kabupaten_name']];
        }
        uasort($districts, fn ($a, $b) => strcmp($a['name'], $b['name']));
        $records = [];
        $matched = 0;
        $excluded = 0;
        // Hanya atribut referensi publik. Identitas pembuat dan kredensial tidak diserialisasi.
        foreach (Wilayah::query()->select(['id', 'kode_referensi', 'nama_wilayah', 'alamat_deskriptif',
            'latitude', 'longitude', 'kondisi_listrik', 'jumlah_kk_terdampak', 'sumber_data', 'data_per_tanggal'])->orderBy('nama_wilayah')->orderBy('id')->cursor() as $region) {
            $csv = $dataset['records'][$region->kode_referensi] ?? null;
            $reference = $geography['points'][$region->kode_referensi] ?? null;
            $hasDbCoordinates = $region->latitude !== null || $region->longitude !== null;
            $point = null;
            $locationSource = null;
            $reason = 'Koordinat dan titik referensi berdasarkan kode belum tersedia.';
            if ($hasDbCoordinates) {
                if ($region->latitude !== null && $region->longitude !== null
                    && is_numeric($region->latitude) && is_numeric($region->longitude)
                    && abs((float) $region->latitude) <= 90 && abs((float) $region->longitude) <= 180
                    && Geometry::contains($boundary['geometry'], [(float) $region->longitude, (float) $region->latitude])) {
                    $point = [(float) $region->longitude, (float) $region->latitude];
                    $locationSource = 'database';
                } else {
                    // Jangan menutupi koordinat manual yang bermasalah dengan titik sumber lain.
                    $reason = 'Koordinat tersimpan tidak lengkap, tidak valid, atau berada di luar batas referensi Jawa Barat.';
                }
            } elseif ($reference) {
                $point = $reference['coordinates'];
                $locationSource = 'big_polygon_interior';
            }
            $validCode = (bool) preg_match('/^32\.\d{2}\.\d{2}\.\d{4}$/', (string) $region->kode_referensi);
            if (! $csv && ! $validCode && ! $point) {
                $excluded++;
                continue;
            }
            $matched += $csv ? 1 : 0;
            $verified = array_key_exists((string) $region->kondisi_listrik, config('wilayah.kondisi_listrik'));
            $positive = ($csv['belum_listrik'] ?? 0) > 0;
            $record = [
                'id' => $region->id, 'code' => $region->kode_referensi, 'name' => $region->nama_wilayah,
                'address' => $region->alamat_deskriptif,
                'government' => [
                    'condition' => $region->kondisi_listrik,
                    'condition_label' => config('wilayah.kondisi_listrik')[$region->kondisi_listrik] ?? ($region->kondisi_listrik ?: 'Belum diverifikasi'),
                    'verified' => $verified, 'affected_kk' => $region->jumlah_kk_terdampak,
                    'observation_date' => $region->data_per_tanggal, 'source' => $region->sumber_data,
                ],
                'csv' => $csv,
                'category' => $verified ? 'verified' : ($positive ? 'csv_positive' : 'unknown'),
                'location' => $point ? [
                    'coordinates' => $point, 'type' => 'village_reference', 'label' => 'Lokasi referensi desa',
                    'source' => $locationSource,
                    'source_label' => $locationSource === 'database' ? 'Koordinat referensi tersimpan di database' : $geography['source'],
                ] : null,
                'location_unavailable_reason' => $point ? null : $reason,
            ];
            if (($filters['q'] ?? '') !== '' && mb_stripos($region->nama_wilayah.' '.$region->alamat_deskriptif, $filters['q']) === false) {
                continue;
            }
            if (! empty($filters['kabupaten']) && ($csv['kabupaten_code'] ?? '') !== $filters['kabupaten']) {
                continue;
            }
            $layer = $filters['layer'] ?? 'all';
            if (($layer === 'verified' && ! $verified) || ($layer === 'csv_positive' && ! $positive) || ($layer === 'unknown' && ($verified || $positive))) {
                continue;
            }
            $location = $filters['location'] ?? 'all';
            if (($location === 'available' && ! $point) || ($location === 'missing' && $point)) {
                continue;
            }
            $records[] = $record;
        }
        $located = array_values(array_filter($records, fn ($r) => $r['location'] !== null));
        $total = count($records);
        $page = min(max(1, (int) ($filters['page'] ?? 1)), max(1, (int) ceil($total / 15)));
        $features = $this->cluster($located, (int) ($filters['zoom'] ?? 8), $filters['bbox'] ?? null);

        return [
            'summary' => [
                'total' => $total, 'located' => count($located), 'unlocated' => $total - count($located),
                'verified' => count(array_filter($records, fn ($r) => $r['government']['verified'])),
                'csv_positive' => count(array_filter($records, fn ($r) => ($r['csv']['belum_listrik'] ?? 0) > 0)),
            ],
            'matching' => [
                ...$dataset['report'], 'database_matched' => $matched, 'csv_unmatched' => count($dataset['records']) - $matched,
                'database_outside_or_unknown_province' => $excluded,
            ],
            'dataset' => [
                'year' => 2024, 'file' => $dataset['source_file'], 'sha256' => $dataset['source_sha256'],
                'verification' => 'Angka dari CSV — belum diverifikasi', 'processed_at' => $dataset['processed_at'],
                'zero_note' => 'Nilai nol tidak membuktikan seluruh desa sudah berlistrik.',
            ],
            'geography' => ['source' => $geography['source'], 'source_url' => $geography['source_url'], 'retrieved_at' => $geography['retrieved_at']],
            'kabupaten' => array_values($districts),
            'features' => $features,
            'list' => ['data' => array_slice($records, ($page - 1) * 15, 15), 'total' => $total, 'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / 15)), 'per_page' => 15],
        ];
    }

    private function cluster(array $records, int $zoom, ?array $bbox): array
    {
        $cells = [];
        $scale = 256 * 2 ** $zoom;
        foreach ($records as $record) {
            [$lng, $lat] = $record['location']['coordinates'];
            if ($bbox && ($lng < $bbox[0] || $lat < $bbox[1] || $lng > $bbox[2] || $lat > $bbox[3])) {
                continue;
            }
            $x = ($lng + 180) / 360 * $scale;
            $sin = sin(deg2rad($lat));
            $y = (.5 - log((1 + $sin) / (1 - $sin)) / (4 * M_PI)) * $scale;
            $key = floor($x / 54).':'.floor($y / 54);
            $cells[$key][] = $record;
        }
        $features = [];
        foreach ($cells as $cell) {
            if (count($cell) === 1) {
                $features[] = ['type' => 'village', 'record' => $cell[0]];
                continue;
            }
            $coordinates = array_column(array_column($cell, 'location'), 'coordinates');
            $lngs = array_column($coordinates, 0);
            $lats = array_column($coordinates, 1);
            $counts = array_count_values(array_column($cell, 'category'));
            $center = [array_sum($lngs) / count($lngs), array_sum($lats) / count($lats)];
            usort($coordinates, fn ($a, $b) => (($a[0] - $center[0]) ** 2 + ($a[1] - $center[1]) ** 2) <=> (($b[0] - $center[0]) ** 2 + ($b[1] - $center[1]) ** 2));
            // Anggota terdekat pusat kelompok tetap berada di desa/provinsi, berbeda dari rata-rata yang bisa di laut.
            $features[] = ['type' => 'cluster', 'count' => count($cell), 'coordinates' => $coordinates[0],
                'bounds' => [[min($lats), min($lngs)], [max($lats), max($lngs)]], 'categories' => $counts];
        }

        return $features;
    }
}
