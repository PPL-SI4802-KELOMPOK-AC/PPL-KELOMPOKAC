<?php

namespace Tests\Browser\PBI09;

use Laravel\Dusk\Browser;

class TC0901Test extends PBI09TestCase
{
    public function test_tc09_01_data_lokasi_dan_foto_valid_berhasil_dikirim(): void
    {
        $this->browse(function (Browser $browser) {
            $this->signIn($browser);

            $browser->visit('/pengajuan/lokasi/bukti')
                ->assertSee('bukti-pbi09.png')
                ->clickLink('Lanjut ke ringkasan')
                ->assertPathIs('/pengajuan/lokasi/ringkasan')
                ->assertSee('Data lokasi dan 1 foto bukti sudah tersimpan.')
                ->check('pernyataan')
                ->press('Kirim pengajuan')
                ->assertPathIs('/pengajuan/lokasi/terkirim/'.$this->draft->id)
                ->assertSee('Pengajuan berhasil dikirim');
        });

        $submitted = $this->draft->fresh();
        $this->assertSame('diajukan', $submitted->status_terkini);
        $this->assertStringStartsWith('PLTS-', $submitted->nomor_tiket);
    }
}
