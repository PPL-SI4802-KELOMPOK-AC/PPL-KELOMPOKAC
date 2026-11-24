<?php

namespace Tests\Browser\PBI03;

use Illuminate\Support\Facades\DB;
use Laravel\Dusk\Browser;
use Tests\Browser\MapDuskTestCase;

class PBI03TC0302Test extends MapDuskTestCase
{
    /** TC-03-02 | Normal: warna poligon desa mengikuti tiga kategori data KK. */
    public function test_tc_03_02_warna_area_mengikuti_status_data(): void
    {
        $positive = $this->addSampleRegion('3201', 10, null, false, 0);
        $zero = $this->addSampleRegion('3201', 0, null, false, 1);
        $unavailable = $this->addSampleRegion('3201', null, null, false, 2);

        $this->browse(function (Browser $browser) use ($positive, $zero, $unavailable) {
            $this->openMap($browser, '?kabupaten=3201');
            $browser->waitUntil('document.querySelector("[data-region-map]").dataset.areaLevel === "village"', 20)
                ->assertAttribute('.village-area[data-area-code="'.$positive['code'].'"]', 'fill', '#c53d48')
                ->assertAttribute('.village-area[data-area-code="'.$zero['code'].'"]', 'fill', '#2f8b63')
                ->assertAttribute('.village-area[data-area-code="'.$unavailable['code'].'"]', 'fill', '#b8c4ce')
                ->assertSeeIn('[data-area-legend]', 'Ada KK belum berlistrik')
                ->assertSeeIn('[data-area-legend]', 'Tidak tercatat KK belum berlistrik')
                ->assertSeeIn('[data-area-legend]', 'Data belum tersedia');
        });
    }
}
