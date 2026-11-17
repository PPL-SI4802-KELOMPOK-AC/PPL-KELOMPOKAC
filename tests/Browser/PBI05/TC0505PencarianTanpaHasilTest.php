<?php

namespace Tests\Browser\PBI05;

use Laravel\Dusk\Browser;

class TC0505PencarianTanpaHasilTest extends PBI05BrowserTestCase
{
    /** TC-05-05 - Exception: kata yang tidak ditemukan menghasilkan daftar kosong. */
    public function test_tc_05_05_kata_tidak_ditemukan_menampilkan_pesan_kosong(): void
    {
        $this->browse(function (Browser $browser) {
            $this->openMap($browser, 'xyzabc');
            $this->assertSame(0, $this->resultTotal($browser));
            $this->assertSame([], $this->resultIds($browser));
            $browser->assertVisible('[data-map-empty]')
                ->assertSeeIn('[data-map-empty]', 'Tidak ada wilayah yang sesuai pencarian atau filter.')
                ->assertSeeIn('[data-map-list-count]', '0 wilayah')
                ->assertMissing('#map-search-suggestions')
                ->screenshot('tc-05-05-kata-tidak-ditemukan');
        });
    }
}
