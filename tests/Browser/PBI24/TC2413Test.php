<?php

namespace Tests\Browser\PBI24;

use Laravel\Dusk\Browser;

/** Jalankan: php artisan dusk --filter=TC2413Test */
class TC2413Test extends PBI24TestCase
{
    public function test_tc_24_13_url_dan_tindakan_tanpa_izin_ditolak(): void
    {
        $profile = $this->accessProfile();
        $this->bind($profile);
        $this->browse(function (Browser $b) {
            $b->loginAs($this->fixture['staff'])->visit('/pemerintah/wilayah')->pause($this->stepPause())->waitForTextIn('[data-rows]', 'Mekarjaya')
                ->assertMissing('[data-add]')->assertMissing('.form-panel')->assertSee('Hanya baca');
            $this->assertSame(403, $this->request($b, 'POST', '/pemerintah/wilayah', [])['status']);
            $b->visit('/pemerintah/dashboard')->pause($this->stepPause())->assertSee('403');
            $this->capture($b, 'TC-24-13-tindakan-tanpa-izin');
        });
    }
}
