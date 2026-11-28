<?php

namespace Tests\Browser\PBI24;

use Laravel\Dusk\Browser;

/** Jalankan: php artisan dusk --filter=TC2411Test */
class TC2411Test extends PBI24TestCase
{
    public function test_tc_24_11_tolak_hapus_profil_yang_digunakan(): void
    {
        $profile = $this->accessProfile();
        $this->bind($profile);
        $this->browse(function (Browser $b) use ($profile) {
            $this->admin($b, '/admin/hak-akses/'.$profile->id.'/hapus');
            $b->assertSee('Profil tidak dapat dihapus.')->assertMissing('button.access-danger');
            $this->assertSame(422, $this->request($b, 'DELETE', '/admin/hak-akses/'.$profile->id, $this->profileInput($profile))['status']);
            $this->assertDatabaseHas('staff_access_profiles', ['id' => $profile->id]);
            $this->capture($b, 'TC-24-11-profil-masih-digunakan');
        });
    }
}
