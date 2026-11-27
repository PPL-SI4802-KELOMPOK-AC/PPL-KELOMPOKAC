<?php

namespace Tests\Browser\PBI24;

use Laravel\Dusk\Browser;

/** Jalankan: php artisan dusk --filter=TC2412Test */
class TC2412Test extends PBI24TestCase
{
    public function test_tc_24_12_tolak_non_admin_dan_eskalasi_sendiri(): void
    {
        $profile = $this->accessProfile();
        $this->browse(function (Browser $b) use ($profile) {
            foreach (['staff', 'society'] as $role) {
                $b->loginAs($this->fixture[$role])->visit('/admin/hak-akses')->pause($this->stepPause())->assertSee('403')->visit('/')->pause($this->stepPause());
                $this->assertSame(403, $this->request($b, 'POST', '/admin/hak-akses/penetapan', $this->assignmentInput($this->fixture['staff'], $profile))['status']);
            }
            $b->visit('/admin/hak-akses')->pause($this->stepPause())->assertSee('403');
            $this->capture($b, 'TC-24-12-akses-admin-ditolak');
        });
    }
}
