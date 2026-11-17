<?php

namespace Tests\Browser\PBI12;

use Laravel\Dusk\Browser;

class TC05EmailTidakTerdaftarTest extends PBI12TestCase
{
    /** TC-PBI12-05: Email tidak terdaftar ditolak. */
    public function test_tc_pbi12_05_email_tidak_terdaftar(): void
    {
        $this->browse(function (Browser $browser) {
            $this->clearBrowserSession($browser);

            $browser->visit('/login')
                ->type('email', 'tidak.terdaftar@example.test')
                ->type('password', '1234567890')
                ->press('Masuk')
                ->assertSee('Email atau kata sandi salah');
        });
    }
}
