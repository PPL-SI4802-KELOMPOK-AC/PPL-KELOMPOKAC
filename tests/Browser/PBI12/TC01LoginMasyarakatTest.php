<?php

namespace Tests\Browser\PBI12;

use Laravel\Dusk\Browser;

class TC01LoginMasyarakatTest extends PBI12TestCase
{
    /** TC-PBI12-01: Login berhasil sebagai Masyarakat. */
    public function test_tc_pbi12_01_login_masyarakat(): void
    {
        $this->browse(function (Browser $browser) {
            $this->clearBrowserSession($browser);

            $this->login(
                $browser,
                'masyarakat.uji@example.test',
                self::PASSWORD_MASYARAKAT
            );

            $browser->assertPathIs('/peta-wilayah');
        });
    }
}
