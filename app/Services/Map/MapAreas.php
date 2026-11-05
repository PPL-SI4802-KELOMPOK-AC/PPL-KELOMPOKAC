<?php

namespace App\Services\Map;

use Illuminate\Support\Facades\Cache;

class MapAreas
{
    public function response(array $records, ?string $selectedDistrict, ?array $index = null): array
    {
        if ($index === null) {
            $path = public_path('data/map-boundaries/index.json');
            $index = is_readable($path) ? Cache::remember('map-area-index:'.hash_file('sha256', $path), now()->addDay(),
                fn () => json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR)['villages']) : [];
        }
        $districts = $villages = [];
        $codeCounts = array_count_values(array_map(fn ($r) => (string) $r['code'], $records));
        $matcher = new VillageLocationMatcher;
        foreach ($records as $record) {
            $code = $record['administration']['district_code'];
            if (! $code) continue;
            $districts[$code] ??= ['code' => $code, 'name' => $record['administration']['district_name'],
                'total' => 0, 'known' => 0, 'has_unserved' => 0, 'unavailable' => 0, 'families' => null];
            $districts[$code]['total']++;
            $value = $record['electricity']['unserved_families'];
            if ($value === null) {
                $districts[$code]['unavailable']++;
            } else {
                $districts[$code]['known']++;
                $districts[$code]['families'] = ($districts[$code]['families'] ?? 0) + $value;
                if ($value > 0) $districts[$code]['has_unserved']++;
            }
            $reference = $index[$record['code']] ?? null;
            if ($selectedDistrict && $reference && $codeCounts[$record['code']] === 1
                && $matcher->matchesAdministration(['nama_wilayah' => $record['name'], 'alamat_deskriptif' => $record['address']], $reference)) {
                $villages[$record['code']] = $record;
            }
        }
        foreach ($districts as &$district) {
            $district['share'] = $district['known'] ? round($district['has_unserved'] / $district['known'] * 100, 1) : null;
        }
        unset($district);

        return ['level' => $selectedDistrict ? 'village' : 'district', 'district' => $selectedDistrict,
            'districts' => (object) $districts, 'villages' => (object) $villages];
    }
}
