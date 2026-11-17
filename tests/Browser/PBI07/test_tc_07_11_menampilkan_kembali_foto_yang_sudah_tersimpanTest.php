<?php

namespace Tests\Browser\PBI07;

use App\Models\PengajuanPlts;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class test_tc_07_11_menampilkan_kembali_foto_yang_sudah_tersimpanTest extends DuskTestCase
{
    /**
     * ID TC: TC-07-11
     * Skenario: Menampilkan kembali foto yang sudah tersimpan.
     * Jenis: Normal.
     * Kondisi awal: Draft pengguna memiliki foto yang sudah tersimpan.
     * Hasil yang diharapkan: Foto tampil kembali beserta nama, tipe, dan ukuran.
     */
    public function test_tc_07_11_menampilkan_kembali_foto_yang_sudah_tersimpan(): void
    {
        $email = 'dusk-pbi07-'.Str::uuid().'@example.test';
        $password = 'password-uji-tc-07-11';
        $address = 'Desa Uji PBI07 TC11, Kecamatan Uji, Kabupaten Uji';

        $user = new User;
        $user->name = 'Pengguna Uji PBI07 TC11';
        $user->email = $email;
        $user->password = Hash::make($password);
        $user->role = 'masyarakat';
        $user->is_active = true;
        $user->save();

        $draft = new PengajuanPlts;
        $draft->user_id = $user->id;
        $draft->nomor_tiket = 'DUSK-'.Str::upper(Str::random(16));
        $draft->ringkasan_pengajuan = 'Draft lokasi untuk pengujian TC-07-11';
        $draft->status_terkini = 'draft';
        $draft->status_diperbarui_pada = now();
        $draft->save();

        $draft->lokasi()->create([
            'alamat_deskriptif' => $address,
            'latitude' => -6.914744,
            'longitude' => 107.609810,
        ]);

        $photoName = 'foto-tc-07-11.png';
        $photoSize = 1024;

        try {
            $draft->bukti()->create([
                'nama_file_asli' => $photoName,
                'path_file' => "bukti-pengajuan/{$draft->id}/{$photoName}",
                'mime_type' => 'image/png',
                'ukuran_byte' => $photoSize,
                'keterangan' => null,
            ]);

            $this->browse(function (Browser $browser) use ($email, $password, $photoName) {
                $browser->visit('http://127.0.0.1:8000/login')
                    ->type('email', $email)
                    ->type('password', $password)
                    ->press('Masuk')
                    ->visit('http://127.0.0.1:8000/pengajuan/lokasi/bukti')
                    ->assertPathIs('/pengajuan/lokasi/bukti')
                    ->assertSeeIn('[data-evidence-count]', '1')
                    ->assertSeeIn('[data-evidence-count-label]', 'Foto tersimpan')
                    ->assertSeeIn('.evidence-grid', $photoName)
                    ->assertSeeIn('.evidence-grid', 'PNG')
                    ->assertSeeIn('.evidence-grid', '0,0 MB');
            });

            $this->assertSame(1, $draft->bukti()->count());
        } finally {
            $draft->bukti()->delete();
            $draft->lokasi()->delete();
            $draft->delete();
            $user->delete();
        }
    }
}