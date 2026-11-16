<?php

namespace Tests\Browser\PBI09;

use Laravel\Dusk\Browser;

class TC0906Test extends PBI09TestCase
{
    public function test_tc09_06_jumlah_foto_melebihi_lima_ditolak_di_browser(): void
    {
        $this->clearEvidence();

        $this->browse(function (Browser $browser) {
            $this->signIn($browser);
            $browser->visit('/pengajuan/lokasi/bukti')
                ->waitFor('#photos');

            $this->selectBrowserFiles($browser, <<<'JS'
                for (let index = 1; index <= 6; index++) {
                    files.items.add(new File(['foto uji'], `foto-${index}.png`, { type: 'image/png' }));
                }
            JS);

            $browser->waitUntil("document.querySelector('[data-evidence-status]').textContent.includes('Pilih maksimal 5 foto lagi')");
            $formState = $browser->driver->executeScript(
                "return { status: document.querySelector('[data-evidence-status]').textContent, disabled: document.querySelector('[data-evidence-submit]').disabled };"
            );
            $this->assertStringContainsString('Pilih maksimal 5 foto lagi', $formState['status']);
            $this->assertTrue($formState['disabled']);
        });

        $this->assertSame(0, $this->draft->fresh()->bukti()->count());
        $this->assertSame('draft', $this->draft->fresh()->status_terkini);
    }
}
