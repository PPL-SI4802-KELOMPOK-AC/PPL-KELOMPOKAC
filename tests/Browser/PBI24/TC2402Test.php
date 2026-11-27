<?php

namespace Tests\Browser\PBI24;

use App\Models\StaffAccessProfile;
use Laravel\Dusk\Browser;

/** Jalankan: php artisan dusk --filter=TC2402Test */
class TC2402Test extends PBI24TestCase
{
    public function test_tc_24_02_tambah_petugas_verifikasi_dan_audit(): void
    {
        $this->browse(function (Browser $b) {
            $this->admin($b);
            $this->createViaGui($b, 'Petugas Verifikasi');
            $profile = StaffAccessProfile::where('name', 'Petugas Verifikasi')->firstOrFail();
            $this->assertSame(['wilayah.view'], $profile->permissionCodes());
            $this->assertDatabaseHas('admin_activity_logs', ['target_id' => $profile->id, 'action' => 'access.profile.created']);
            $this->capture($b, 'TC-24-02-tambah-profil');
        });
    }
}
