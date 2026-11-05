<?php

namespace App\Services\Map;

use App\Models\Wilayah;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class MapData
{
    private function boundary(): array
    {
        $path = config('map.boundary_path');
        if (! is_readable($path)) {
            throw new RuntimeException('Aset batas Jawa Barat belum tersedia.');
        }

        return Cache::remember('map-boundary:'.hash_file('sha256', $path), now()->addDay(), function () use ($path) {
            return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        });
    }

    public function response(array $filters): array
    {
        $boundary = $this->boundary();
        if (! isset($boundary['geometry']) || ! in_array($boundary['geometry']['type'], ['Polygon', 'MultiPolygon'], true)) {
            throw new RuntimeException('Geometri batas Jawa Barat tidak valid.');
        }
        $districts = [];
        $records = [];
        $periods = [];
        $excluded = 0;
        // Hanya atribut referensi publik. Identitas pembuat dan kredensial tidak diserialisasi.
        foreach (Wilayah::query()->select(['id', 'kode_referensi', 'nama_wilayah', 'alamat_deskriptif',
            'latitude', 'longitude', 'kondisi_listrik', 'jumlah_kk_terdampak', 'sumber_data', 'data_per_tanggal'])->orderBy('nama_wilayah')->orderBy('id')->cursor() as $region) {
            $administration = $this->administration($region);
            $point = null;
            $reason = 'Koordinat wilayah belum tersedia pada database.';
            if ($region->latitude !== null || $region->longitude !== null) {
                if ($region->latitude !== null && $region->longitude !== null
                    && is_numeric($region->latitude) && is_numeric($region->longitude)
                    && abs((float) $region->latitude) <= 90 && abs((float) $region->longitude) <= 180
                    && Geometry::contains($boundary['geometry'], [(float) $region->longitude, (float) $region->latitude])) {
                    $point = [(float) $region->longitude, (float) $region->latitude];
                } else {
                    // Jangan menutupi koordinat manual yang bermasalah dengan titik sumber lain.
                    $reason = 'Koordinat tersimpan tidak lengkap, tidak valid, atau berada di luar batas referensi Jawa Barat.';
                }
            }
            $validCode = (bool) preg_match('/^32\.\d{2}\.\d{2}\.\d{4}$/', (string) $region->kode_referensi);
            if (! $validCode && ! $point && $administration['province_name'] !== 'JAWA BARAT') {
                $excluded++;
                continue;
            }
            if ($administration['district_code'] && $administration['district_name']) {
                $districts[$administration['district_code']] = ['code' => $administration['district_code'], 'name' => $administration['district_name']];
            }
            $conditionRecorded = array_key_exists((string) $region->kondisi_listrik, config('wilayah.kondisi_listrik'));
            $families = $region->jumlah_kk_terdampak;
            $category = $families === null ? 'unavailable' : ($families > 0 ? 'has_unserved' : 'no_unserved');
            $year = $this->dataYear($region);
            $record = [
                'id' => $region->id, 'code' => $region->kode_referensi, 'name' => $region->nama_wilayah,
                'address' => $region->alamat_deskriptif,
                'government' => [
                    'condition' => $region->kondisi_listrik,
                    'condition_label' => config('wilayah.kondisi_listrik')[$region->kondisi_listrik] ?? ($region->kondisi_listrik ?: 'Belum diisi'),
                    'condition_recorded' => $conditionRecorded, 'affected_kk' => $region->jumlah_kk_terdampak,
                    'observation_date' => $region->data_per_tanggal, 'source' => $region->sumber_data,
                ],
                'administration' => $administration,
                'electricity' => [
                    'unserved_families' => $families, 'pln_families' => null,
                    'year' => $year, 'unit' => 'keluarga', 'source' => $region->sumber_data,
                ],
                'category' => $category,
                'location' => $point ? [
                    'coordinates' => $point, 'type' => 'village_reference', 'label' => 'Lokasi referensi desa',
                    'source' => 'database', 'source_label' => 'Koordinat referensi tersimpan pada database aplikasi',
                ] : null,
                'location_unavailable_reason' => $point ? null : $reason,
            ];
            if (($filters['q'] ?? '') !== '' && mb_stripos($region->nama_wilayah.' '.$region->alamat_deskriptif, $filters['q']) === false) {
                continue;
            }
            if (! empty($filters['kabupaten']) && $administration['district_code'] !== $filters['kabupaten']) {
                continue;
            }
            $condition = $filters['condition'] ?? 'all';
            if ($condition !== 'all' && $category !== $condition) {
                continue;
            }
            $location = $filters['location'] ?? 'all';
            if (($location === 'available' && ! $point) || ($location === 'missing' && $point)) {
                continue;
            }
            $records[] = $record;
            if ($year !== null) $periods[$year] = $year;
        }
        $located = array_values(array_filter($records, fn ($r) => $r['location'] !== null));
        uasort($districts, fn ($a, $b) => strcmp($a['name'], $b['name']));
        sort($periods);
        $total = count($records);
        $page = min(max(1, (int) ($filters['page'] ?? 1)), max(1, (int) ceil($total / 15)));
        $features = $this->cluster($located, (int) ($filters['zoom'] ?? 8), $filters['bbox'] ?? null);

        return [
            'summary' => [
                'total' => $total, 'located' => count($located), 'unlocated' => $total - count($located),
                'has_unserved' => count(array_filter($records, fn ($r) => $r['category'] === 'has_unserved')),
                'no_unserved' => count(array_filter($records, fn ($r) => $r['category'] === 'no_unserved')),
                'unavailable' => count(array_filter($records, fn ($r) => $r['category'] === 'unavailable')),
                'affected_kk' => count(array_filter($records, fn ($r) => $r['electricity']['unserved_families'] !== null))
                    ? array_sum(array_column(array_column($records, 'electricity'), 'unserved_families')) : null,
            ],
            'data' => ['source' => 'Database aplikasi', 'periods' => array_values($periods),
                'outside_or_unknown_province' => $excluded, 'unavailable_fields' => ['pln_families']],
            'kabupaten' => array_values($districts),
            'areas' => app(MapAreas::class)->response($records, $filters['kabupaten'] ?? null),
            'features' => $features,
            'list' => ['data' => array_slice($records, ($page - 1) * 15, 15), 'total' => $total, 'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / 15)), 'per_page' => 15],
        ];
    }

    private function administration(Wilayah $region): array
    {
        $result = ['district_code' => null, 'district_name' => null, 'subdistrict_name' => null, 'province_name' => null];
        if (preg_match('/^(32)\.(\d{2})\.\d{2}\.\d{4}$/', (string) $region->kode_referensi, $code)) {
            $result['district_code'] = $code[1].$code[2];
        }
        // Baca komponen alamat yang eksplisit. Format lain tetap ditampilkan sebagai alamat utuh.
        $parts = array_map('trim', explode(',', $region->alamat_deskriptif));
        $count = count($parts);
        if ($count >= 4 && mb_strtoupper($parts[$count - 1]) === 'JAWA BARAT'
            && preg_match('/^(KABUPATEN|KOTA)\s+\S/iu', $parts[$count - 2])) {
            $result['province_name'] = 'JAWA BARAT';
            $result['district_name'] = $parts[$count - 2];
            $result['subdistrict_name'] = $parts[$count - 3] ?: null;
        }

        return $result;
    }

    private function dataYear(Wilayah $region): ?int
    {
        if ($region->data_per_tanggal && preg_match('/^((?:19|20)\d{2})-\d{2}-\d{2}$/', $region->data_per_tanggal, $date)) {
            return (int) $date[1];
        }
        if (preg_match('/(?:periode|tahun)\s*[:=]?\s*((?:19|20)\d{2})\b/iu', $region->sumber_data ?? '', $source)) {
            return (int) $source[1];
        }

        return null;
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
