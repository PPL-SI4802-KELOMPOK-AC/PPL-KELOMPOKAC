<?php

namespace Tests\Browser\PBI09;

use Laravel\Dusk\Browser;

class TC0903Test extends PBI09TestCase
{
    public function test_tc09_03_lokasi_tidak_valid_ditolak_dan_draft_tetap_tersimpan(): void
    {
        $this->draft->lokasi()->update(['alamat_deskriptif' => 'Alamat tanpa data wilayah']);

        $this->browse(function (Browser $browser) {
            $this->signIn($browser);

            $browser->visit('/pengajuan/lokasi/ringkasan')
                ->assertPathIs('/pengajuan/lokasi')
                ->assertSee('Data lokasi belum lengkap.')
                ->assertSee('Data wilayah');
        });

        $this->assertSame('draft', $this->draft->fresh()->status_terkini);
    }
}
