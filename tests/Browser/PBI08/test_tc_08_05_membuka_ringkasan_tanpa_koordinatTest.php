<?php

namespace Tests\Browser\PBI08;

use App\Models\PengajuanPlts;
use App\Models\User;
use App\Services\Pengajuan\LocationCatalog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class test_tc_08_05_membuka_ringkasan_tanpa_koordinatTest extends DuskTestCase
{
    /**
     * ID TC: TC-08-05
     * Skenario: Membuka ringkasan tanpa koordinat.
     * Jenis: Normal.
     * Hasil yang diharapkan: Ringkasan dapat dibuka dan koordinat
     * dinyatakan opsional.
     */
    public function test_tc_08_05_membuka_ringkasan_tanpa_koordinat(): void
    {
        sleep(3); // Jeda demo agar durasi test lebih mudah diamati.
        $email = 'dusk-pbi08-'.Str::uuid().'@example.test';
        $password = 'password-uji-tc-08-05';

        $address = app(LocationCatalog::class)->address([
            'kabupaten' => '3205',
            'kecamatan' => 'Cikelet',
            'desa' => '32.05.30.2010',
            'alamat' => 'Kampung Uji TC05, RT 01',
        ]);

        $user = new User;
        $user->name = 'Pengguna Uji PBI08 TC05';
        $user->email = $email;
        $user->password = Hash::make($password);
        $user->role = 'masyarakat';
        $user->is_active = true;
        $user->save();

        $draft = new PengajuanPlts;
        $draft->user_id = $user->id;
        $draft->nomor_tiket = 'DRAFT-DUSK-'.Str::upper(Str::random(12));
        $draft->ringkasan_pengajuan = 'Draft lengkap tanpa koordinat untuk TC-08-05';
        $draft->status_terkini = 'draft';
        $draft->status_diperbarui_pada = now();
        $draft->save();

        $draft->lokasi()->create([
            'alamat_deskriptif' => $address,
            'latitude' => null,
            'longitude' => null,
        ]);

        try {
            $draft->bukti()->create([
                'nama_file_asli' => 'tc-08-05-foto.png',
                'path_file' => "bukti-pengajuan/{$draft->id}/tc-08-05-foto.png",
                'mime_type' => 'image/png',
                'ukuran_byte' => 1024,
                'keterangan' => null,
            ]);

            $this->browse(function (Browser $browser) use ($email, $password) {
                $browser->visit('http://127.0.0.1:8000/login')
                    ->type('email', $email)
                    ->type('password', $password)
                    ->press('Masuk')
                    ->visit('http://127.0.0.1:8000/pengajuan/lokasi/ringkasan')
                    ->assertPathIs('/pengajuan/lokasi/ringkasan')
                    ->assertSee('Periksa sebelum mengirim')
                    ->assertSee('Draft tersimpan')
                    ->assertSee('Awassagara')
                    ->assertSee('Cikelet / Kabupaten Garut')
                    ->assertSee('Kampung Uji TC05, RT 01')
                    ->assertSee('Tidak diisi (opsional)')
                    ->assertSeeIn('.review-evidence', 'tc-08-05-foto.png');
            });

            $this->assertSame('draft', $draft->fresh()->status_terkini);
            $this->assertNull($draft->lokasi()->first()->latitude);
            $this->assertNull($draft->lokasi()->first()->longitude);
            $this->assertSame(1, $draft->bukti()->count());
        } finally {
            $draft->bukti()->delete();
            $draft->lokasi()->delete();
            $draft->delete();
            $user->delete();
        }
    }
}