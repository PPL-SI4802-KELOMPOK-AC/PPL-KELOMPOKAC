<?php

namespace Tests\Browser\PBI11;

use Laravel\Dusk\Browser;

class TC01LihatStatusPengajuanTest extends PBI11TestCase
{
    /**
     * ID TC: TC-11-01
     * Pemetaan: skenario status pengajuan legacy, di luar Jira autentikasi PBI-11/PBI-12 terbaru.
     * Skenario: Melihat status pengajuan sendiri.
     * Kondisi awal: Pemilik sudah login dan memiliki pengajuan terkirim.
     * Hasil yang diharapkan: Nomor dan status terkini pengajuan tampil.
     * Ekspektasi legacy: linimasa menampilkan catatan perubahan status.
     */
    public function test_owner_can_view_their_own_submission_status(): void
    {
        $this->browse(function (Browser $browser) {
            try {
                $this->login($browser, 'pemohon-pbi11@example.test');
                $browser->assertPathIs('/peta-wilayah');

                $browser->visit(route('pengajuan.terkirim', [
                    'pengajuan' => $this->fixture['pengajuan'],
                ]))->pause(2000)
                    ->assertSee('PLTS-PBI11-001')
                    ->assertSee('Diperiksa')
                    ->assertSee('Pengajuan sedang diperiksa.');
            } finally {
                $browser->pause(3000);
            }
        });
    }
}
