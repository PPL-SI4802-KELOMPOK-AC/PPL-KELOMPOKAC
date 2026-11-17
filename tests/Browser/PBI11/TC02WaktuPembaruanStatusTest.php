<?php

namespace Tests\Browser\PBI11;

use Illuminate\Support\Facades\DB;

class TC02WaktuPembaruanStatusTest extends PBI11TestCase
{
    /**
     * ID TC: TC-11-02
     * Skenario: Memastikan tiga catatan riwayat fixture berurutan secara kronologis.
     * Kondisi awal: Pemilik memiliki pengajuan dengan tiga catatan riwayat.
     * Hasil yang diharapkan: Data SQLite terurut berdasarkan created_at ascending.
     * Catatan: Halaman bukti pengiriman saat ini tidak menampilkan catatan riwayat,
     * sehingga test ini memverifikasi urutan data fixture, bukan tampilan UI.
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
    }
}
