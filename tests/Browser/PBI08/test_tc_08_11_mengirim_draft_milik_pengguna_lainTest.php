<?php

namespace Tests\Browser\PBI08;

use App\Models\PengajuanPlts;
use App\Models\User;
use App\Services\Pengajuan\LocationCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\DuskTestCase;

class test_tc_08_11_mengirim_draft_milik_pengguna_lainTest extends DuskTestCase
{
    /**
     * ID TC: TC-08-11
     * Skenario: Mengirim draft milik pengguna lain.
     * Jenis: Exception.
     * Hasil yang diharapkan: Request ditolak dan draft milik pengguna lain
     * tidak berubah.
     */
    public function test_tc_08_11_mengirim_draft_milik_pengguna_lain(): void
    {
        sleep(3); // Jeda demo agar durasi test lebih mudah diamati.
        $address = app(LocationCatalog::class)->address([
            'kabupaten' => '3205',
            'kecamatan' => 'Cikelet',
            'desa' => '32.05.30.2010',
            'alamat' => 'Kampung Uji TC11, RT 01',
        ]);

        $pemilik = new User;
        $pemilik->name = 'Pemilik Draft Uji PBI08 TC11';
        $pemilik->email = 'dusk-pbi08-'.Str::uuid().'@example.test';
        $pemilik->password = Hash::make('password-uji-tc-08-11');
        $pemilik->role = 'masyarakat';
        $pemilik->is_active = true;
        $pemilik->save();

        $penggunaLain = new User;
        $penggunaLain->name = 'Pengguna Lain Uji PBI08 TC11';
        $penggunaLain->email = 'dusk-pbi08-'.Str::uuid().'@example.test';
        $penggunaLain->password = Hash::make('password-uji-tc-08-11');
        $penggunaLain->role = 'masyarakat';
        $penggunaLain->is_active = true;
        $penggunaLain->save();

        $draft = new PengajuanPlts;
        $draft->user_id = $pemilik->id;
        $draft->nomor_tiket = 'DRAFT-DUSK-'.Str::upper(Str::random(12));
        $draft->ringkasan_pengajuan = 'Draft lengkap milik pengguna lain untuk TC-08-11';
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
                'nama_file_asli' => 'tc-08-11-foto.png',
                'path_file' => "bukti-pengajuan/{$draft->id}/tc-08-11-foto.png",
                'mime_type' => 'image/png',
                'ukuran_byte' => 1024,
                'keterangan' => null,
            ]);

            $csrfToken = Str::random(40);

            $response = $this->actingAs($penggunaLain)
                ->withSession(['_token' => $csrfToken])
                ->post('/pengajuan/lokasi/kirim', [
                    '_token' => $csrfToken,
                    'draft_id' => $draft->id,
                    'pernyataan' => '1',
                ]);

            $response->assertNotFound();

            $this->assertSame('draft', $draft->fresh()->status_terkini);
            $this->assertSame($nomorDraft, $draft->fresh()->nomor_tiket);
            $this->assertStringStartsWith('DRAFT-', $draft->fresh()->nomor_tiket);
            $this->assertSame(1, $draft->bukti()->count());
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
            $pemilik->delete();
            $penggunaLain->delete();
        }
    }
}