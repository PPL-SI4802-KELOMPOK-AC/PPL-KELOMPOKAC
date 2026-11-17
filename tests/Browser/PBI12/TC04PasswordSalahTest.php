<?php

namespace Tests\Browser\PBI12;

use Laravel\Dusk\Browser;

class TC04PasswordSalahTest extends PBI12TestCase
{
    /** TC-PBI12-04: Password salah ditolak. */
    public function test_tc_pbi12_04_password_salah(): void
    {
        $this->browse(function (Browser $browser) {
            $this->clearBrowserSession($browser);

            $browser->visit('/login')
                ->type('email', 'masyarakat.uji@example.test')
                ->type('password', 'password-salah')
                ->press('Masuk')
                ->assertSee('Email atau kata sandi salah');
        });
    }
}
