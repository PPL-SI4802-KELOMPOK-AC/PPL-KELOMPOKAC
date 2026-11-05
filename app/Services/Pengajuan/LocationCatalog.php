<?php

namespace App\Services\Pengajuan;

use App\Services\Map\Geometry;

class LocationCatalog
{
    private ?array $districts = null;
    private ?array $villages = null;

    public function districts(): array
    {
        if ($this->districts === null) {
            $data = json_decode(file_get_contents(public_path('data/map-boundaries/districts.geojson')), true, 512, JSON_THROW_ON_ERROR);
            $this->districts = [];
            foreach ($data['features'] as $feature) {
                $properties = $feature['properties'];
                $name = trim($properties['name']);
                $this->districts[$properties['code']] = preg_match('/^Kota\s/i', $name) ? $name : 'Kabupaten '.$name;
            }
            asort($this->districts, SORT_NATURAL | SORT_FLAG_CASE);
        }

        return $this->districts;
    }

    public function villages(string $district): array
    {
        if (! array_key_exists($district, $this->districts())) {
            return [];
        }
        if ($this->villages === null) {
            $data = json_decode(file_get_contents(public_path('data/map-boundaries/index.json')), true, 512, JSON_THROW_ON_ERROR);
            $this->villages = array_map(fn ($v) => array_map('trim', $v), $data['villages']);
        }
        $prefix = substr($district, 0, 2).'.'.substr($district, 2, 2).'.';
        $villages = array_values(array_filter($this->villages, fn ($v) => str_starts_with($v['code'], $prefix)));
        usort($villages, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return $villages;
    }

    public function selection(string $district, string $subdistrict, string $village): ?array
    {
        foreach ($this->villages($district) as $record) {
            if ($record['code'] === $village && $record['subdistrict'] === $subdistrict) {
                return $record;
            }
        }

        return null;
    }

    public function containsPoint(float $latitude, float $longitude): bool
    {
        $boundary = json_decode(file_get_contents(config('map.boundary_path')), true, 512, JSON_THROW_ON_ERROR);

        return Geometry::contains($boundary['geometry'], [$longitude, $latitude]);
    }

    public function address(array $input): string
    {
        $village = $this->selection($input['kabupaten'], $input['kecamatan'], $input['desa']);

        return implode(', ', [trim($input['alamat']), $village['name'], $village['subdistrict'],
            $this->districts()[$input['kabupaten']], 'Jawa Barat']);
    }

    // Skema lokasi menyimpan alamat utuh. Empat komponen terakhir dibentuk aplikasi,
    // sehingga koma dalam alamat kampung tetap dapat dipulihkan saat draft dibuka.
    public function addressFields(string $address): array
    {
        $parts = array_map('trim', explode(',', $address));
        if (count($parts) >= 5 && array_pop($parts) === 'Jawa Barat') {
            $districtName = array_pop($parts);
            $subdistrict = array_pop($parts);
            $villageName = array_pop($parts);
            $district = array_search($districtName, $this->districts(), true);
            if ($district !== false) {
                foreach ($this->villages((string) $district) as $village) {
                    if ($village['name'] === $villageName && $village['subdistrict'] === $subdistrict) {
                        return ['kabupaten' => (string) $district, 'kecamatan' => $subdistrict,
                            'desa' => $village['code'], 'alamat' => implode(', ', $parts)];
                    }
                }
            }
        }

        return ['kabupaten' => '', 'kecamatan' => '', 'desa' => '', 'alamat' => $address];
    }
}
