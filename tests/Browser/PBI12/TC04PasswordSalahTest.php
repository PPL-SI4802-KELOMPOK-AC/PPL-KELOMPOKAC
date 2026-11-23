<?php

namespace Tests\Browser\PBI12;

use Laravel\Dusk\Browser;

class TC04PasswordSalahTest extends PBI12TestCase
{
    /** TC-PBI12-04: Password salah ditolak. */
    public function test_tc_pbi12_04_password_salah(): void
    {
        $this->browse(function (Browser $browser) {
            try {
                $this->clearBrowserSession($browser);

                $browser->visit('/login')->pause(2000)
                    ->type('email', 'masyarakat.uji@example.test')->pause(2000)
                    ->type('password', 'password-salah')->pause(2000)
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
