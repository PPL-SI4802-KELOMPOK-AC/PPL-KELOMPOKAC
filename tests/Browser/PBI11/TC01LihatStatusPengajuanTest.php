<?php

namespace Tests\Browser\PBI11;

use Laravel\Dusk\Browser;

class TC01LihatStatusPengajuanTest extends PBI11TestCase
{
    /**
     * ID TC: TC-11-01
     * Skenario: Melihat status pengajuan sendiri.
     * Kondisi awal: Pemilik sudah login dan memiliki pengajuan terkirim.
     * Hasil yang diharapkan: Nomor, status terkini, dan catatan pemeriksaan tampil.
     */
    public function test_owner_can_view_their_own_submission_status(): void
    {
        $this->browse(function (Browser $browser) {
            try {
                $this->login($browser, 'pemohon-pbi11@example.test');
                $browser->assertPathIs('/peta-wilayah');

                $detail = route('pengajuan.terkirim', ['pengajuan' => $this->fixture['pengajuan']]);

                $browser->visit(route('pengajuan.riwayat'))->pause(2000)
                    ->click('a[href="'.$detail.'"]')
                    ->waitForLocation(parse_url($detail, PHP_URL_PATH))
                    ->assertSeeIn('.submission-receipt', 'PLTS-PBI11-001')
                    ->assertSeeIn('@status-terkini', 'Diperiksa')
                    ->assertSeeIn('[data-status-history]', 'Pengajuan sedang diperiksa.');
            } finally {
                $browser->pause(3000);
            }
        });
    }
}
