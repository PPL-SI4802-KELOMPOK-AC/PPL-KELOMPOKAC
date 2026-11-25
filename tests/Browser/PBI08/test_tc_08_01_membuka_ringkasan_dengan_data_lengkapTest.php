<?php

namespace Tests\Browser\PBI08;

use App\Models\PengajuanPlts;
use App\Models\User;
use App\Services\Pengajuan\LocationCatalog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class test_tc_08_01_membuka_ringkasan_dengan_data_lengkapTest extends DuskTestCase
{
    /**
     * ID TC: TC-08-01
     * Skenario: Membuka ringkasan dengan data lengkap.
     * Jenis: Normal.
     * Hasil yang diharapkan: Ringkasan menampilkan lokasi, foto bukti,
     * dan identitas akun; status pengajuan tetap draft.
     */
    public function test_tc_08_01_membuka_ringkasan_dengan_data_lengkap(): void
    {
        sleep(3); // Jeda demo agar durasi test lebih mudah diamati.
        $email = 'dusk-pbi08-'.Str::uuid().'@example.test';
        $password = 'password-uji-tc-08-01';
        $userName = 'Pengguna Uji PBI08 TC01';

        $address = app(LocationCatalog::class)->address([
            'kabupaten' => '3205',
            'kecamatan' => 'Cikelet',
            'desa' => '32.05.30.2010',
            'alamat' => 'Kampung Uji, RT 01',
        ]);

        $user = new User;
        $user->name = $userName;
        $user->email = $email;
        $user->password = Hash::make($password);
        $user->role = 'masyarakat';
        $user->is_active = true;
        $user->save();

        $draft = new PengajuanPlts;
        $draft->user_id = $user->id;
        $draft->nomor_tiket = 'DRAFT-DUSK-'.Str::upper(Str::random(12));
        $draft->ringkasan_pengajuan = 'Ringkasan draft PBI08 TC01';
        $draft->status_terkini = 'draft';
        $draft->status_diperbarui_pada = now();
        $draft->save();

        $draft->lokasi()->create([
            'alamat_deskriptif' => $address,
            'latitude' => -7.6522,
            'longitude' => 107.6834,
        ]);

        try {
            foreach ([1, 2] as $number) {
                $draft->bukti()->create([
                    'nama_file_asli' => "tc-08-01-foto-{$number}.png",
                    'path_file' => "bukti-pengajuan/{$draft->id}/tc-08-01-foto-{$number}.png",
                    'mime_type' => 'image/png',
                    'ukuran_byte' => 1024 * $number,
                    'keterangan' => null,
                ]);
            }

            $this->browse(function (Browser $browser) use (
                $email,
                $password,
                $userName
            ) {
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
                    ->assertSee('Kampung Uji, RT 01')
                    ->assertSeeIn('.review-evidence', 'tc-08-01-foto-1.png')
                    ->assertSeeIn('.review-evidence', 'tc-08-01-foto-2.png')
                    ->assertSee($userName)
                    ->assertSee($email);
            });

            $this->assertSame('draft', $draft->fresh()->status_terkini);
            $this->assertStringStartsWith('DRAFT-', $draft->fresh()->nomor_tiket);
            $this->assertSame(2, $draft->bukti()->count());
        } finally {
            $draft->bukti()->delete();
            $draft->lokasi()->delete();
            $draft->delete();
            $user->delete();
        }
    }
}