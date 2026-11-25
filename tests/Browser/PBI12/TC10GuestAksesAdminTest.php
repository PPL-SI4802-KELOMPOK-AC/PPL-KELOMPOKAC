<?php

namespace Tests\Browser\PBI12;

use Laravel\Dusk\Browser;

class TC10GuestAksesAdminTest extends PBI12TestCase
{
    /** TC-PBI12-10: Pengunjung tanpa login diarahkan ke halaman login. */
    public function test_tc_pbi12_10_pengunjung_diarahkan_ke_login(): void
    {
        $this->browse(function (Browser $browser) {
            try {
                $this->clearBrowserSession($browser);

                $browser->visit('/admin/ringkasan')->pause(2000)
                    ->assertPathIs('/login');
            } finally {
                $browser->pause(3000);
            }
        });
    }
}
