<?php

namespace Tests\Browser\PBI24;

use App\Models\StaffAccessProfile;
use Laravel\Dusk\Browser;

/** Jalankan: php artisan dusk --filter=TC2408Test */
class TC2408Test extends PBI24TestCase
{
    public function test_tc_24_08_hapus_profil_yang_belum_pernah_ditetapkan(): void
    {
        $this->browse(function (Browser $b) {
            $this->admin($b);
            $this->createViaGui($b, 'Profil Hapus Uji');
            $profile = StaffAccessProfile::where('name', 'Profil Hapus Uji')->firstOrFail();
            $b->visit('/admin/hak-akses/'.$profile->id.'/hapus')->pause($this->stepPause())->check('confirmed')->pause($this->stepPause())->press('Hapus profil')->pause($this->stepPause())
                ->waitForLocation('/admin/hak-akses')->assertSee('Profil hak akses berhasil dihapus.');
            $this->assertDatabaseMissing('staff_access_profiles', ['id' => $profile->id]);
            $this->assertDatabaseHas('admin_activity_logs', ['target_id' => $profile->id, 'subject_name' => 'Profil Hapus Uji', 'action' => 'access.profile.deleted']);
            $this->capture($b, 'TC-24-08-hapus-profil');
        });
    }
}
