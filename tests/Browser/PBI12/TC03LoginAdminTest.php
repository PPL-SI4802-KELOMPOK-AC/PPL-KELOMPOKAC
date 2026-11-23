<?php

namespace Tests\Browser\PBI12;

use Laravel\Dusk\Browser;

class TC03LoginAdminTest extends PBI12TestCase
{
    /** TC-PBI12-03: Login berhasil sebagai Admin. */
    public function test_tc_pbi12_03_login_admin(): void
    {
        $this->browse(function (Browser $browser) {
            try {
                $this->clearBrowserSession($browser);

                $this->login(
                    $browser,
                    'tom@mail.com',
                    self::PASSWORD_ADMIN
                );

                $browser->assertPathIs('/admin/ringkasan')
                    ->assertSee('Ringkasan operasional');
            } finally {
                $browser->pause(3000);
            }
        });
    }
}
