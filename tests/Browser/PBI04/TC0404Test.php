<?php

namespace Tests\Browser\PBI04;

use Illuminate\Support\Facades\DB;
use Laravel\Dusk\Browser;
use Tests\Browser\MapDuskTestCase;

class PBI04TC0404Test extends MapDuskTestCase
{
    /** TC-04-04 | Area batas tanpa data aplikasi memberi keterangan, bukan error. */
    public function test_tc_04_04_area_tidak_memiliki_data_detail(): void
    {
        $this->browse(function (Browser $browser) {
            $this->openMap($browser, '?kabupaten=3201');
            $browser->waitUntil('document.querySelector("[data-region-map]").dataset.areaLevel === "village"', 20);
            $this->clickArea($browser, '.village-area');
            $browser->assertVisible('[data-map-detail-panel]')
                ->assertSeeIn('[data-map-detail]', 'Batas desa tersedia')
                ->assertSeeIn('[data-map-detail]', 'belum ada data aplikasi yang cocok')
                ->assertAttribute('[data-map-panel]', 'data-state', 'ready');
        });
    }
}
