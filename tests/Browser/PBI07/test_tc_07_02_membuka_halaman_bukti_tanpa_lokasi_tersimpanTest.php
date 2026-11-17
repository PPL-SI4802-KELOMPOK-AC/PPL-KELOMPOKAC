<?php

namespace Tests\Browser\PBI07;

use App\Models\PengajuanPlts;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class test_tc_07_02_membuka_halaman_bukti_tanpa_lokasi_tersimpanTest extends DuskTestCase
{
    /**
     * ID TC: TC-07-02
     * Skenario: Membuka halaman bukti tanpa lokasi tersimpan.
     * Jenis: Exception.
     * Kondisi awal: Pengguna masyarakat memiliki draft tanpa data lokasi tersimpan.
     * Langkah: Pengguna membuka halaman bukti foto.
     * Hasil yang diharapkan: Pengguna diarahkan ke halaman lokasi dan diminta menyimpan data lokasi terlebih dahulu.
     */
    public function test_tc_07_02_membuka_halaman_bukti_tanpa_lokasi_tersimpan(): void
    {
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
                    ->type('password', $password)
                    ->press('Masuk')
                    ->visit('http://127.0.0.1:8000/pengajuan/lokasi/bukti')
                    ->assertPathIs('/pengajuan/lokasi')
                    ->assertSee('Simpan data lokasi terlebih dahulu sebelum mengunggah bukti foto.');
            });
        } finally {
            $draft->delete();
            $user->delete();
        }
    }
}