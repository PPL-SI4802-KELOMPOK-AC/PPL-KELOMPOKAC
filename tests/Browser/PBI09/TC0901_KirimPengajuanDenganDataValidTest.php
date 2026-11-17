<?php

namespace Tests\Browser\PBI09;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TC0901_KirimPengajuanDenganDataValidTest extends DuskTestCase
{
    use PBI09DuskSupport;

    public function test_pengajuan_data_valid_berhasil_dikirim(): void
    {
        $s = $this->makeDraft(['photos' => 1, 'latitude' => -7.6522, 'longitude' => 107.6834]);
        try {
            $this->browse(function (Browser $browser) use ($s) {
                $this->signIn($browser, $s['email'], $s['password'])
                    ->visit($this->appUrl('/pengajuan/lokasi/ringkasan'))
                    ->assertPathIs('/pengajuan/lokasi/ringkasan')->check('#pernyataan')
                    ->press('Kirim pengajuan')->waitForText('Pengajuan berhasil dikirim')
                    ->assertPathIs('/pengajuan/lokasi/terkirim/'.$s['draft']->id)
                    ->assertSee('Status pengajuan:')->assertSee('Diajukan');
            });
            $this->assertSame('diajukan', $s['draft']->fresh()->status_terkini);
            $this->assertStringStartsWith('PLTS-', $s['draft']->fresh()->nomor_tiket);
        } finally {
            $this->removeDraft($s);
        }
    }
}
