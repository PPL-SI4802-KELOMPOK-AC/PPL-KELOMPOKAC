<?php

namespace Tests\Browser\PBI09;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TC0904_TolakPengajuanTanpaFotoBuktiTest extends DuskTestCase
{
    use PBI09DuskSupport;

    public function test_pengajuan_tanpa_foto_bukti_ditolak(): void
    {
        $s = $this->makeDraft();
        try {
            $this->browse(function (Browser $browser) use ($s) {
                $this->signIn($browser, $s['email'], $s['password'])
                    ->visit($this->appUrl('/pengajuan/lokasi/ringkasan'))
                    ->pause($this->demoPauseDuration())
                    ->assertPathIs('/pengajuan/lokasi/bukti')
                    ->assertSee('Bukti wajib belum lengkap');
                $this->pauseForDemo($browser);
            });
            $this->assertSame('draft', $s['draft']->fresh()->status_terkini);
        } finally {
            $this->removeDraft($s);
        }
    }
}
