<?php

namespace Tests\Browser\PBI23;

use Laravel\Dusk\Browser;

/** Jalankan: php artisan dusk --filter=TC2301Test */
class TC2301Test extends PBI23TestCase
{
    public function test_tc_23_01_buka_pengguna_dan_peran(): void
    {
        $this->browse(function (Browser $b) {
            $this->admin($b);
            $b->assertSee('Kelola data operasional')->assertSee('Pengguna terdaftar')->assertSee('Peran layanan')
                ->assertSee('Data master')->assertVisible('#q')->assertVisible('#role')->assertVisible('#access')
                ->assertSee('Admin Uji')->assertSee('Masyarakat Uji')->assertSee('Petugas Uji')
                ->assertCount('.admin-user-table tbody tr', 4)
                ->assertSeeIn('.admin-pagination', 'dari 4 pengguna')
                ->assertAttribute('#sidebar a[aria-current]', 'href', url('/admin/pengguna'));
            $this->capture($b, 'TC-23-01-pengguna-dan-peran');
        });
    }
}
