<?php

namespace Tests\Browser\PBI24;

use Illuminate\Support\Facades\DB;
use Laravel\Dusk\Browser;

/** Jalankan: php artisan dusk --filter=TC2407Test */
class TC2407Test extends PBI24TestCase
{
    public function test_tc_24_07_nonaktifkan_profil_dan_pertahankan_riwayat(): void
    {
        $profile = $this->accessProfile();
        $this->bind($profile);
        $users = DB::table('users')->get()->toJson();
        $this->browse(function (Browser $b) use ($profile, $users) {
            $this->admin($b, '/admin/hak-akses/'.$profile->id.'/edit');
            $b->select('is_active', '0')->pause($this->stepPause())->press('Tinjau dampak')->pause($this->stepPause())->waitForText('Profil berhenti memberikan seluruh izinnya');
            $this->capture($b, 'TC-24-07-dampak-penonaktifan');
            $this->confirmProfile($b);
            $this->assertFalse($profile->fresh()->is_active);
            $this->assertSame($users, DB::table('users')->get()->toJson());
            $this->assertDatabaseHas('staff_profile_assignments', ['profile_id' => $profile->id]);
            $b->loginAs($this->fixture['staff'])->visit('/pemerintah/wilayah')->pause($this->stepPause())->assertSee('403');
        });
    }
}
