<?php

namespace Tests\Browser\PBI07;

use App\Models\PengajuanPlts;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class test_tc_07_10_melebihi_batas_lima_foto_pada_satu_draftTest extends DuskTestCase
{
    /**
     * ID TC: TC-07-10
     * Skenario: Melebihi batas lima foto pada satu draft.
     * Jenis: Exception.
     * Kondisi awal: Draft sudah memiliki empat foto.
     * Langkah: Pengguna memilih dua foto baru.
     * Hasil yang diharapkan: Upload ditolak karena jumlah keseluruhan
     * akan melebihi lima foto.
     */
    public function test_tc_07_10_melebihi_batas_lima_foto_pada_satu_draft(): void
    {
        $email = 'dusk-pbi07-'.Str::uuid().'@example.test';
        $password = 'password-uji-tc-07-10';
        $address = 'Desa Uji PBI07 TC10, Kecamatan Uji, Kabupaten Uji';

        $photoPaths = [
            __DIR__.DIRECTORY_SEPARATOR.'tc-07-10-foto-baru-1.png',
            __DIR__.DIRECTORY_SEPARATOR.'tc-07-10-foto-baru-2.png',
        ];

        $user = new User;
        $user->name = 'Pengguna Uji PBI07 TC10';
        $user->email = $email;
        $user->password = Hash::make($password);
        $user->role = 'masyarakat';
        $user->is_active = true;
        $user->save();

        $draft = new PengajuanPlts;
        $draft->user_id = $user->id;
        $draft->nomor_tiket = 'DUSK-'.Str::upper(Str::random(16));
        $draft->ringkasan_pengajuan = 'Draft lokasi untuk pengujian TC-07-10';
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
            // Siapkan empat foto yang sudah tercatat pada draft.
            for ($number = 1; $number <= 4; $number++) {
                $draft->bukti()->create([
                    'nama_file_asli' => "foto-lama-{$number}.png",
                    'path_file' => "bukti-pengajuan/{$draft->id}/foto-lama-{$number}.png",
                    'mime_type' => 'image/png',
                    'ukuran_byte' => strlen($png),
                    'keterangan' => null,
                ]);
            }

            // Siapkan dua foto valid yang akan dipilih pengguna.
            foreach ($photoPaths as $photoPath) {
                file_put_contents($photoPath, $png);
            }

            $this->browse(function (Browser $browser) use ($email, $password, $photoPaths) {
                $errorMessage = 'Pilih maksimal 1 foto lagi untuk draft ini.';

                $browser->visit('http://127.0.0.1:8000/login')
                    ->type('email', $email)
                    ->type('password', $password)
                    ->press('Masuk')
                    ->visit('http://127.0.0.1:8000/pengajuan/lokasi/bukti')
                    ->assertPathIs('/pengajuan/lokasi/bukti')
                    ->assertSeeIn('[data-evidence-status]', '4 foto tersimpan. 1 slot tersisa.')
                    ->assertSeeIn('[data-evidence-count]', '4')
                    ->attach('#photos', implode("\n", $photoPaths))
                    ->waitForText($errorMessage)
                    ->assertSeeIn('[data-evidence-status]', $errorMessage)
                    ->assertDisabled('[data-evidence-submit]');
            });

            // Memastikan pemilihan dua foto baru tidak menambah metadata.
            $this->assertSame(4, $draft->bukti()->count());
        } finally {
            $draft->bukti()->delete();
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