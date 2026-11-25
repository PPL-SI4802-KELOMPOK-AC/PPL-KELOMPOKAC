<?php

namespace Tests\Browser\PBI08;

use App\Models\PengajuanPlts;
use App\Models\User;
use App\Services\Pengajuan\LocationCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\DuskTestCase;

class test_tc_08_09_membuka_atau_mengirim_pengajuan_tanpa_loginTest extends DuskTestCase
{
    /**
     * ID TC: TC-08-09
     * Skenario: Membuka atau mengirim pengajuan tanpa login.
     * Jenis: Exception.
     * Hasil yang diharapkan: Akses diarahkan ke login dan data pengajuan
     * tidak berubah.
     */
    public function test_tc_08_09_membuka_atau_mengirim_pengajuan_tanpa_login(): void
    {
        sleep(3); // Jeda demo agar durasi test lebih mudah diamati.
        $address = app(LocationCatalog::class)->address([
            'kabupaten' => '3205',
            'kecamatan' => 'Cikelet',
            'desa' => '32.05.30.2010',
            'alamat' => 'Kampung Uji TC09, RT 01',
        ]);

        $user = new User;
        $user->name = 'Pengguna Uji PBI08 TC09';
        $user->email = 'dusk-pbi08-'.Str::uuid().'@example.test';
        $user->password = Hash::make('password-uji-tc-08-09');
        $user->role = 'masyarakat';
        $user->is_active = true;
        $user->save();

        $draft = new PengajuanPlts;
        $draft->user_id = $user->id;
        $draft->nomor_tiket = 'DRAFT-DUSK-'.Str::upper(Str::random(12));
        $draft->ringkasan_pengajuan = 'Draft lengkap untuk pengujian TC-08-09';
        $draft->status_terkini = 'draft';
        $draft->status_diperbarui_pada = now();
        $draft->save();

        $nomorDraft = $draft->nomor_tiket;

        $draft->lokasi()->create([
            'alamat_deskriptif' => $address,
            'latitude' => null,
            'longitude' => null,
        ]);

        try {
            $draft->bukti()->create([
                'nama_file_asli' => 'tc-08-09-foto.png',
                'path_file' => "bukti-pengajuan/{$draft->id}/tc-08-09-foto.png",
                'mime_type' => 'image/png',
                'ukuran_byte' => 1024,
                'keterangan' => null,
            ]);

            // Membuka ringkasan tanpa sesi login.
            $this->get('/pengajuan/lokasi/ringkasan')
                ->assertRedirect('/login');

            // Mengirim request tanpa sesi login. Token CSRF disertakan supaya
            // request mencapai middleware autentikasi.
            $csrfToken = Str::random(40);

            $this->withSession(['_token' => $csrfToken])
                ->post('/pengajuan/lokasi/kirim', [
                    '_token' => $csrfToken,
                    'draft_id' => $draft->id,
                    'pernyataan' => '1',
                ])
                ->assertRedirect('/login');

            $this->assertSame('draft', $draft->fresh()->status_terkini);
            $this->assertSame($nomorDraft, $draft->fresh()->nomor_tiket);
            $this->assertStringStartsWith('DRAFT-', $draft->fresh()->nomor_tiket);
            $this->assertSame(
                0,
                DB::table('riwayat_status_pengajuan')
                    ->where('pengajuan_id', $draft->id)
                    ->count()
            );
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