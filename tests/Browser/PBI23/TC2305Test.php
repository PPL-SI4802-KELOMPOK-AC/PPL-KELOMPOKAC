<?php

namespace Tests\Browser\PBI23;

use Laravel\Dusk\Browser;

/** Jalankan: php artisan dusk --filter=TC2305Test */
class TC2305Test extends PBI23TestCase
{
    public function test_tc_23_05_edit_tanpa_mengganti_password(): void
    {
        $user = $this->fixture['society'];
        $hash = $user->password;
        $this->browse(function (Browser $b) use ($user, $hash) {
            $this->admin($b, '/admin/pengguna/'.$user->id.'/edit');
            $b->assertInputValue('password', '')->assertInputValue('password_confirmation', '')
                ->typeSlowly('name', 'Warga Password Tetap', $this->typingPause())->pause($this->stepPause())->press('Simpan perubahan')->pause($this->stepPause())->waitForText('Perubahan pengguna berhasil disimpan.');
            $this->assertSame($hash, $user->fresh()->password);
            $b->logout()->visit('/login')->pause($this->stepPause())->typeSlowly('email', $user->email, $this->typingPause())->pause($this->stepPause())->typeSlowly('password', 'password-khusus-uji', $this->typingPause())->pause($this->stepPause())
                ->press('Masuk')->pause($this->stepPause())->waitForLocation('/peta-wilayah')->assertSee('Warga Password Tetap');
            $this->capture($b, 'TC-23-05-password-lama-tetap');
        });
    }
}
