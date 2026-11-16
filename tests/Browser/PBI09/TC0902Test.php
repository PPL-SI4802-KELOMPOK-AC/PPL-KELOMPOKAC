<?php

namespace Tests\Browser\PBI09;

use Laravel\Dusk\Browser;

class TC0902Test extends PBI09TestCase
{
    public function test_tc09_02_data_utama_kosong_ditolak_dan_draft_tetap_tersimpan(): void
    {
        $this->draft->ringkasan_pengajuan = '';
        $this->draft->save();
        $ticket = $this->draft->nomor_tiket;

        $this->browse(function (Browser $browser) {
            $this->signIn($browser);

            $browser->visit('/pengajuan/lokasi/ringkasan')
                ->assertPathIs('/pengajuan/lokasi')
                ->assertSee('Data utama pengajuan belum lengkap.')
                ->assertPresent('[data-location-form]');
        });

        $this->assertSame('draft', $this->draft->fresh()->status_terkini);
        $this->assertSame($ticket, $this->draft->fresh()->nomor_tiket);
    }
}
