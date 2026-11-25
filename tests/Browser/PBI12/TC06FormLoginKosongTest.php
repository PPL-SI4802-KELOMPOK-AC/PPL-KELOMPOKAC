<?php

namespace Tests\Browser\PBI12;

use Laravel\Dusk\Browser;

class TC06FormLoginKosongTest extends PBI12TestCase
{
    /** TC-PBI12-06: Form login kosong ditolak oleh validasi browser. */
    public function test_tc_pbi12_06_form_login_kosong(): void
    {
        $this->browse(function (Browser $browser) {
            try {
                $this->clearBrowserSession($browser);

                $browser->visit('/login')->pause(2000)
                    ->assertPathIs('/login')
                    ->assertPresent('input[name="email"][required]')
                    ->assertPresent('input[name="password"][required]')
                    ->pause(2000)->press('Masuk')->pause(2000)
                    ->assertPathIs('/login');

                $this->assertFalse($browser->script('return document.querySelector("form.region-form").checkValidity()')[0]);
                $this->assertTrue($browser->script('return document.querySelector("#email").validity.valueMissing')[0]);
                $this->assertTrue($browser->script('return document.querySelector("#password").validity.valueMissing')[0]);
            } finally {
                $browser->pause(3000);
            }
        });
    }
}
