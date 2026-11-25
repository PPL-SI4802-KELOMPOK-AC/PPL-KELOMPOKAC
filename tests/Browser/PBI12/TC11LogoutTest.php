<?php

namespace Tests\Browser\PBI12;

use Laravel\Dusk\Browser;

class TC11LogoutTest extends PBI12TestCase
{
    /** TC-PBI12-11: Logout mengakhiri sesi pengguna. */
    public function test_tc_pbi12_11_logout(): void
    {
        $this->browse(function (Browser $browser) {
            try {
                $this->clearBrowserSession($browser);

                $this->login(
                    $browser,
                    'masyarakat.uji@example.test',
                    self::PASSWORD_MASYARAKAT
                );

                $browser->assertPathIs('/peta-wilayah')
                    ->pause(2000)->click('form[action$="/logout"] button[type="submit"]')->pause(2000)
                    ->waitForLocation('/login')
                    ->assertPathIs('/login')
                    ->visit('/pengajuan/riwayat')->pause(2000)
                    ->assertPathIs('/login');
            } finally {
                $browser->pause(3000);
            }
        });
    }
}
