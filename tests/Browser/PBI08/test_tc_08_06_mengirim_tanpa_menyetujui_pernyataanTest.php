<?php

namespace Tests\Browser\PBI08;

use App\Models\PengajuanPlts;
use App\Models\User;
use App\Services\Pengajuan\LocationCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\DuskTestCase;

class test_tc_08_06_mengirim_tanpa_menyetujui_pernyataanTest extends DuskTestCase
{
    /**
     * ID TC: TC-08-06
     * Skenario: Mengirim tanpa menyetujui pernyataan.
     * Jenis: Exception.
     * Hasil yang diharapkan: Request ditolak; draft dan nomor DRAFT tetap,
     * nomor PLTS final belum diterbitkan, dan riwayat tidak dibuat.
     */
    public function test_tc_08_06_mengirim_tanpa_menyetujui_pernyataan(): void
    {
        sleep(3); // Jeda demo agar durasi test lebih mudah diamati.
        $email = 'dusk-pbi08-'.Str::uuid().'@example.test';
        $password = 'password-uji-tc-08-06';

        $address = app(LocationCatalog::class)->address([
            'kabupaten' => '3205',
            'kecamatan' => 'Cikelet',
            'desa' => '32.05.30.2010',
            'alamat' => 'Kampung Uji TC06, RT 01',
        ]);

        $user = new User;
        $user->name = 'Pengguna Uji PBI08 TC06';
        $user->email = $email;
        $user->password = Hash::make($password);
        $user->role = 'masyarakat';
        $user->is_active = true;
        $user->save();

        $draft = new PengajuanPlts;
        $draft->user_id = $user->id;
        $draft->nomor_tiket = 'DRAFT-DUSK-'.Str::upper(Str::random(12));
        $draft->ringkasan_pengajuan = 'Draft lengkap untuk pengujian TC-08-06';
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
                'nama_file_asli' => 'tc-08-06-foto.png',
                'path_file' => "bukti-pengajuan/{$draft->id}/tc-08-06-foto.png",
                'mime_type' => 'image/png',
                'ukuran_byte' => 1024,
                'keterangan' => null,
            ]);

            $csrfToken = Str::random(40);

            // Request sengaja tidak mengirim field "pernyataan".
            $response = $this->from('/pengajuan/lokasi/ringkasan')
                ->actingAs($user)
                ->withSession(['_token' => $csrfToken])
                ->post('/pengajuan/lokasi/kirim', [
                    '_token' => $csrfToken,
                    'draft_id' => $draft->id,
                ]);

            $response->assertRedirect('/pengajuan/lokasi/ringkasan')
                ->assertSessionHasErrors([
                    'pernyataan' => 'Setujui pernyataan kebenaran data sebelum mengirim.',
                ]);

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