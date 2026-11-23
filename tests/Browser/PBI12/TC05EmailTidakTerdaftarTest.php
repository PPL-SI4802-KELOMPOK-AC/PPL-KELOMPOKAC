<?php

namespace Tests\Browser\PBI12;

use Laravel\Dusk\Browser;

class TC05EmailTidakTerdaftarTest extends PBI12TestCase
{
    /** TC-PBI12-05: Email tidak terdaftar ditolak. */
    public function test_tc_pbi12_05_email_tidak_terdaftar(): void
    {
        $this->browse(function (Browser $browser) {
            try {
                $this->clearBrowserSession($browser);

                $browser->visit('/login')->pause(2000)
                    ->type('email', 'tidak.terdaftar@example.test')->pause(2000)
                    ->type('password', '1234567890')->pause(2000)
                    ->press('Masuk')->pause(2000)
                    ->waitFor('#email-error')
                    ->assertPathIs('/login')
                    ->assertSee('Email atau kata sandi salah');
            } finally {
                $browser->pause(3000);
            }
        });
    }
}
