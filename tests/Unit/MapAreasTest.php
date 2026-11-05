<?php

namespace Tests\Unit;

use App\Services\Map\MapAreas;
use PHPUnit\Framework\TestCase;

class MapAreasTest extends TestCase
{
    private function record(string $code, ?int $families): array
    {
        return ['id' => $code, 'code' => $code, 'name' => 'MALASARI',
            'address' => 'MALASARI, NANGGUNG, KABUPATEN BOGOR, JAWA BARAT',
            'administration' => ['district_code' => '3201', 'district_name' => 'KABUPATEN BOGOR'],
            'electricity' => ['unserved_families' => $families]];
    }

    public function test_aggregation_keeps_zero_and_unknown_distinct_and_uses_village_denominator(): void
    {
        $records = [$this->record('32.01.21.2001', 7), $this->record('32.01.21.2002', 0), $this->record('32.01.21.2003', null)];
        $data = (new MapAreas)->response($records, null, []);
        $district = ((array) $data['districts'])['3201'];
        $this->assertSame(3, $district['total']);
        $this->assertSame(2, $district['known']);
        $this->assertSame(1, $district['has_unserved']);
        $this->assertSame(1, $district['unavailable']);
        $this->assertSame(7, $district['families']);
        $this->assertSame(50.0, $district['share']);
        $this->assertSame([], (array) $data['villages']);
    }

    public function test_polygon_match_needs_code_and_administration_but_does_not_need_a_point(): void
    {
        $record = $this->record('32.01.21.2001', 0);
        $index = [$record['code'] => ['name' => 'Malasari', 'subdistrict' => 'Nanggung', 'district' => 'Bogor']];
        $data = (new MapAreas)->response([$record], '3201', $index);
        $this->assertSame($record, ((array) $data['villages'])[$record['code']]);
        $record['address'] = 'MALASARI, LAIN, KABUPATEN BOGOR, JAWA BARAT';
        $this->assertSame([], (array) (new MapAreas)->response([$record], '3201', $index)['villages']);
    }

    public function test_all_unknown_has_null_sum_and_percentage(): void
    {
        $district = ((array) (new MapAreas)->response([$this->record('32.01.21.2001', null)], null, [])['districts'])['3201'];
        $this->assertNull($district['share']);
        $this->assertNull($district['families']);
    }

    public function test_duplicate_codes_are_not_assigned_a_polygon(): void
    {
        $record = $this->record('32.01.21.2001', 7);
        $index = [$record['code'] => ['name' => 'Malasari', 'subdistrict' => 'Nanggung', 'district' => 'Bogor']];
        $this->assertSame([], (array) (new MapAreas)->response([$record, $record], '3201', $index)['villages']);
    }
}
