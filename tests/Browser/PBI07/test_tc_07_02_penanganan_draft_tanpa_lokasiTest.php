<?php

namespace Tests\Browser\PBI07;

use App\Models\PengajuanPlts;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class test_tc_07_02_penanganan_draft_tanpa_lokasiTest extends DuskTestCase
{
    /**
     * ID TC: TC-07-02
     * Skenario: Membuka halaman bukti tanpa lokasi tersimpan.
     * Jenis: Exception.
     * Kondisi awal: Pengguna masyarakat memiliki draft tanpa data lokasi tersimpan.
     * Langkah: Pengguna membuka halaman bukti foto.
     * Hasil yang diharapkan: Pengguna diarahkan ke halaman lokasi dan diminta menyimpan data lokasi terlebih dahulu.
     */
    /**
     * ID: TC-07-02
     * Fokus pengujian: Penanganan draft tanpa lokasi.
     * Prasyarat dan data uji: Pengguna masyarakat login; draft tidak memiliki lokasi tersimpan.
     * Hasil yang diharapkan: Pengguna diarahkan ke halaman lokasi dan mendapat pesan agar menyimpan data lokasi.
     * Jenis: Exception.    */
    public function test_tc_07_02_penanganan_draft_tanpa_lokasi(): void
    {
        sleep(3); // Jeda demo agar durasi test mudah diamati.
        $email = 'dusk-pbi07-'.Str::uuid().'@example.test';
        $password = 'password-uji-tc-07-02';

        $user = new User;
        $user->name = 'Pengguna Uji PBI07 TC02';
        $user->email = $email;
        $user->password = Hash::make($password);
        $user->role = 'masyarakat';
        $user->is_active = true;
        $user->save();

        $draft = new PengajuanPlts;
        $draft->user_id = $user->id;
        $draft->nomor_tiket = 'DUSK-'.Str::upper(Str::random(16));
        $draft->ringkasan_pengajuan = 'Draft tanpa lokasi untuk pengujian TC-07-02';
        $draft->status_terkini = 'draft';
        $draft->status_diperbarui_pada = now();
        $draft->save();

        try {
            $this->browse(function (Browser $browser) use ($email, $password) {
                $browser->visit('http://127.0.0.1:8000/login')
                    ->type('email', $email)
                        ->pause(500)
                    ->type('password', $password)
                        ->pause(500)
                    ->press('Masuk')
                    ->pause(700)
                    ->visit('http://127.0.0.1:8000/pengajuan/lokasi/bukti')
                    ->pause(900)
                    ->assertPathIs('/pengajuan/lokasi')
                    ->assertSee('Simpan data lokasi terlebih dahulu sebelum mengunggah bukti foto.');
            });
        } finally {
            $draft->delete();
            $user->delete();
        }
    }
}