<?php

namespace Tests\Browser\PBI11;

use Illuminate\Support\Facades\DB;
use Laravel\Dusk\Browser;

class TC03TimelineStatusTest extends PBI11TestCase
{
    /**
     * ID TC: TC-11-03
     * Skenario: Memastikan halaman menampilkan status terbaru setelah status berubah.
     * Kondisi awal: Pemilik memiliki pengajuan dengan status Diperiksa.
     * Hasil yang diharapkan: Halaman menampilkan status Diverifikasi.
     */
    public function test_latest_submission_status_is_displayed_after_update(): void
    {
        $this->browse(function (Browser $browser) {
            try {
                $this->login($browser, 'pemohon-pbi11@example.test');
                $browser->visit(route('pengajuan.terkirim', $this->fixture['pengajuan']))->pause(2000)
                    ->assertSee('Diperiksa');

                DB::table('pengajuan_plts')
                    ->where('id', $this->fixture['pengajuan'])
                    ->update([
                        'status_terkini' => 'diverifikasi',
                        'status_diperbarui_pada' => '2026-11-17 10:00:00',
                    ]);

                $browser->refresh()->pause(2000)
                    ->assertSee('PLTS-PBI11-001')
                    ->assertSee('Diverifikasi')
                    ->assertDontSee('Diperiksa');
            } finally {
                $browser->pause(3000);
            }
        });
    }
}
