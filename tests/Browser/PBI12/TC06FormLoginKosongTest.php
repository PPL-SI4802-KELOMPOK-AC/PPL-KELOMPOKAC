<?php

namespace Tests\Browser\PBI12;

use Laravel\Dusk\Browser;

class TC06FormLoginKosongTest extends PBI12TestCase
{
    /** TC-PBI12-06: Form login kosong ditolak oleh validasi browser. */
    public function test_tc_pbi12_06_form_login_kosong(): void
    {
        $this->browse(function (Browser $browser) {
            $this->clearBrowserSession($browser);

            $browser->visit('/login')
                ->assertPathIs('/login')
                ->assertPresent('input[name="email"][required]')
                ->assertPresent('input[name="password"][required]');
        });
    }
}
