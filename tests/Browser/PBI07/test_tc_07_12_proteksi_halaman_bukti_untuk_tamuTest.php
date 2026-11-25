<?php

namespace Tests\Browser\PBI07;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class test_tc_07_12_proteksi_halaman_bukti_untuk_tamuTest extends DuskTestCase
{
    /**
     * ID: TC-07-12
     * Fokus pengujian: Proteksi halaman bukti untuk tamu.
     * Prasyarat dan data uji: Pengguna belum login.
     * Hasil yang diharapkan: Akses diarahkan ke halaman login.
     * Jenis: Exception.    */
    public function test_tc_07_12_proteksi_halaman_bukti_untuk_tamu(): void
    {
        sleep(3); // Jeda demo agar durasi test mudah diamati.
        $this->browse(function (Browser $browser) {
            $browser->visit('http://127.0.0.1:8000/pengajuan/lokasi/bukti')
                ->pause(900)
                ->assertPathIs('/login')
                ->assertSee('Masuk');
        });
    }
}
