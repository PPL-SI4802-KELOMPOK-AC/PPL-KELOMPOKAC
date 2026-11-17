<?php

namespace Tests\Browser\PBI07;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class test_tc_07_12_membuka_halaman_bukti_tanpa_loginTest extends DuskTestCase
{
    public function test_tc_07_12_membuka_halaman_bukti_tanpa_login(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('http://127.0.0.1:8000/pengajuan/lokasi/bukti')
                ->assertPathIs('/login')
                ->assertSee('Masuk');
        });
    }
}