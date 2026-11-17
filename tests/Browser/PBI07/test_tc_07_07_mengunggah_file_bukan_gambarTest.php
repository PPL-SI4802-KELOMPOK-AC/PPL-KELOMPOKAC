<?php

namespace Tests\Browser\PBI07;

use App\Models\PengajuanPlts;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class test_tc_07_07_mengunggah_file_bukan_gambarTest extends DuskTestCase
{
    public function test_tc_07_07_mengunggah_file_bukan_gambar(): void
    {
        $email = 'dusk-pbi07-'.Str::uuid().'@example.test';
        $password = 'password-uji-tc-07-07';
        $address = 'Desa Uji PBI07 TC07, Kecamatan Uji, Kabupaten Uji';
        $fileName = 'tc-07-07-bukan-gambar.txt';
        $filePath = __DIR__.DIRECTORY_SEPARATOR.$fileName;
        $errorMessage = 'The foto bukti field must be a file of type: jpg, jpeg, png, webp.';

        $user = new User;
        $user->name = 'Pengguna Uji PBI07 TC07';
        $user->email = $email;
        $user->password = Hash::make($password);
        $user->role = 'masyarakat';
        $user->is_active = true;
        $user->save();

        $draft = new PengajuanPlts;
        $draft->user_id = $user->id;
        $draft->nomor_tiket = 'DUSK-'.Str::upper(Str::random(16));
        $draft->ringkasan_pengajuan = 'Draft lokasi untuk pengujian TC-07-07';
        $draft->status_terkini = 'draft';
        $draft->status_diperbarui_pada = now();
        $draft->save();

        $draft->lokasi()->create([
            'alamat_deskriptif' => $address,
            'latitude' => -6.914744,
            'longitude' => 107.609810,
        ]);

        try {
            file_put_contents($filePath, 'File uji ini bukan gambar.');

            $this->browse(function (Browser $browser) use ($email, $password, $filePath, $errorMessage) {
                $browser->visit('http://127.0.0.1:8000/login')
                    ->type('email', $email)
                    ->type('password', $password)
                    ->press('Masuk')
                    ->visit('http://127.0.0.1:8000/pengajuan/lokasi/bukti')
                    ->assertPathIs('/pengajuan/lokasi/bukti')
                    ->attach('#photos', $filePath);

                // Kirim form untuk memastikan validasi server menolak file .txt.
                $browser->script("
                    document.querySelector('[data-evidence-upload]').submit();
                ");

                $browser->waitForText($errorMessage)
                    ->assertPathIs('/pengajuan/lokasi/bukti')
                    ->assertSee($errorMessage);
            });

            $this->assertSame(0, $draft->bukti()->count());
        } finally {
            $draft->lokasi()->delete();
            $draft->delete();
            $user->delete();

            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
    }
}