<?php

namespace Tests\Browser\PBI07;

use App\Models\PengajuanPlts;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class test_tc_07_06_mengunggah_foto_lebih_dari_5mbTest extends DuskTestCase
{
    public function test_tc_07_06_mengunggah_foto_lebih_dari_5mb(): void
    {
        $email = 'dusk-pbi07-'.Str::uuid().'@example.test';
        $password = 'password-uji-tc-07-06';
        $address = 'Desa Uji PBI07 TC06, Kecamatan Uji, Kabupaten Uji';
        $photoName = 'tc-07-06-over-5mb.png';
        $photoPath = __DIR__.DIRECTORY_SEPARATOR.$photoName;
        $fileBytes = (5 * 1024 * 1024) + 1;

        $user = new User;
        $user->name = 'Pengguna Uji PBI07 TC06';
        $user->email = $email;
        $user->password = Hash::make($password);
        $user->role = 'masyarakat';
        $user->is_active = true;
        $user->save();

        $draft = new PengajuanPlts;
        $draft->user_id = $user->id;
        $draft->nomor_tiket = 'DUSK-'.Str::upper(Str::random(16));
        $draft->ringkasan_pengajuan = 'Draft lokasi untuk pengujian TC-07-06';
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
            file_put_contents($photoPath, str_pad($png, $fileBytes, "\0"));

            $this->assertSame($fileBytes, filesize($photoPath));
            $this->assertNotFalse(getimagesize($photoPath));

            $this->browse(function (Browser $browser) use ($email, $password, $photoPath, $photoName) {
                $errorMessage = $photoName.': pilih JPG, PNG, atau WebP berukuran maksimal 5 MB.';

                $browser->visit('http://127.0.0.1:8000/login')
                    ->type('email', $email)
                    ->type('password', $password)
                    ->press('Masuk')
                    ->visit('http://127.0.0.1:8000/pengajuan/lokasi/bukti')
                    ->assertPathIs('/pengajuan/lokasi/bukti')
                    ->attach('#photos', $photoPath)
                    ->waitForText($errorMessage)
                    ->assertSeeIn('[data-evidence-status]', $errorMessage)
                    ->assertDisabled('[data-evidence-submit]');
            });
        } finally {
            $draft->lokasi()->delete();
            $draft->delete();
            $user->delete();

            if (file_exists($photoPath)) {
                unlink($photoPath);
            }
        }
    }
}