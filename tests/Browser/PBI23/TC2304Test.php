<?php

namespace Tests\Browser\PBI23;

use Illuminate\Support\Facades\Hash;
use Laravel\Dusk\Browser;

/** Jalankan: php artisan dusk --filter=TC2304Test */
class TC2304Test extends PBI23TestCase
{
    public function test_tc_23_04_edit_identitas_peran_dan_password(): void
    {
        $user = $this->fixture['society'];
        $this->browse(function (Browser $b) use ($user) {
            $this->admin($b, '/admin/pengguna/'.$user->id.'/edit');
            $b->typeSlowly('name', 'Admin Hasil Edit', $this->typingPause())->pause($this->stepPause())->typeSlowly('email', 'edit@pbi23.example.test', $this->typingPause())->pause($this->stepPause())->select('role', 'admin')->pause($this->stepPause())
                ->typeSlowly('password', 'password-baru-456', $this->typingPause())->pause($this->stepPause())->typeSlowly('password_confirmation', 'password-baru-456', $this->typingPause())->pause($this->stepPause())
                ->press('Simpan perubahan')->pause($this->stepPause())->waitForText('Perubahan pengguna berhasil disimpan.')->assertSee('Admin Hasil Edit');
            $this->assertSame('admin', $user->fresh()->role);
            $this->assertTrue(Hash::check('password-baru-456', $user->fresh()->password));
            $this->assertDatabaseHas('admin_activity_logs', ['subject_id' => $user->id, 'action' => 'user.updated']);
            $b->logout()->visit('/login')->pause($this->stepPause())->typeSlowly('email', 'edit@pbi23.example.test', $this->typingPause())->pause($this->stepPause())->typeSlowly('password', 'password-khusus-uji', $this->typingPause())->pause($this->stepPause())
                ->press('Masuk')->pause($this->stepPause())->waitForText('Email atau kata sandi salah');
            $this->capture($b, 'TC-23-04-password-lama-ditolak');
            $b->typeSlowly('password', 'password-baru-456', $this->typingPause())->pause($this->stepPause())->press('Masuk')->pause($this->stepPause())->waitForLocation('/admin/ringkasan')
                ->visit('/admin/pengguna')->pause($this->stepPause())->waitForText('Kelola data operasional')->assertSee('Admin Hasil Edit');
            $this->capture($b, 'TC-23-04-akses-peran-baru');
        });
    }
}
