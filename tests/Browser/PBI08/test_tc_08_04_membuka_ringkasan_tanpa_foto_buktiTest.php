<?php

namespace Tests\Browser\PBI08;

use App\Models\PengajuanPlts;
use App\Models\User;
use App\Services\Pengajuan\LocationCatalog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class test_tc_08_04_membuka_ringkasan_tanpa_foto_buktiTest extends DuskTestCase
{
    /**
     * ID TC: TC-08-04
     * Skenario: Membuka ringkasan tanpa foto bukti.
     * Jenis: Exception.
     * Hasil yang diharapkan: Pengguna diarahkan ke halaman bukti
     * dan diminta mengunggah minimal satu foto.
     */
    public function test_tc_08_04_membuka_ringkasan_tanpa_foto_bukti(): void
    {
        sleep(3); // Jeda demo agar durasi test lebih mudah diamati.
        $email = 'dusk-pbi08-'.Str::uuid().'@example.test';
        $password = 'password-uji-tc-08-04';

        $address = app(LocationCatalog::class)->address([
            'kabupaten' => '3205',
            'kecamatan' => 'Cikelet',
            'desa' => '32.05.30.2010',
            'alamat' => 'Kampung Uji TC04, RT 01',
        ]);

        $user = new User;
        $user->name = 'Pengguna Uji PBI08 TC04';
        $user->email = $email;
        $user->password = Hash::make($password);
        $user->role = 'masyarakat';
        $user->is_active = true;
        $user->save();

        $draft = new PengajuanPlts;
        $draft->user_id = $user->id;
        $draft->nomor_tiket = 'DRAFT-DUSK-'.Str::upper(Str::random(12));
        $draft->ringkasan_pengajuan = 'Draft lokasi lengkap tanpa foto untuk TC-08-04';
        $draft->status_terkini = 'draft';
        $draft->status_diperbarui_pada = now();
        $draft->save();

        $draft->lokasi()->create([
            'alamat_deskriptif' => $address,
            'latitude' => null,
            'longitude' => null,
        ]);

        try {
            $this->browse(function (Browser $browser) use ($email, $password) {
                $browser->visit('http://127.0.0.1:8000/login')
                    ->type('email', $email)
                    ->type('password', $password)
                    ->press('Masuk')
                    ->visit('http://127.0.0.1:8000/pengajuan/lokasi/ringkasan')
                    ->assertPathIs('/pengajuan/lokasi/bukti')
                    ->assertSee('Tunjukkan kondisi di lapangan')
                    ->assertSee('Pengajuan belum lengkap:')
                    ->assertSee('Bukti wajib belum lengkap')
                    ->assertSee('Unggah 1-5 foto JPG, PNG, atau WebP')
                    ->assertSee('0 foto tersimpan. 5 slot tersisa.');
            });

            $this->assertSame('draft', $draft->fresh()->status_terkini);
            $this->assertSame(0, $draft->bukti()->count());
        } finally {
            $draft->bukti()->delete();
            $draft->lokasi()->delete();
            $draft->delete();
            $user->delete();
        }
    }
}