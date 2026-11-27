<?php

namespace Tests\Browser\PBI24;

use Laravel\Dusk\Browser;

/** Jalankan: php artisan dusk --filter=TC2406Test */
class TC2406Test extends PBI24TestCase
{
    public function test_tc_24_06_cabut_izin_pada_sesi_petugas_terbuka(): void
    {
        $profile = $this->accessProfile();
        $this->bind($profile);
        $this->browse(function (Browser $admin, Browser $staff) use ($profile) {
            $staff->loginAs($this->fixture['staff'])->visit('/pemerintah/wilayah')->pause($this->stepPause())->waitForText('Kelola data wilayah');
            $this->admin($admin, '/admin/hak-akses/'.$profile->id.'/edit');
            $admin->uncheck('permissions[]', 'wilayah.view')->pause($this->stepPause())->press('Tinjau dampak')->pause($this->stepPause())->waitForText('1 petugas terkait');
            $this->confirmProfile($admin);
            $staff->refresh()->pause($this->stepPause())->assertSee('403');
            $this->capture($staff, 'TC-24-06-sesi-izin-dicabut');
        });
    }
}
