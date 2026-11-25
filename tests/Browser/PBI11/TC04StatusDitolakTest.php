<?php

namespace Tests\Browser\PBI11;

use Illuminate\Support\Facades\DB;
use Laravel\Dusk\Browser;

class TC04StatusDitolakTest extends PBI11TestCase
{
    /**
     * ID TC: TC-11-04
     * Skenario: Melihat bukti pengiriman untuk pengajuan yang ditolak.
     * Kondisi awal: Pemilik memiliki pengajuan dengan status ditolak.
     * Hasil yang diharapkan: Bukti pengiriman menampilkan status Ditolak dan alasannya.
     */
    public function test_rejected_status_and_reason_are_displayed(): void
    {
        DB::table('pengajuan_plts')
            ->where('id', $this->fixture['pengajuan'])
            ->update([
                'status_terkini' => 'ditolak',
                'status_diperbarui_pada' => '2026-11-17 10:00:00',
            ]);

        DB::table('riwayat_status_pengajuan')->insert([
            'pengajuan_id' => $this->fixture['pengajuan'],
            'diubah_oleh' => $this->fixture['owner']->id,
            'status_sebelumnya' => 'diperiksa',
            'status_baru' => 'ditolak',
            'jenis_tindakan' => 'perubahan_status',
            'hasil_verifikasi' => 'ditolak',
            'catatan' => 'Dokumen persyaratan tidak lengkap.',
            'created_at' => '2026-11-17 10:00:00',
        ]);

        $this->assertDatabaseHas('riwayat_status_pengajuan', [
            'pengajuan_id' => $this->fixture['pengajuan'],
            'status_baru' => 'ditolak',
            'catatan' => 'Dokumen persyaratan tidak lengkap.',
        ]);

        $this->browse(function (Browser $browser) {
            try {
                $this->login($browser, 'pemohon-pbi11@example.test');

                $browser->visit(route('pengajuan.terkirim', [
                    'pengajuan' => $this->fixture['pengajuan'],
                ]))->pause(2000)
                    ->assertSeeIn('.submission-receipt', 'PLTS-PBI11-001')
                    ->assertSeeIn('@status-terkini', 'Ditolak')
                    ->assertSeeIn('[data-status-history]', 'Dokumen persyaratan tidak lengkap.');
            } finally {
                $browser->pause(3000);
            }
        });
    }
}
