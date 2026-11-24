<?php

namespace Tests\Browser\PBI03;

use Illuminate\Support\Facades\DB;
use Laravel\Dusk\Browser;
use Tests\Browser\MapDuskTestCase;

class PBI03TC0304Test extends MapDuskTestCase
{
    /** TC-03-04 | Normal: pilihan dan highlight berpindah dari Desa A ke Desa B. */
    public function test_tc_03_04_memilih_desa_lain_memindahkan_highlight(): void
    {
        // Banjar Wangi dan Banjar Waru berada di Kecamatan Ciawi, Kabupaten Bogor.
        $first = $this->addSampleRegion('3201', 10, null, false, 1);
        $second = $this->addSampleRegion('3201', 0, null, false, 2);

        $this->browse(function (Browser $browser) use ($first, $second) {
            $this->openMap($browser, '?kabupaten=3201');
            $browser->waitUntil('document.querySelector("[data-region-map]").dataset.areaLevel === "village"', 20);

            $this->clickArea($browser, '.village-area[data-area-code="'.$first['code'].'"]');
            $browser->waitForTextIn('#map-detail-title', $first['name'], 15)
                ->assertPresent('[data-map-rows] tr.is-selected[data-id="'.$first['id'].'"]')
                ->assertPresent('.selected-area');

            $this->clickArea($browser, '.village-area[data-area-code="'.$second['code'].'"]');
            $browser->waitForTextIn('#map-detail-title', $second['name'], 15)
                ->assertMissing('[data-map-rows] tr.is-selected[data-id="'.$first['id'].'"]')
                ->assertPresent('[data-map-rows] tr.is-selected[data-id="'.$second['id'].'"]')
                ->assertPresent('.selected-area')
                ->assertAttribute('.village-area[data-area-code="'.$first['code'].'"]', 'fill', '#c53d48')
                ->assertAttribute('.village-area[data-area-code="'.$second['code'].'"]', 'fill', '#2f8b63');
        });
    }
}
