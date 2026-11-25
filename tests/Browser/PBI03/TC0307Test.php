<?php

namespace Tests\Browser\PBI03;

use Illuminate\Support\Facades\DB;
use Laravel\Dusk\Browser;
use Tests\Browser\MapDuskTestCase;

class PBI03TC0307Test extends MapDuskTestCase
{
    /** TC-03-07 | Negatif: filter tanpa hasil menampilkan keadaan kosong dengan jelas. */
    public function test_tc_03_07_pencarian_tanpa_hasil_tidak_crash(): void
    {
        $this->browse(function (Browser $browser) {
            $this->openMap($browser);
            $browser->type('input[name="q"]', 'WILAYAH-TIDAK-ADA-PBI03')
                ->waitUntil('document.querySelector("[data-map-data-state]").dataset.mapDataState === "ready" && document.querySelector("[data-map-empty]").hidden === false', 20)
                ->assertSeeIn('[data-map-data-status]', 'Tidak ada wilayah yang sesuai')
                ->assertVisible('[data-map-empty]')
                ->assertCount('[data-map-rows] tr', 0)
                ->assertCount('.district-area', 27)
                ->assertAttribute('[data-map-panel]', 'data-state', 'ready')
                ->assertMissing('[data-map-data-retry]');
        });
    }
}
