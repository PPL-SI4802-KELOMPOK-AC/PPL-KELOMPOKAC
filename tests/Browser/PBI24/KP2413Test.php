<?php

namespace Tests\Browser\PBI24;

use Illuminate\Support\Facades\DB;
use Laravel\Dusk\Browser;

/** Jalankan: php artisan dusk --filter=KP2413Test */
class KP2413Test extends PBI24TestCase
{
    public function test_kp_24_13_tampilan_seluler_dan_keadaan_kosong(): void
    {
        DB::table('staff_profile_assignments')->delete();
        DB::table('staff_profile_permissions')->delete();
        DB::table('staff_access_profiles')->delete();
        $this->browse(function (Browser $b) {
            $this->admin($b);
            $b->assertSee('Belum ada profil hak akses.')->resize(390, 844)->pause($this->stepPause())->pause(400)
                ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
                ->click('[data-drawer-open]')->pause($this->stepPause())->waitFor('#sidebar a[aria-current]')
                ->click('#sidebar [data-drawer-close]')->pause($this->stepPause())->clickLink('Tambah profil')->pause($this->stepPause())->waitForText('Tambah profil hak akses')
                ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
            $this->capture($b, 'KP-24-13-seluler-dan-kosong');
        });
    }
}
