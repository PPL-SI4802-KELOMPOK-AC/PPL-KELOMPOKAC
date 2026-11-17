<?php

namespace Tests\Browser\PBI05;

use Laravel\Dusk\Browser;

class TC0508PencarianPublikTanpaLoginTest extends PBI05BrowserTestCase
{
    /** TC-05-08 - Akses publik: tamu tetap dapat mencari tanpa diarahkan ke login. */
    public function test_tc_05_08_pencarian_publik_dapat_digunakan_tanpa_login(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/peta-wilayah');
            $browser->driver->manage()->deleteAllCookies();
            $this->openMap($browser, self::KEYWORD)->assertGuest();
            // assertGuest() Dusk mengunjungi endpoint sesi, lalu memuat ulang halaman.
            $this->waitForResults($browser, self::KEYWORD);
            $browser->assertPathIs('/peta-wilayah')
                ->assertInputValue('q', self::KEYWORD);
            $this->assertGreaterThan(0, $this->resultTotal($browser));
            $this->assertMatchingRows($browser, self::KEYWORD);
            $browser->screenshot('tc-05-08-pencarian-tanpa-login');
        });
    }
}
