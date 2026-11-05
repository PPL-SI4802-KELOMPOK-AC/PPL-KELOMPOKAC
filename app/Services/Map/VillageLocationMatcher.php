<?php

namespace App\Services\Map;

class VillageLocationMatcher
{
    private function name(string $value): string
    {
        return preg_replace('/[^\pL\pN]/u', '', mb_strtoupper(trim($value)));
    }

    private function district(string $value): string
    {
        $value = mb_strtoupper(trim($value));
        if (! preg_match('/^KOTA\s+/u', $value)) {
            $value = 'KABUPATEN '.preg_replace('/^KAB(?:UPATEN)?\.?\s+/u', '', $value);
        }

        return $this->name($value);
    }

    public function matchesAdministration(array $row, array $reference): bool
    {
        $address = array_map('trim', explode(',', $row['alamat_deskriptif']));
        $count = count($address);

        return $count >= 4 && $this->name($address[$count - 1]) === 'JAWABARAT'
            && $this->name($row['nama_wilayah']) === $this->name($reference['name'] ?? '')
            && $this->name($address[$count - 3]) === $this->name($reference['subdistrict'] ?? '')
            && $this->district($address[$count - 2]) === $this->district($reference['district'] ?? '');
    }

    public function plan(iterable $regions, array $references, array $boundary): array
    {
        $index = [];
        foreach ($references as $reference) {
            $index[$reference['code'] ?? ''][] = $reference;
        }
        $rows = is_array($regions) ? $regions : iterator_to_array($regions);
        $codeCounts = array_count_values(array_map(fn ($r) => (string) $r['kode_referensi'], $rows));
        $updates = [];
        $skipped = [];
        foreach ($rows as $row) {
            $code = (string) $row['kode_referensi'];
            $reason = null;
            $candidates = $index[$code] ?? [];
            if ($row['latitude'] !== null || $row['longitude'] !== null) {
                $reason = 'existing_coordinate';
            } elseif (! preg_match('/^32\.\d{2}\.\d{2}\.\d{4}$/', $code) || count($candidates) !== 1 || $codeCounts[$code] !== 1) {
                $reason = 'missing_or_ambiguous_code';
            } else {
                $reference = $candidates[0];
                $point = $reference['coordinates'] ?? [];
                if (! $this->matchesAdministration($row, $reference)) {
                    $reason = 'administration_mismatch';
                } elseif (count($point) !== 2 || ! is_numeric($point[0]) || ! is_numeric($point[1])
                    || abs((float) $point[0]) > 180 || abs((float) $point[1]) > 90
                    || ! Geometry::contains($boundary, $point)) {
                    $reason = 'invalid_point';
                } else {
                    $updates[] = ['id' => $row['id'], 'code' => $code, 'name' => $row['nama_wilayah'],
                        'district' => $reference['district'], 'subdistrict' => $reference['subdistrict'],
                        'latitude' => round((float) $point[1], 7), 'longitude' => round((float) $point[0], 7)];
                }
            }
            if ($reason) $skipped[] = ['id' => $row['id'], 'code' => $code, 'reason' => $reason];
        }

        return ['updates' => $updates, 'skipped' => $skipped];
    }
}
