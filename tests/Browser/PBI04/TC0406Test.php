<?php

namespace Tests\Browser\PBI04;

use Illuminate\Support\Facades\DB;
use Laravel\Dusk\Browser;
use Tests\Browser\MapDuskTestCase;

class PBI04TC0406Test extends MapDuskTestCase
{
    /** TC-04-06 | HTTP 503 pada endpoint peta menampilkan status gagal dan dapat dicoba lagi. */
    public function test_tc_04_06_layanan_gagal_menampilkan_pesan_dan_retry(): void
    {
        $this->browse(function (Browser $browser) {
            $this->openMap($browser);
            $browser->script(<<<'JS'
                window.__pbi04OriginalFetch = window.fetch.bind(window);
                window.fetch = (input, init) => String(input).includes('/peta-wilayah/data')
                    ? Promise.resolve(new Response(JSON.stringify({message: 'Layanan data tidak tersedia.'}), {
                        status: 503, headers: {'Content-Type': 'application/json'}
                    }))
                    : window.__pbi04OriginalFetch(input, init);
                JS);
            $browser->type('input[name="q"]', 'SIMULASI-GAGAL-PBI04')
                ->waitUntil('document.querySelector("[data-map-data-state]").dataset.mapDataState === "error"', 15)
                ->assertSeeIn('[data-map-data-status]', 'Gagal mengambil data wilayah')
                ->assertVisible('[data-map-data-retry]')
                ->assertAttribute('[data-region-map]', 'aria-busy', 'false');
            $browser->script('window.fetch = window.__pbi04OriginalFetch; delete window.__pbi04OriginalFetch;');
            $browser->click('[data-map-data-retry]')
                ->waitUntil('document.querySelector("[data-map-data-state]").dataset.mapDataState === "ready"', 20)
                ->assertMissing('[data-map-data-retry]')
                ->assertAttribute('[data-map-panel]', 'data-state', 'ready');
        });
    }
}
