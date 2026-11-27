<?php

namespace Tests\Browser\PBI23;

use App\Models\User;
use Laravel\Dusk\Browser;

/** Jalankan: php artisan dusk --filter=TC2313Test */
class TC2313Test extends PBI23TestCase
{
    public function test_tc_23_13_lindungi_admin_aktif_antar_sesi(): void
    {
        $first = $this->fixture['admin'];
        $second = new User(['name' => 'Admin Kedua', 'email' => 'kedua@pbi23.example.test', 'password' => 'password-demo-123']);
        $second->forceFill(['role' => 'admin', 'is_active' => true])->save();
        $this->browse(function (Browser $a, Browser $b) use ($first, $second) {
            $this->admin($a, '/admin/pengguna/'.$second->id.'/edit');
            $b->loginAs($second)->visit('/admin/pengguna/'.$first->id.'/edit')->pause($this->stepPause())->waitForText('Edit pengguna');
            $a->select('is_active', '0')->pause($this->stepPause())->press('Simpan perubahan')->pause($this->stepPause())->waitForText('Perubahan pengguna berhasil disimpan.');
            // The second Admin still has an open form, but has now lost authority.
            $result = $this->request($b, 'PUT', '/admin/pengguna/'.$first->id,
                $this->accountPayload(['name' => $first->name, 'email' => $first->email, 'role' => 'admin', 'is_active' => '0', 'password' => '', 'password_confirmation' => '']));
            $this->assertSame(401, $result['status']);
            $this->assertSame(1, User::where('role', 'admin')->where('is_active', true)->count());
            $this->assertTrue($first->fresh()->is_active);
            $b->visit('/admin/pengguna')->pause($this->stepPause())->waitForLocation('/login');
            $this->capture($b, 'TC-23-13-admin-kehilangan-kewenangan');
        });
        $this->markTestIncomplete('Dua sesi dan formulir lama diperiksa. SQL yang benar-benar overlap serta penghapusan Admin belum diuji; SQLite/PHP server lokal menserialkan request dan endpoint DELETE belum tersedia.');
    }
}
