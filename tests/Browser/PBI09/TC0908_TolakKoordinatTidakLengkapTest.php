<?php

namespace Tests\Browser\PBI09;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TC0908_TolakKoordinatTidakLengkapTest extends DuskTestCase
{
    use PBI09DuskSupport;

    public function test_koordinat_tidak_lengkap_ditolak(): void
    {
        $s = $this->makeDraft(['photos' => 1, 'latitude' => -7.6522]);
        try {
            $this->browse(function (Browser $browser) use ($s) {
                $this->signIn($browser, $s['email'], $s['password'])
                    ->visit($this->appUrl('/pengajuan/lokasi/ringkasan'))
                    ->assertPathIs('/pengajuan/lokasi')
                    ->assertSee('Data lokasi belum lengkap.');
            });
            $this->assertSame('draft', $s['draft']->fresh()->status_terkini);
        } finally {
            $this->removeDraft($s);
        }
    }
}
