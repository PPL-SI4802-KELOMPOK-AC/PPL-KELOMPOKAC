<?php

namespace Tests\Browser\PBI23;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Dusk\Browser;

/** Jalankan: php artisan dusk --filter=TC2302Test */
class TC2302Test extends PBI23TestCase
{
    public function test_tc_23_02_tambah_akun_valid(): void
    {
        $this->browse(function (Browser $b) {
            $this->admin($b, '/admin/pengguna/tambah');
            $this->fillAccount($b);
            $b->press('Tambah pengguna')->pause($this->stepPause())->waitForText('Pengguna baru berhasil ditambahkan.')->assertSee('Warga Demo Baru');
            $user = User::where('email', 'baru@pbi23.example.test')->firstOrFail();
            $this->assertTrue(Hash::check('password-demo-123', $user->password));
            $this->assertDatabaseHas('admin_activity_logs', ['subject_id' => $user->id, 'action' => 'user.created']);
            $changes = DB::table('admin_activity_logs')->where('subject_id', $user->id)->where('action', 'user.created')->value('changes');
            $this->assertStringNotContainsString('password-demo-123', $changes);
            $this->assertStringNotContainsString($user->password, $changes);
            $this->capture($b, 'TC-23-02-tambah-akun');
        });
    }
}
