<?php

namespace Tests\Browser\PBI04;

use Illuminate\Support\Facades\DB;
use Laravel\Dusk\Browser;
use Tests\Browser\MapDuskTestCase;

class PBI04TC0403Test extends MapDuskTestCase
{
    /** TC-04-03 | Tombol tutup menyembunyikan panel dan tombol Lihat detail membukanya lagi. */
    public function test_tc_04_03_tutup_dan_buka_kembali_panel_detail(): void
    {
        $record = $this->fixture;
        $this->browse(function (Browser $browser) use ($record) {
            $this->openMap($browser);
            $browser->click('[data-map-rows] tr[data-id="'.$record['id'].'"] button')
                ->waitForTextIn('#map-detail-title', $record['name'], 15)
                ->click('[data-map-close-detail]')
                ->waitUntil('document.querySelector("[data-map-detail-panel]").hidden === true', 10)
                ->assertVisible('[data-map-show-detail]')
                ->assertMissing('[data-map-rows] tr.is-selected')
                ->assertMissing('.selected-area')
                ->click('[data-map-show-detail]')
                ->waitUntil('document.querySelector("[data-map-detail-panel]").hidden === false', 10)
                ->assertVisible('[data-map-detail-panel]')
                ->waitUntil('document.querySelector("[data-map-show-detail]").hidden === true', 10);
        });
    }
}
