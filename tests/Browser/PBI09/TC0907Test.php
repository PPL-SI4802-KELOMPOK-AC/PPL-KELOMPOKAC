<?php

namespace Tests\Browser\PBI09;

use Laravel\Dusk\Browser;

class TC0907Test extends PBI09TestCase
{
    public function test_tc09_07_lintang_dan_bujur_boleh_dikosongkan(): void
    {
        $this->browse(function (Browser $browser) {
            $this->signIn($browser);

            $browser->assertScript("document.querySelector('#latitude').value", '')
                ->assertScript("document.querySelector('#longitude').value", '')
                ->press('Simpan draft')
                ->assertSee('Draft lokasi berhasil disimpan.')
                ->visit('/pengajuan/lokasi/bukti')
                ->clickLink('Lanjut ke ringkasan')
                ->assertSee('Tidak diisi (opsional)')
                ->check('pernyataan')
                ->press('Kirim pengajuan')
                ->assertSee('Pengajuan berhasil dikirim');
        });

        $this->assertSame('diajukan', $this->draft->fresh()->status_terkini);
        $this->assertNull($this->draft->fresh()->lokasi->latitude);
        $this->assertNull($this->draft->fresh()->lokasi->longitude);
    }
}
