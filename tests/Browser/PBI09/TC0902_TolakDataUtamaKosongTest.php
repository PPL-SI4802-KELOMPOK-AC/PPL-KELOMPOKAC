<?php

namespace Tests\Browser\PBI09;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TC0902_TolakDataUtamaKosongTest extends DuskTestCase
{
    use PBI09DuskSupport;

    public function test_data_utama_kosong_ditolak_dan_tetap_draft(): void
    {
        $s = $this->makeDraft(['summary' => '', 'photos' => 1]);
        try {
            $this->browse(function (Browser $browser) use ($s) {
                $this->signIn($browser, $s['email'], $s['password'])
                    ->visit($this->appUrl('/pengajuan/lokasi/ringkasan'))
                    ->assertPathIs('/pengajuan/lokasi')->assertSee('Data utama pengajuan belum lengkap.');
            });
            $this->assertSame('draft', $s['draft']->fresh()->status_terkini);
        } finally {
            $this->removeDraft($s);
        }
    }
}
