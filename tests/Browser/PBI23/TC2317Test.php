<?php

namespace Tests\Browser\PBI23;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Dusk\Browser;

/** Jalankan: php artisan dusk --filter=TC2317Test */
class TC2317Test extends PBI23TestCase
{
    public function test_tc_23_17_audit_gagal_rollback_dan_pemulihan(): void
    {
        $trigger = "CREATE TRIGGER pbi23_audit_failure BEFORE INSERT ON admin_activity_logs BEGIN SELECT RAISE(ABORT, 'audit fixture unavailable'); END";
        $before = User::count();
        $this->browse(function (Browser $b) use ($trigger, $before) {
            $this->admin($b, '/admin/pengguna/tambah');
            $this->fillAccount($b);
            DB::unprepared($trigger);
            try {
                $b->press('Tambah pengguna')->pause($this->stepPause())->waitForText('Database belum tersedia');
                $this->assertSame($before, User::count());
                $this->assertDatabaseMissing('users', ['email' => 'baru@pbi23.example.test']);
                $this->capture($b, 'TC-23-17-tambah-rollback');
            } finally {
                DB::unprepared('DROP TRIGGER IF EXISTS pbi23_audit_failure');
            }
            $b->visit('/admin/pengguna/tambah')->pause($this->stepPause());
            $this->fillAccount($b);
            $b->press('Tambah pengguna')->pause($this->stepPause())->waitForText('Pengguna baru berhasil ditambahkan.');
            $user = User::where('email', 'baru@pbi23.example.test')->firstOrFail();
            $this->assertDatabaseHas('admin_activity_logs', ['subject_id' => $user->id, 'action' => 'user.created']);
            $b->visit('/admin/pengguna/'.$user->id.'/edit')->pause($this->stepPause())->typeSlowly('name', 'Nama Setelah Pulih', $this->typingPause())->pause($this->stepPause());
            $audit = DB::table('admin_activity_logs')->count();
            DB::unprepared($trigger);
            try {
                $b->press('Simpan perubahan')->pause($this->stepPause())->waitForText('Database belum tersedia');
                $this->assertSame('Warga Demo Baru', $user->fresh()->name);
                $this->assertSame($audit, DB::table('admin_activity_logs')->count());
                $this->capture($b, 'TC-23-17-edit-rollback');
            } finally {
                DB::unprepared('DROP TRIGGER IF EXISTS pbi23_audit_failure');
            }
            $b->visit('/admin/pengguna/'.$user->id.'/edit')->pause($this->stepPause())->typeSlowly('name', 'Nama Setelah Pulih', $this->typingPause())->pause($this->stepPause())->press('Simpan perubahan')->pause($this->stepPause())
                ->waitForText('Perubahan pengguna berhasil disimpan.');
            $this->assertSame('Nama Setelah Pulih', $user->fresh()->name);
            $this->assertDatabaseHas('admin_activity_logs', ['subject_id' => $user->id, 'action' => 'user.updated']);
            $this->capture($b, 'TC-23-17-pemulihan-berhasil');
        });
        $this->markTestIncomplete('Rollback serta pemulihan tambah/ubah dan audit sudah diperiksa. Bagian hapus belum tersedia sehingga TC belum lengkap.');
    }
}
