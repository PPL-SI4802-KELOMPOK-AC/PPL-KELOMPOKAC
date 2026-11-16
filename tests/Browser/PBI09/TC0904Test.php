<?php

namespace Tests\Browser\PBI09;

use Laravel\Dusk\Browser;

class TC0904Test extends PBI09TestCase
{
    public function test_tc09_04_tanpa_foto_bukti_pengajuan_tidak_dapat_dilanjutkan(): void
    {
        $this->clearEvidence();

        $this->browse(function (Browser $browser) {
            $this->signIn($browser);

            $browser->visit('/pengajuan/lokasi/ringkasan')
                ->assertPathIs('/pengajuan/lokasi/bukti')
                ->assertSee('Bukti wajib belum lengkap')
                ->assertSee('Pilih minimal satu foto bukti')
                ->assertDisabled('button[disabled][aria-describedby="summary-help"]');
        });

        $this->assertSame('draft', $this->draft->fresh()->status_terkini);
        $this->assertSame(0, $this->draft->fresh()->bukti()->count());
    }
}
