<?php

namespace Tests\Browser\PBI09;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TC0909_PerbaikiDraftDanKirimUlangTest extends DuskTestCase
{
    use PBI09DuskSupport;

    public function test_draft_diperbaiki_lalu_berhasil_dikirim(): void
    {
        $s = $this->makeDraft(['photos' => 1, 'latitude' => -7.5812]);
        try {
            $this->browse(function (Browser $browser) use ($s) {
                $this->signIn($browser, $s['email'], $s['password'])
                    ->visit($this->appUrl('/pengajuan/lokasi/ringkasan'))
                    ->pause($this->demoPauseDuration())
                    ->assertPathIs('/pengajuan/lokasi')->assertSee('Data lokasi belum lengkap.')
                    ->type('#longitude', '107.6984')->click('button[name="action"][value="save"]')
                    ->waitForText('Draft lokasi berhasil disimpan.')
                    ->pause($this->demoPauseDuration())
                    ->visit($this->appUrl('/pengajuan/lokasi/ringkasan'))
                    ->pause($this->demoPauseDuration())
                    ->assertPathIs('/pengajuan/lokasi/ringkasan')->check('#pernyataan')
                    ->press('Kirim pengajuan')->waitForText('Pengajuan berhasil dikirim')
                    ->pause($this->demoPauseDuration())
                    ->assertPathIs('/pengajuan/lokasi/terkirim/'.$s['draft']->id);
            });
            $this->assertSame('diajukan', $s['draft']->fresh()->status_terkini);
        } finally {
            $this->removeDraft($s);
        }
    }
}
