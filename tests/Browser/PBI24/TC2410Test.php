<?php

namespace Tests\Browser\PBI24;

use Laravel\Dusk\Browser;

/** Jalankan: php artisan dusk --filter=TC2410Test */
class TC2410Test extends PBI24TestCase
{
    public function test_tc_24_10_tolak_penetapan_peran_tidak_sesuai(): void
    {
        $profile = $this->accessProfile();
        $this->browse(function (Browser $b) use ($profile) {
            $this->admin($b, '/admin/hak-akses/petugas/'.$this->fixture['society']->id);
            $b->assertSee('Profil hanya dapat ditetapkan kepada akun Pemerintah yang aktif.');
            $this->assertSame(422, $this->request($b, 'POST', '/admin/hak-akses/penetapan', $this->assignmentInput($this->fixture['society'], $profile))['status']);
            $this->assertDatabaseMissing('staff_profile_assignments', ['user_id' => $this->fixture['society']->id]);
            $this->capture($b, 'TC-24-10-peran-tidak-sesuai');
        });
    }
}
