<?php

namespace Tests\Browser\PBI23;

use Laravel\Dusk\Browser;

/** Jalankan: php artisan dusk --filter=TC2312Test */
class TC2312Test extends PBI23TestCase
{
    public function test_tc_23_12_lindungi_akun_admin_sendiri(): void
    {
        $admin = $this->fixture['admin'];
        $this->browse(function (Browser $b) use ($admin) {
            foreach (['nonaktif' => ['is_active' => '0'], 'turun-peran' => ['role' => 'masyarakat']] as $kind => $changes) {
                $this->admin($b, '/admin/pengguna/'.$admin->id.'/edit');
                $b->assertSee('Ini akun yang sedang Anda gunakan.');
                $result = $this->request($b, 'PUT', '/admin/pengguna/'.$admin->id,
                    $this->accountPayload($changes + ['name' => $admin->name, 'email' => $admin->email, 'role' => 'admin', 'password' => '', 'password_confirmation' => '']));
                $this->assertSame(422, $result['status']);
                $this->assertArrayHasKey('role', $result['body']['errors']);
                $this->assertTrue($admin->fresh()->is_active);
                $this->assertSame('admin', $admin->fresh()->role);
                $this->capture($b, 'TC-23-12-'.$kind.'-ditolak');
            }
        });
        $this->markTestIncomplete('Penonaktifan dan penurunan peran sendiri sudah diperiksa. Bagian DELETE akun sendiri belum dapat diuji karena endpoint hapus akun belum tersedia.');
    }
}
