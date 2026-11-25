<?php

namespace Tests\Browser\PBI08;

use App\Models\PengajuanPlts;
use App\Models\User;
use App\Services\Pengajuan\LocationCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\DuskTestCase;

class test_tc_08_08_mengirim_ulang_request_pengajuan_yang_sudah_berhasilTest extends DuskTestCase
{
    /**
     * ID TC: TC-08-08
     * Skenario: Mengirim ulang request pengajuan yang sudah berhasil.
     * Jenis: Normal.
     * Hasil yang diharapkan: Pengguna diarahkan ke tanda terima dengan
     * nomor yang sama dan tidak ada riwayat pengajuan baru yang duplikat.
     */
    public function test_tc_08_08_mengirim_ulang_request_pengajuan_yang_sudah_berhasil(): void
    {
        sleep(3); // Jeda demo agar durasi test lebih mudah diamati.
        $email = 'dusk-pbi08-'.Str::uuid().'@example.test';
        $password = 'password-uji-tc-08-08';

        $address = app(LocationCatalog::class)->address([
            'kabupaten' => '3205',
            'kecamatan' => 'Cikelet',
            'desa' => '32.05.30.2010',
            'alamat' => 'Kampung Uji TC08, RT 01',
        ]);

        $user = new User;
        $user->name = 'Pengguna Uji PBI08 TC08';
        $user->email = $email;
        $user->password = Hash::make($password);
        $user->role = 'masyarakat';
        $user->is_active = true;
        $user->save();

        $draft = new PengajuanPlts;
        $draft->user_id = $user->id;
        $draft->nomor_tiket = 'PLTS-'.now()->format('Ymd').'-'.Str::ulid();
        $draft->ringkasan_pengajuan = 'Pengajuan terkirim untuk pengujian TC-08-08';
        $draft->status_terkini = 'diajukan';
        $draft->status_diperbarui_pada = now();
        $draft->save();

        $nomorFinal = $draft->nomor_tiket;

        $draft->lokasi()->create([
            'alamat_deskriptif' => $address,
            'latitude' => null,
            'longitude' => null,
        ]);

        try {
            $draft->bukti()->create([
                'nama_file_asli' => 'tc-08-08-foto.png',
                'path_file' => "bukti-pengajuan/{$draft->id}/tc-08-08-foto.png",
                'mime_type' => 'image/png',
                'ukuran_byte' => 1024,
                'keterangan' => null,
            ]);

            // Riwayat awal yang sudah tercatat saat pengajuan pertama dikirim.
            DB::table('riwayat_status_pengajuan')->insert([
                'pengajuan_id' => $draft->id,
                'diubah_oleh' => $user->id,
                'status_sebelumnya' => null,
                'status_baru' => 'diajukan',
                'jenis_tindakan' => 'pengajuan_baru',
                'catatan' => 'Riwayat awal pengujian TC-08-08.',
                'created_at' => now(),
            ]);

            $csrfToken = Str::random(40);

            // Kirim ulang request dengan draft_id dan persetujuan yang sama.
            $response = $this->actingAs($user)
                ->withSession(['_token' => $csrfToken])
                ->post('/pengajuan/lokasi/kirim', [
                    '_token' => $csrfToken,
                    'draft_id' => $draft->id,
                    'pernyataan' => '1',
                ]);

            $response->assertRedirect('/pengajuan/lokasi/terkirim/'.$draft->id);

            $this->assertSame('diajukan', $draft->fresh()->status_terkini);
            $this->assertSame($nomorFinal, $draft->fresh()->nomor_tiket);
            $this->assertStringStartsWith('PLTS-', $draft->fresh()->nomor_tiket);

            $riwayat = DB::table('riwayat_status_pengajuan')
                ->where('pengajuan_id', $draft->id)
                ->where('jenis_tindakan', 'pengajuan_baru')
                ->get();

            $this->assertCount(1, $riwayat);
            $this->assertSame('diajukan', $riwayat->first()->status_baru);
            $this->assertSame($user->id, (int) $riwayat->first()->diubah_oleh);
        } finally {
            DB::table('riwayat_status_pengajuan')
                ->where('pengajuan_id', $draft->id)
                ->delete();

            $draft->bukti()->delete();
            $draft->lokasi()->delete();
            $draft->delete();
            $user->delete();
        }
    }
}