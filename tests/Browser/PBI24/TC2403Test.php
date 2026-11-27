<?php

namespace Tests\Browser\PBI24;

use Laravel\Dusk\Browser;

/** Jalankan: php artisan dusk --filter=TC2403Test */
class TC2403Test extends PBI24TestCase
{
    public function test_tc_24_03_ubah_deskripsi_tambah_dan_cabut_izin(): void
    {
        $profile = $this->accessProfile();
        $this->browse(function (Browser $b) use ($profile) {
            $this->admin($b, '/admin/hak-akses/'.$profile->id.'/edit');
            $b->typeSlowly('description', 'Tugas dashboard terbaru', $this->typingPause())->pause($this->stepPause())->uncheck('permissions[]', 'wilayah.view')->pause($this->stepPause())->check('permissions[]', 'dashboard.view')->pause($this->stepPause())->press('Tinjau dampak')->pause($this->stepPause())
                ->waitForText('Tinjau perubahan profil')->assertSee('Izin sebelumnya')->assertSee('Izin setelah disimpan');
            $this->confirmProfile($b);
            $this->assertSame(['dashboard.view'], $profile->permissionCodes());
            $this->assertSame('Tugas dashboard terbaru', $profile->fresh()->description);
            $this->capture($b, 'TC-24-03-ubah-izin');
        });
    }
}
