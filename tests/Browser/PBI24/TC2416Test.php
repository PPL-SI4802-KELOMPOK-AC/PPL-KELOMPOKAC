<?php

namespace Tests\Browser\PBI24;

use Illuminate\Support\Facades\DB;
use Laravel\Dusk\Browser;

/** Jalankan: php artisan dusk --filter=TC2416Test */
class TC2416Test extends PBI24TestCase
{
    public function test_tc_24_16_audit_gagal_rollback_dan_isian_dipertahankan(): void
    {
        DB::unprepared("CREATE TRIGGER pbi24_audit_failure BEFORE INSERT ON admin_activity_logs BEGIN SELECT RAISE(ABORT, 'audit fixture unavailable'); END");
        try {
            $this->browse(function (Browser $b) {
                $this->admin($b, '/admin/hak-akses/tambah');
                $b->typeSlowly('name', 'Profil Audit Gagal', $this->typingPause())->pause($this->stepPause())->typeSlowly('description', 'Isian tetap dipertahankan', $this->typingPause())->pause($this->stepPause())->check('permissions[]', 'wilayah.view')->pause($this->stepPause())->press('Tinjau dampak')->pause($this->stepPause());
                $b->waitForText('Tinjau perubahan profil')->check('confirmed')->pause($this->stepPause())->press('Konfirmasi dan simpan')->pause($this->stepPause())
                    ->waitForText('Perubahan belum dapat disimpan.')->assertInputValue('name', 'Profil Audit Gagal')
                    ->assertInputValue('description', 'Isian tetap dipertahankan');
                $this->assertDatabaseMissing('staff_access_profiles', ['name' => 'Profil Audit Gagal']);
                $this->capture($b, 'TC-24-16-audit-gagal-rollback');
            });
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS pbi24_audit_failure');
        }
    }
}
