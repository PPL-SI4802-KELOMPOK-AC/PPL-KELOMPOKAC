<?php

namespace Tests\Browser\PBI03;

use Illuminate\Support\Facades\DB;
use Laravel\Dusk\Browser;
use Tests\Browser\MapDuskTestCase;

class PBI03TC0303Test extends MapDuskTestCase
{
    /** TC-03-03 | Normal: memilih Kabupaten Bogor membuka batas desa. */
    public function test_tc_03_03_memilih_kabupaten_membuka_batas_desa(): void
    {
        $this->browse(function (Browser $browser) {
            $this->openMap($browser);
            $this->clickArea($browser, '.district-area[data-area-code="3201"]');
            $browser->waitUntil('document.querySelector("[data-region-map]").dataset.areaLevel === "village"', 20)
                ->waitUntil('document.querySelector("[data-map-data-state]").dataset.mapDataState === "ready"', 20)
                ->assertAttribute('[data-region-map]', 'data-area-level', 'village')
                ->assertAttribute('[data-map-panel]', 'data-state', 'ready')
                ->assertSeeIn('[data-map-area-status]', 'batas desa/kelurahan')
                ->assertValue('#map-kabupaten', '3201');
            $this->assertGreaterThan(0, (int) $browser->attribute('[data-region-map]', 'data-area-count'));
        });
    }
}
