<?php

namespace Tests\Browser\PBI04;

use Illuminate\Support\Facades\DB;
use Laravel\Dusk\Browser;
use Tests\Browser\MapDuskTestCase;

class PBI04TC0401Test extends MapDuskTestCase
{
    /** TC-04-01 | Klik tombol Lihat menampilkan detail lengkap wilayah. */
    public function test_tc_04_01_pilih_wilayah_menampilkan_detail(): void
    {
        $record = $this->fixture;
        $this->browse(function (Browser $browser) use ($record) {
            $this->openMap($browser);
            $browser->click('[data-map-rows] tr[data-id="'.$record['id'].'"] button[aria-label="Lihat '.$record['name'].'"]')
                ->waitForTextIn('#map-detail-title', $record['name'], 15)
                ->assertSeeIn('[data-map-detail]', $record['address'])
                ->assertSeeIn('[data-map-detail]', 'Ada KK belum berlistrik')
                ->assertSeeIn('[data-map-detail]', 'SUKARESMI')
                ->assertSeeIn('[data-map-detail]', 'KABUPATEN GARUT')
                ->assertSeeIn('[data-map-detail]', '25 keluarga')
                ->assertSeeIn('[data-map-detail]', '2024')
                ->assertSeeIn('[data-map-detail]', $record['code'])
                ->assertPresent('[data-map-rows] tr.is-selected[data-id="'.$record['id'].'"]')
                ->assertVisible('[data-map-detail-panel]');
        });
    }
}
