<?php

namespace Tests\Browser\PBI24;

use Laravel\Dusk\Browser;

/** Jalankan: php artisan dusk --filter=TC2415Test */
class TC2415Test extends PBI24TestCase
{
    public function test_tc_24_15_penetapan_baru_sebelum_konfirmasi_hapus_tetap_utuh(): void
    {
        $profile = $this->accessProfile();
        $this->browse(function (Browser $first, Browser $second) use ($profile) {
            $this->admin($first, '/admin/hak-akses/'.$profile->id.'/hapus');
            $first->check('confirmed')->pause($this->stepPause());
            $this->admin($second, '/admin/hak-akses/petugas/'.$this->fixture['staff']->id);
            $this->assignViaGui($second, $profile);
            $first->press('Hapus profil')->pause($this->stepPause())->waitForText('Profil atau penetapan petugas sudah berubah.');
            $this->assertDatabaseHas('staff_profile_assignments', ['user_id' => $this->fixture['staff']->id, 'profile_id' => $profile->id]);
            $this->assertDatabaseHas('staff_access_profiles', ['id' => $profile->id]);
            $this->capture($first, 'TC-24-15-penetapan-baru-dipertahankan');
        });
    }
}
