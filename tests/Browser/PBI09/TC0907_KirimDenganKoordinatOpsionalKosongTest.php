<?php

namespace Tests\Browser\PBI09;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TC0907_KirimDenganKoordinatOpsionalKosongTest extends DuskTestCase
{
    use PBI09DuskSupport;

    public function test_pengajuan_dapat_dikirim_tanpa_koordinat(): void
    {
        $s = $this->makeDraft(['photos' => 1]);
        try {
            $this->browse(function (Browser $browser) use ($s) {
                $this->signIn($browser, $s['email'], $s['password'])
                    ->visit($this->appUrl('/pengajuan/lokasi/ringkasan'))
                    ->pause($this->demoPauseDuration())
                    ->assertSee('Tidak diisi (opsional)')->check('#pernyataan')->press('Kirim pengajuan')
                    ->waitForText('Pengajuan berhasil dikirim')
                    ->pause($this->demoPauseDuration())
                    ->assertPathIs('/pengajuan/lokasi/terkirim/'.$s['draft']->id);
            });
            $this->assertSame('diajukan', $s['draft']->fresh()->status_terkini);
        } finally {
            $this->removeDraft($s);
        }
    }
}
