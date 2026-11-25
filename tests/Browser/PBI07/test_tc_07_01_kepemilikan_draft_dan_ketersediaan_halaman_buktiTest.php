<?php

namespace Tests\Browser\PBI07;

use Laravel\Dusk\Browser;
use App\Models\PengajuanPlts;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\DuskTestCase;

class test_tc_07_01_kepemilikan_draft_dan_ketersediaan_halaman_buktiTest extends DuskTestCase
{
    /**
     * ID: TC-07-01
     * Fokus pengujian: Kepemilikan draft dan ketersediaan halaman bukti.
     * Prasyarat dan data uji: Pengguna masyarakat login; memiliki draft berstatus `draft` dengan lokasi tersimpan.
     * Hasil yang diharapkan: Halaman bukti terbuka dan menampilkan data draft milik pengguna tersebut.
     * Jenis: Normal.
     */
    public function test_tc_07_01_kepemilikan_draft_dan_ketersediaan_halaman_bukti(): void
    {
        sleep(3); // Jeda demo agar durasi test mudah diamati.
        $email = 'dusk-pbi07-'.Str::uuid().'@example.test';
        $password = 'password-uji-tc-07-01';
        $address = 'Desa Uji PBI07, Kecamatan Uji, Kabupaten Uji';

        $user = new User;
        $user->name = 'Pengguna Uji PBI07';
        $user->email = $email;
        $user->password = Hash::make($password);
        $user->role = 'masyarakat';
        $user->is_active = true;
        $user->save();

        $draft = new PengajuanPlts;
        $draft->user_id = $user->id;
        $draft->nomor_tiket = 'DUSK-'.Str::upper(Str::random(16));
        $draft->ringkasan_pengajuan = 'Draft lokasi untuk pengujian TC-07-01';
        $draft->status_terkini = 'draft';
        $draft->status_diperbarui_pada = now();
        $draft->save();

        $draft->lokasi()->create([
            'alamat_deskriptif' => $address,
            'latitude' => -6.914744,
            'longitude' => 107.609810,
        ]);

        try {
            $this->browse(function (Browser $browser) use ($email, $password, $address) {
                $browser->visit('http://127.0.0.1:8000/login')
                    ->type('email', $email)
                        ->pause(500)
                    ->type('password', $password)
                        ->pause(500)
                    ->press('Masuk')
                    ->pause(700)
                    ->waitForLocation('/peta-wilayah')
                    ->visit('http://127.0.0.1:8000/pengajuan/lokasi/bukti')
                    ->pause(900)
                    ->assertPathIs('/pengajuan/lokasi/bukti')
                    ->assertSee('Tunjukkan kondisi di lapangan')
                    ->assertSee('Draft tersimpan')
                    ->assertSee('Draft lokasi tersimpan')
                    ->assertSee($address);
            });
        } finally {
            $draft->lokasi()->delete();
            $draft->delete();
            $user->delete();
        }
    }
}
