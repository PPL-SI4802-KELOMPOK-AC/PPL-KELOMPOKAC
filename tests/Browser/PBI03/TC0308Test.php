<?php

namespace Tests\Browser\PBI03;

use Illuminate\Support\Facades\DB;
use Laravel\Dusk\Browser;
use Tests\Browser\MapDuskTestCase;

class PBI03TC0308Test extends MapDuskTestCase
{
    /** TC-03-08 | Negatif: kegagalan API menampilkan retry, lalu pulih tanpa crash. */
    public function test_tc_03_08_api_gagal_dapat_dicoba_ulang(): void
    {
        $this->browse(function (Browser $browser) {
            $this->openMap($browser);
            $browser->script(<<<'JS'
                window.__pbi03OriginalFetch = window.fetch.bind(window);
                window.fetch = (input, init) => String(input).includes('/peta-wilayah/data')
                    ? Promise.resolve(new Response(JSON.stringify({message: 'Layanan data sedang tidak tersedia.'}), {
                        status: 503, headers: {'Content-Type': 'application/json'}
                    }))
                    : window.__pbi03OriginalFetch(input, init);
                JS);
            $browser->type('input[name="q"]', 'SIMULASI-GAGAL-PBI03')
                ->waitUntil('document.querySelector("[data-map-data-state]").dataset.mapDataState === "error"', 15)
                ->assertSeeIn('[data-map-data-status]', 'Gagal mengambil data wilayah')
                ->assertVisible('[data-map-data-retry]')
                ->assertAttribute('[data-region-map]', 'aria-busy', 'false')
                ->assertPresent('.leaflet-control-zoom');

            $browser->script('window.fetch = window.__pbi03OriginalFetch; delete window.__pbi03OriginalFetch;');
            $browser->click('[data-map-data-retry]')
                ->waitUntil('document.querySelector("[data-map-data-state]").dataset.mapDataState === "ready"', 20)
                ->assertMissing('[data-map-data-retry]')
                ->assertAttribute('[data-map-panel]', 'data-state', 'ready');
        });
    }
}
