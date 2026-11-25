<?php

namespace Tests\Browser\PBI07;

use App\Models\PengajuanPlts;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class test_tc_07_09_batas_jumlah_foto_dalam_satu_pilihanTest extends DuskTestCase
{
    /**
     * ID: TC-07-09
     * Fokus pengujian: Batas jumlah foto dalam satu pilihan.
     * Prasyarat dan data uji: Enam foto JPG/PNG/WebP valid dipilih sekaligus pada draft tanpa foto.
     * Hasil yang diharapkan: Pilihan ditolak dengan pesan batas maksimal; tombol unggah nonaktif.
     * Jenis: Exception.    */
    public function test_tc_07_09_batas_jumlah_foto_dalam_satu_pilihan(): void
    {
        sleep(3); // Jeda demo agar durasi test mudah diamati.
        $email = 'dusk-pbi07-'.Str::uuid().'@example.test';
        $password = 'password-uji-tc-07-09';
        $address = 'Desa Uji PBI07 TC09, Kecamatan Uji, Kabupaten Uji';

        $photoNames = array_map(
            fn ($number) => "tc-07-09-photo-{$number}.png",
            range(1, 6)
        );

        $photoPaths = array_map(
            fn ($name) => __DIR__.DIRECTORY_SEPARATOR.$name,
            $photoNames
        );

        $user = new User;
        $user->name = 'Pengguna Uji PBI07 TC09';
        $user->email = $email;
        $user->password = Hash::make($password);
        $user->role = 'masyarakat';
        $user->is_active = true;
        $user->save();

        $draft = new PengajuanPlts;
        $draft->user_id = $user->id;
        $draft->nomor_tiket = 'DUSK-'.Str::upper(Str::random(16));
        $draft->ringkasan_pengajuan = 'Draft lokasi untuk pengujian TC-07-09';
        $draft->status_terkini = 'draft';
        $draft->status_diperbarui_pada = now();
        $draft->save();

        $draft->lokasi()->create([
            'alamat_deskriptif' => $address,
            'latitude' => -6.914744,
            'longitude' => 107.609810,
        ]);

        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6l1sAAAAASUVORK5CYII=',
            true
        );

        try {
            foreach ($photoPaths as $photoPath) {
                file_put_contents($photoPath, $png);
            }

            $this->browse(function (Browser $browser) use ($email, $password, $photoPaths) {
                $errorMessage = 'Pilih maksimal 5 foto lagi untuk draft ini.';

                $browser->visit('http://127.0.0.1:8000/login')
                    ->type('email', $email)
                        ->pause(500)
                    ->type('password', $password)
                        ->pause(500)
                    ->press('Masuk')
                    ->pause(700)
                    ->visit('http://127.0.0.1:8000/pengajuan/lokasi/bukti')
                    ->pause(900)
                    ->assertPathIs('/pengajuan/lokasi/bukti')
                    ->attach('#photos', implode("\n", $photoPaths))
                    ->pause(900)
                    ->waitForText($errorMessage)
                    ->assertSeeIn('[data-evidence-status]', $errorMessage)
                    ->assertDisabled('[data-evidence-submit]');
            });

            $this->assertSame(0, $draft->bukti()->count());
        } finally {
            $draft->lokasi()->delete();
            $draft->delete();
            $user->delete();

            foreach ($photoPaths as $photoPath) {
                if (file_exists($photoPath)) {
                    unlink($photoPath);
                }
            }
        }
    }
}