<?php

namespace Tests\Browser\PBI07;

use App\Models\PengajuanPlts;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class test_tc_07_04_mengunggah_beberapa_foto_validTest extends DuskTestCase
{
    /**
     * ID TC: TC-07-04
     * Skenario: Mengunggah beberapa foto valid.
     * Jenis: Normal.
     * Kondisi awal: Draft masyarakat memiliki lokasi tersimpan dan belum memiliki foto.
     * Langkah: Pilih dan unggah dua foto PNG yang valid.
     * Hasil yang diharapkan: Status unggah tampil, tombol nonaktif saat proses,
     * foto tersimpan di R2, dan metadata tercatat untuk draft.
     */
    public function test_tc_07_04_mengunggah_beberapa_foto_valid(): void
    {
        $email = 'dusk-pbi07-'.Str::uuid().'@example.test';
        $password = 'password-uji-tc-07-04';
        $address = 'Desa Uji PBI07 TC04, Kecamatan Uji, Kabupaten Uji';
        $photoNames = ['tc-07-04-a.png', 'tc-07-04-b.png'];
        $photoPaths = array_map(
            fn ($name) => __DIR__.DIRECTORY_SEPARATOR.$name,
            $photoNames
        );

        $user = new User;
        $user->name = 'Pengguna Uji PBI07 TC04';
        $user->email = $email;
        $user->password = Hash::make($password);
        $user->role = 'masyarakat';
        $user->is_active = true;
        $user->save();

        $draft = new PengajuanPlts;
        $draft->user_id = $user->id;
        $draft->nomor_tiket = 'DUSK-'.Str::upper(Str::random(16));
        $draft->ringkasan_pengajuan = 'Draft lokasi untuk pengujian TC-07-04';
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

            $this->browse(function (Browser $browser) use ($email, $password, $photoNames, $photoPaths) {
                $browser->visit('http://127.0.0.1:8000/login')
                    ->type('email', $email)
                    ->type('password', $password)
                    ->press('Masuk')
                    ->visit('http://127.0.0.1:8000/pengajuan/lokasi/bukti')
                    ->assertPathIs('/pengajuan/lokasi/bukti')
                    ->assertSee('Tunjukkan kondisi di lapangan')
                    ->attach('#photos', implode("\n", $photoPaths))
                    ->waitFor('[data-evidence-preview] img[alt="Preview '.$photoNames[0].'"]')
                    ->waitFor('[data-evidence-preview] img[alt="Preview '.$photoNames[1].'"]')
                    ->assertSeeIn('[data-evidence-preview]', $photoNames[0])
                    ->assertSeeIn('[data-evidence-preview]', $photoNames[1])
                    ->assertEnabled('[data-evidence-submit]');

                // Cegah submit pertama agar keadaan loading bisa diperiksa.
                $browser->script("
                    const form = document.querySelector('[data-evidence-upload]');

                    form.addEventListener('submit', event => {
                        event.preventDefault();
                    }, { once: true });

                    document.querySelector('[data-evidence-submit]').click();
                ");

                $browser->assertDisabled('[data-evidence-submit]')
                    ->assertSeeIn('[data-evidence-submit]', 'Mengunggah foto…')
                    ->assertSeeIn(
                        '[data-evidence-status]',
                        'Foto sedang diunggah. Jangan tutup halaman ini.'
                    );

                // Kirim unggahan sungguhan setelah status loading diperiksa.
                $browser->script("
                    document.querySelector('[data-evidence-upload]').submit();
                ");

                $browser->waitForText('2 foto bukti berhasil disimpan.')
                    ->assertSee('2 foto bukti berhasil disimpan.');
            });

            $photos = $draft->bukti()->orderBy('nama_file_asli')->get();

            $this->assertCount(2, $photos);
            $this->assertSame($photoNames, $photos->pluck('nama_file_asli')->all());

            foreach ($photos as $photo) {
                $this->assertSame('image/png', $photo->mime_type);
                $this->assertGreaterThan(0, $photo->ukuran_byte);
                $this->assertTrue(Storage::disk('r2')->exists($photo->path_file));
            }
        } finally {
            try {
                Storage::disk('r2')->deleteDirectory('bukti-pengajuan/'.$draft->id);
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
}