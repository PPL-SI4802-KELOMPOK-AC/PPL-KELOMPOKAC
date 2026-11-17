<?php

namespace Tests\Browser\PBI07;

use App\Models\PengajuanPlts;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class test_tc_07_03_memilih_foto_valid_dan_melihat_previewTest extends DuskTestCase
{
    /**
     * ID TC: TC-07-03
     * Skenario: Memilih foto valid dan melihat preview.
     * Jenis: Normal.
     * Kondisi awal: Pengguna masyarakat memiliki draft berstatus draft dan lokasi tersimpan.
     * Langkah: Pilih satu foto PNG valid berukuran di bawah 5 MB.
     * Hasil yang diharapkan: Preview foto tampil dan tombol Unggah foto aktif.
     */
    public function test_tc_07_03_memilih_foto_valid_dan_melihat_preview(): void
    {
        $email = 'dusk-pbi07-'.Str::uuid().'@example.test';
        $password = 'password-uji-tc-07-03';
        $address = 'Desa Uji PBI07 TC03, Kecamatan Uji, Kabupaten Uji';
        $photoName = 'tc-07-03-valid.png';
        $photoPath = __DIR__.DIRECTORY_SEPARATOR.$photoName;

        $user = new User;
        $user->name = 'Pengguna Uji PBI07 TC03';
        $user->email = $email;
        $user->password = Hash::make($password);
        $user->role = 'masyarakat';
        $user->is_active = true;
        $user->save();

        $draft = new PengajuanPlts;
        $draft->user_id = $user->id;
        $draft->nomor_tiket = 'DUSK-'.Str::upper(Str::random(16));
        $draft->ringkasan_pengajuan = 'Draft lokasi untuk pengujian TC-07-03';
        $draft->status_terkini = 'draft';
        $draft->status_diperbarui_pada = now();
        $draft->save();

        $draft->lokasi()->create([
            'alamat_deskriptif' => $address,
            'latitude' => -6.914744,
            'longitude' => 107.609810,
        ]);

        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/u9sAAAAASUVORK5CYII=',
            true
        );

        try {
            file_put_contents($photoPath, $png);

            $this->browse(function (Browser $browser) use ($email, $password, $photoPath, $photoName) {
                $browser->visit('http://127.0.0.1:8000/login')
                    ->type('email', $email)
                    ->type('password', $password)
                    ->press('Masuk')
                    ->visit('http://127.0.0.1:8000/pengajuan/lokasi/bukti')
                    ->assertPathIs('/pengajuan/lokasi/bukti')
                    ->assertSee('Tunjukkan kondisi di lapangan')
                    ->waitFor('#photos')
                    ->attach('#photos', $photoPath)
                    ->waitFor('[data-evidence-preview] img')
                    ->assertVisible('[data-evidence-preview] img')
                    ->assertSeeIn('[data-evidence-preview]', $photoName)
                    ->assertEnabled('[data-evidence-submit]');
            });
        } finally {
            if (file_exists($photoPath)) {
                unlink($photoPath);
            }

            $draft->lokasi()->delete();
            $draft->delete();
            $user->delete();
        }
    }
}