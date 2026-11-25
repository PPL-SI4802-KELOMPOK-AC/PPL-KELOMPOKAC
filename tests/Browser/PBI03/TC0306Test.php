<?php

namespace Tests\Browser\PBI03;

use Illuminate\Support\Facades\DB;
use Laravel\Dusk\Browser;
use Tests\Browser\MapDuskTestCase;

class PBI03TC0306Test extends MapDuskTestCase
{
    /** TC-03-06 | Negatif: latitude di luar batas tidak dipakai sebagai lokasi. */
    public function test_tc_03_06_koordinat_di_luar_batas_diabaikan(): void
    {
        $invalid = $this->addSampleRegion('3201', 7, [106.8, 200]);

        $this->browse(function (Browser $browser) use ($invalid) {
            $this->openMap($browser);
            $data = $this->mapData($browser);
            $this->assertSame(2, $data['summary']['total']);
            $this->assertSame(1, $data['summary']['located']);
            $this->assertSame(1, $data['summary']['unlocated']);
            $this->assertSame(1, $this->representedLocations($data['features']));
            $invalidRecord = collect($data['list']['data'])->firstWhere('id', $invalid['id']);
            $this->assertNotNull($invalidRecord);
            $this->assertNull($invalidRecord['location']);
            $this->assertStringContainsString('tidak lengkap, tidak valid, atau berada di luar batas', $invalidRecord['location_unavailable_reason']);
            $browser->assertSeeIn('[data-map-data-status]', '1 lokasi belum tersedia')
                ->assertCount('[data-map-rows] tr', 2)
                ->assertSeeIn('[data-map-rows] tr[data-id="'.$invalid['id'].'"]', 'Belum tersedia')
                ->assertAttribute('[data-map-panel]', 'data-state', 'ready');
        });
    }
}
