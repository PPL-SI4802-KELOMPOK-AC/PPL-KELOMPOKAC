<?php

namespace Tests\Browser\PBI11;

use Laravel\Dusk\Browser;

class TC01LihatStatusPengajuanTest extends PBI11TestCase
{
    /**
     * ID TC: TC-11-01
     * Skenario: Melihat status pengajuan sendiri.
     * Kondisi awal: Pemilik sudah login dan memiliki pengajuan terkirim.
     * Hasil yang diharapkan: Nomor dan status terkini pengajuan tampil.
     * Catatan: Halaman bukti pengiriman saat ini tidak menampilkan linimasa.
     */
    public function test_owner_can_view_their_own_submission_status(): void
    {
        $this->browse(function (Browser $browser) {
            $this->login($browser, 'pemohon-pbi11@example.test');
            $browser->assertPathIs('/peta-wilayah');

            $browser->visit(route('pengajuan.terkirim', [
                'pengajuan' => $this->fixture['pengajuan'],
            ]))
                ->assertSee('PLTS-PBI11-001')
                ->assertSee('Diperiksa');
        });
    }
}
