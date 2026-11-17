<?php

namespace Tests\Browser\PBI09;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TC0903_TolakLokasiTidakValidTest extends DuskTestCase
{
    use PBI09DuskSupport;

    public function test_lokasi_tidak_valid_ditolak(): void
    {
        $s = $this->makeDraft(['address' => 'Alamat tanpa wilayah administratif', 'photos' => 1]);
        try {
            $this->browse(function (Browser $browser) use ($s) {
                $this->signIn($browser, $s['email'], $s['password'])
                    ->visit($this->appUrl('/pengajuan/lokasi/ringkasan'))
                    ->assertPathIs('/pengajuan/lokasi')->assertSee('Data lokasi belum lengkap.');
            });
            $this->assertSame('draft', $s['draft']->fresh()->status_terkini);
        } finally {
            $this->removeDraft($s);
        }
    }
}
