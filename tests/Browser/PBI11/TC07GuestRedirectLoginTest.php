<?php

namespace Tests\Browser\PBI11;

use Laravel\Dusk\Browser;

class TC07GuestRedirectLoginTest extends PBI11TestCase
{
    /**
     * ID TC: TC-11-07
     * Skenario: Pengguna belum login membuka halaman status pengajuan.
     * Kondisi awal: Pengguna belum login.
     * Hasil yang diharapkan: Sistem mengarahkan pengguna ke halaman login.
     */

    public function test_guest_is_redirected_to_login(): void
    {
        $this->browse(function (Browser $browser) {
            $this->clearBrowserSession($browser);

            $browser->visit(route('pengajuan.terkirim', [
                'pengajuan' => $this->fixture['pengajuan'],
            ]))
                ->assertPathIs('/login');
        });
    }
}
