<?php

namespace Tests\Browser\PBI03;

use Illuminate\Support\Facades\DB;
use Laravel\Dusk\Browser;
use Tests\Browser\MapDuskTestCase;

class PBI03TC0305Test extends MapDuskTestCase
{
    /** TC-03-05 | Negatif: wilayah tanpa koordinat tetap ada di daftar dan ringkasan. */
    public function test_tc_03_05_wilayah_tanpa_koordinat_tetap_dicantumkan(): void
    {
        foreach (['3201', '3202', '3203'] as $district) {
            $this->addSampleRegion($district, 10);
        }
        $missing = $this->addSampleRegion('3204', 5, null, true);

        $this->browse(function (Browser $browser) use ($missing) {
            $this->openMap($browser);
            $data = $this->mapData($browser);
            $this->assertSame(5, $data['summary']['total']);
            $this->assertSame(4, $data['summary']['located']);
            $this->assertSame(1, $data['summary']['unlocated']);
            $this->assertSame(4, $this->representedLocations($data['features']));
            $missingRecord = collect($data['list']['data'])->firstWhere('id', $missing['id']);
            $this->assertNotNull($missingRecord);
            $this->assertNull($missingRecord['location']);
            $this->assertNotEmpty($missingRecord['location_unavailable_reason']);
            $browser->assertSeeIn('[data-map-data-status]', '1 lokasi belum tersedia')
                ->assertCount('[data-map-rows] tr', 5)
                ->assertSeeIn('[data-map-rows] tr[data-id="'.$missing['id'].'"]', 'Belum tersedia')
                ->assertAttribute('[data-map-panel]', 'data-state', 'ready');
        });
    }
}
