<?php

namespace Tests\Browser\PBI07;

use App\Models\PengajuanPlts;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class test_tc_07_08_mengunggah_format_gambar_tidak_didukungTest extends DuskTestCase
{
    public function test_tc_07_08_mengunggah_format_gambar_tidak_didukung(): void
    {
        $email = 'dusk-pbi07-'.Str::uuid().'@example.test';
        $password = 'password-uji-tc-07-08';
        $address = 'Desa Uji PBI07 TC08, Kecamatan Uji, Kabupaten Uji';
        $photoName = 'tc-07-08-format-tidak-didukung.gif';
        $photoPath = __DIR__.DIRECTORY_SEPARATOR.$photoName;
        $errorMessage = $photoName.': pilih JPG, PNG, atau WebP berukuran maksimal 5 MB.';

        $user = new User;
        $user->name = 'Pengguna Uji PBI07 TC08';
        $user->email = $email;
        $user->password = Hash::make($password);
        $user->role = 'masyarakat';
        $user->is_active = true;
        $user->save();

        $draft = new PengajuanPlts;
        $draft->user_id = $user->id;
        $draft->nomor_tiket = 'DUSK-'.Str::upper(Str::random(16));
        $draft->ringkasan_pengajuan = 'Draft lokasi untuk pengujian TC-07-08';
        $draft->status_terkini = 'draft';
        $draft->status_diperbarui_pada = now();
        $draft->save();

        $draft->lokasi()->create([
            'alamat_deskriptif' => $address,
            'latitude' => -6.914744,
            'longitude' => 107.609810,
        ]);

        $gif = base64_decode(
            'R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==',
            true
        );

        try {
            file_put_contents($photoPath, $gif);

            $this->assertNotFalse(getimagesize($photoPath));

            $this->browse(function (Browser $browser) use ($email, $password, $photoPath, $errorMessage) {
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

            $this->assertSame(0, $draft->bukti()->count());
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