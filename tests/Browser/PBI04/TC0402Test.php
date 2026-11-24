<?php

namespace Tests\Browser\PBI04;

use Illuminate\Support\Facades\DB;
use Laravel\Dusk\Browser;
use Tests\Browser\MapDuskTestCase;

class PBI04TC0402Test extends MapDuskTestCase
{
    /** TC-04-02 | Memilih wilayah lain mengganti detail dan highlight sebelumnya. */
    public function test_tc_04_02_memilih_wilayah_lain_mengganti_detail(): void
    {
        $second = $this->addSampleRegion('3201', 11);
        $first = $this->fixture;
        $this->browse(function (Browser $browser) use ($first, $second) {
            $this->openMap($browser);
            $browser->click('[data-map-rows] tr[data-id="'.$first['id'].'"] button')
                ->waitForTextIn('#map-detail-title', $first['name'], 15)
                ->click('[data-map-rows] tr[data-id="'.$second['id'].'"] button')
                ->waitForTextIn('#map-detail-title', $second['name'], 15)
                ->assertSeeIn('[data-map-detail]', '11 keluarga')
                ->assertMissing('[data-map-rows] tr.is-selected[data-id="'.$first['id'].'"]')
                ->assertPresent('[data-map-rows] tr.is-selected[data-id="'.$second['id'].'"]');
        });
    }
}
