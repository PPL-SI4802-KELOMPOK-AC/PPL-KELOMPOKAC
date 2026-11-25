<?php

namespace Tests\Browser\PBI08;

use App\Models\PengajuanPlts;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class test_tc_08_03_membuka_ringkasan_tanpa_lokasi_lengkapTest extends DuskTestCase
{
    /**
     * ID TC: TC-08-03
     * Skenario: Membuka ringkasan tanpa lokasi lengkap.
     * Jenis: Exception.
     * Hasil yang diharapkan: Pengguna diarahkan ke halaman lokasi
     * dan diminta melengkapi data.
     */
    public function test_tc_08_03_membuka_ringkasan_tanpa_lokasi_lengkap(): void
    {
        sleep(3); // Jeda demo agar durasi test lebih mudah diamati.
        $email = 'dusk-pbi08-'.Str::uuid().'@example.test';
        $password = 'password-uji-tc-08-03';

        $user = new User;
        $user->name = 'Pengguna Uji PBI08 TC03';
        $user->email = $email;
        $user->password = Hash::make($password);
        $user->role = 'masyarakat';
        $user->is_active = true;
        $user->save();

        $draft = new PengajuanPlts;
        $draft->user_id = $user->id;
        $draft->nomor_tiket = 'DRAFT-DUSK-'.Str::upper(Str::random(12));
        $draft->ringkasan_pengajuan = 'Draft tanpa lokasi untuk TC-08-03';
        $draft->status_terkini = 'draft';
        $draft->status_diperbarui_pada = now();
        $draft->save();

        try {
            $this->browse(function (Browser $browser) use ($email, $password) {
                $browser->visit('http://127.0.0.1:8000/login')
                    ->type('email', $email)
                    ->type('password', $password)
                    ->press('Masuk')
                    ->visit('http://127.0.0.1:8000/pengajuan/lokasi/ringkasan')
                    ->assertPathIs('/pengajuan/lokasi')
                    ->assertSee('Tentukan lokasi pengajuan')
                    ->assertSee('Pengajuan belum lengkap:')
                    ->assertSee('Data lokasi belum lengkap.');
            });

            $this->assertSame('draft', $draft->fresh()->status_terkini);
            $this->assertNull($draft->lokasi()->first());
        } finally {
            $draft->bukti()->delete();
            $draft->lokasi()->delete();
            $draft->delete();
            $user->delete();
        }
    }
}