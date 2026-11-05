<?php

namespace Tests\Unit;

use App\Services\Map\VillageLocationMatcher;
use PHPUnit\Framework\TestCase;

class VillageLocationMatcherTest extends TestCase
{
    private function plan(array $row = [], array $references = []): array
    {
        $base = ['id' => 1, 'kode_referensi' => '32.01.21.2001', 'nama_wilayah' => 'MALASARI',
            'alamat_deskriptif' => 'MALASARI, NANGGUNG, KABUPATEN BOGOR, JAWA BARAT',
            'latitude' => null, 'longitude' => null];
        $ref = ['code' => '32.01.21.2001', 'name' => 'Malasari', 'subdistrict' => 'Nanggung',
            'district' => 'Bogor', 'coordinates' => [106.5, -6.7]];
        $boundary = ['type' => 'Polygon', 'coordinates' => [[[106, -8], [109, -8], [109, -6], [106, -6], [106, -8]]]];

        return (new VillageLocationMatcher)->plan([[...$base, ...$row]], $references ?: [$ref], $boundary);
    }

    public function test_code_and_administrative_hierarchy_match_and_coordinate_order_is_correct(): void
    {
        $plan = $this->plan();
        $this->assertCount(1, $plan['updates']);
        $this->assertSame(-6.7, $plan['updates'][0]['latitude']);
        $this->assertSame(106.5, $plan['updates'][0]['longitude']);
        $this->assertSame([], $plan['skipped']);
    }

    public function test_same_village_name_in_different_district_or_subdistrict_is_rejected(): void
    {
        foreach (['MALASARI, LAIN, KABUPATEN BOGOR, JAWA BARAT', 'MALASARI, NANGGUNG, KOTA BOGOR, JAWA BARAT'] as $address) {
            $plan = $this->plan(['alamat_deskriptif' => $address]);
            $this->assertSame([], $plan['updates']);
            $this->assertSame('administration_mismatch', $plan['skipped'][0]['reason']);
        }
    }

    public function test_different_village_name_is_rejected_despite_matching_code(): void
    {
        $this->assertSame('administration_mismatch', $this->plan(['nama_wilayah' => 'DESA LAIN'])['skipped'][0]['reason']);
    }

    public function test_even_partial_existing_coordinates_are_preserved(): void
    {
        $plan = $this->plan(['latitude' => -6.6]);
        $this->assertSame([], $plan['updates']);
        $this->assertSame('existing_coordinate', $plan['skipped'][0]['reason']);
    }

    public function test_ambiguous_references_are_rejected(): void
    {
        $ref = ['code' => '32.01.21.2001'];
        $this->assertSame('missing_or_ambiguous_code', $this->plan([], [$ref, $ref])['skipped'][0]['reason']);
    }

    public function test_outside_point_is_rejected(): void
    {
        $ref = ['code' => '32.01.21.2001', 'name' => 'Malasari', 'subdistrict' => 'Nanggung',
            'district' => 'Kab. Bogor', 'coordinates' => [120, -6.7]];
        $this->assertSame('invalid_point', $this->plan([], [$ref])['skipped'][0]['reason']);
    }
}
