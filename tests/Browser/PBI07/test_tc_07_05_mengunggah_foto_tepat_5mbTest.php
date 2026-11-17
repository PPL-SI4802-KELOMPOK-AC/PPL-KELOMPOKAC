<?php

namespace Tests\Browser\PBI07;

use App\Models\PengajuanPlts;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class test_tc_07_05_mengunggah_foto_tepat_5mbTest extends DuskTestCase
{
    public function test_tc_07_05_mengunggah_foto_tepat_5mb(): void
    {
        $email = 'dusk-pbi07-'.Str::uuid().'@example.test';
        $password = 'password-uji-tc-07-05';
        $address = 'Desa Uji PBI07 TC05, Kecamatan Uji, Kabupaten Uji';
        $photoName = 'tc-07-05-exact-5mb.png';
        $photoPath = __DIR__.DIRECTORY_SEPARATOR.$photoName;
        $maxBytes = 5_000_000;

        $user = new User;
        $user->name = 'Pengguna Uji PBI07 TC05';
        $user->email = $email;
        $user->password = Hash::make($password);
        $user->role = 'masyarakat';
        $user->is_active = true;
        $user->save();

        $draft = new PengajuanPlts;
        $draft->user_id = $user->id;
        $draft->nomor_tiket = 'DUSK-'.Str::upper(Str::random(16));
        $draft->ringkasan_pengajuan = 'Draft lokasi untuk pengujian TC-07-05';
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
            $photoContent = str_pad($png, $maxBytes, "\0");
            file_put_contents($photoPath, $photoContent);

            $this->assertSame($maxBytes, filesize($photoPath));
            $this->assertNotFalse(getimagesize($photoPath));

            $this->browse(function (Browser $browser) use ($email, $password, $photoPath, $photoName) {
                $browser->visit('http://127.0.0.1:8000/login')
                    ->type('email', $email)
                    ->type('password', $password)
                    ->press('Masuk')
                    ->visit('http://127.0.0.1:8000/pengajuan/lokasi/bukti')
                    ->assertPathIs('/pengajuan/lokasi/bukti')
                    ->assertSee('Tunjukkan kondisi di lapangan')
                    ->attach('#photos', $photoPath)
                    ->waitFor('[data-evidence-preview] img[alt="Preview '.$photoName.'"]')
                    ->assertVisible('[data-evidence-preview] img')
                    ->assertSeeIn('[data-evidence-preview]', $photoName)
                    ->assertSeeIn('[data-evidence-preview]', '4.8 MB')
                    ->assertEnabled('[data-evidence-submit]')
                    ->press('Unggah foto')
                    ->waitForText('1 foto bukti berhasil disimpan.', 60)
                    ->assertSee('1 foto bukti berhasil disimpan.');
            });

            $photo = $draft->bukti()->firstOrFail();

            $this->assertSame($photoName, $photo->nama_file_asli);
            $this->assertSame('image/png', $photo->mime_type);
            $this->assertSame($maxBytes, (int) $photo->ukuran_byte);
            $this->assertTrue(Storage::disk('r2')->exists($photo->path_file));
        } finally {
            try {
                Storage::disk('r2')->deleteDirectory('bukti-pengajuan/'.$draft->id);
            } finally {
                $draft->bukti()->delete();
                $draft->lokasi()->delete();
                $draft->delete();
                $user->delete();

                if (file_exists($photoPath)) {
                    unlink($photoPath);
                }
            }
        }
    }
}