<?php

namespace Tests\Browser\PBI11;

use Illuminate\Support\Facades\DB;
use Laravel\Dusk\Browser;

class TC02WaktuPembaruanStatusTest extends PBI11TestCase
{
    /**
     * ID TC: TC-11-02
     * Pemetaan: skenario status pengajuan legacy, di luar Jira autentikasi PBI-11/PBI-12 terbaru.
     * Skenario: Memastikan tiga catatan riwayat fixture berurutan secara kronologis.
     * Kondisi awal: Pemilik memiliki pengajuan dengan tiga catatan riwayat.
     * Hasil yang diharapkan: Tiga catatan tampil kronologis pada halaman pengajuan.
     */
    public function test_three_status_history_notes_are_ordered_by_created_at(): void
    {
        DB::table('riwayat_status_pengajuan')->insert([
            'pengajuan_id' => $this->fixture['pengajuan'],
            'diubah_oleh' => $this->fixture['owner']->id,
            'status_sebelumnya' => 'diperiksa',
            'status_baru' => 'diverifikasi',
            'jenis_tindakan' => 'perubahan_status',
            'hasil_verifikasi' => null,
            'catatan' => 'Dokumen telah diverifikasi.',
            'created_at' => '2026-11-17 10:00:00',
        ]);

        // Catatan terakhir dan ringkasan pengajuan harus menggambarkan status yang sama.
        DB::table('pengajuan_plts')
            ->where('id', $this->fixture['pengajuan'])
            ->update([
                'status_terkini' => 'diverifikasi',
                'status_diperbarui_pada' => '2026-11-17 10:00:00',
            ]);

        $history = DB::table('riwayat_status_pengajuan')
            ->where('pengajuan_id', $this->fixture['pengajuan'])
            ->orderBy('created_at')
            ->get(['created_at', 'catatan'])
            ->map(fn ($entry) => [
                'created_at' => $entry->created_at,
                'catatan' => $entry->catatan,
            ])
            ->all();

        $this->assertSame([
            [
                'created_at' => '2026-11-15 08:00:00',
                'catatan' => 'Pengajuan berhasil dikirim.',
            ],
            [
                'created_at' => '2026-11-17 09:00:00',
                'catatan' => 'Pengajuan sedang diperiksa.',
            ],
            [
                'created_at' => '2026-11-17 10:00:00',
                'catatan' => 'Dokumen telah diverifikasi.',
            ],
        ], $history);

        $this->browse(function (Browser $browser) {
            try {
                $this->login($browser, 'pemohon-pbi11@example.test');
                $browser->visit(route('pengajuan.terkirim', $this->fixture['pengajuan']))->pause(2000);
                $text = $browser->script('return document.body.innerText;')[0];
                $previous = -1;
                foreach ([
                    'Pengajuan berhasil dikirim.',
                    'Pengajuan sedang diperiksa.',
                    'Dokumen telah diverifikasi.',
                ] as $note) {
                    $position = strpos($text, $note);
                    $this->assertNotFalse($position, 'Catatan riwayat tidak tampil: '.$note);
                    $this->assertGreaterThan($previous, $position);
                    $previous = $position;
                }
            } finally {
                $browser->pause(3000);
            }
        });
    }
}
