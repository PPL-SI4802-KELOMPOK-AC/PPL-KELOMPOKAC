<?php

namespace Tests\Browser\PBI24;

use Illuminate\Support\Facades\DB;
use Laravel\Dusk\Browser;

/** Jalankan: php artisan dusk --filter=TC2404Test */
class TC2404Test extends PBI24TestCase
{
    public function test_tc_24_04_tetapkan_satu_profil_ke_petugas(): void
    {
        $profile = $this->accessProfile();
        $this->browse(function (Browser $b) use ($profile) {
            $this->admin($b, '/admin/hak-akses/petugas/'.$this->fixture['staff']->id);
            $this->assignViaGui($b, $profile);
            $this->assertDatabaseHas('staff_profile_assignments', ['user_id' => $this->fixture['staff']->id, 'profile_id' => $profile->id]);
            $this->assertSame(1, DB::table('staff_profile_assignments')->where('user_id', $this->fixture['staff']->id)->count());
            $this->capture($b, 'TC-24-04-penetapan-profil');
        });
    }
}
