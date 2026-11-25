<?php

namespace Tests\Browser\PBI12;

use Laravel\Dusk\Browser;

class TC02LoginPemerintahTest extends PBI12TestCase
{
    /** TC-PBI12-02: Login berhasil sebagai Pemerintah. */
    public function test_tc_pbi12_02_login_pemerintah(): void
    {
        $this->browse(function (Browser $browser) {
            try {
                $this->clearBrowserSession($browser);

                $this->login(
                    $browser,
                    'sep@mail.com',
                    self::PASSWORD_PEMERINTAH
                );

                $browser->assertPathIs('/pemerintah/wilayah');
            } finally {
                $browser->pause(3000);
            }
        });
    }
}
