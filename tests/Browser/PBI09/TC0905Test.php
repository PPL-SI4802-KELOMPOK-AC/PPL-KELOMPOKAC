<?php

namespace Tests\Browser\PBI09;

use Laravel\Dusk\Browser;

class TC0905Test extends PBI09TestCase
{
    public function test_tc09_05_format_foto_tidak_didukung_dan_ukuran_lebih_dari_lima_mb_ditolak(): void
    {
        $this->browse(function (Browser $browser) {
            $this->signIn($browser);
            $browser->visit('/pengajuan/lokasi/bukti');

            $this->selectBrowserFiles($browser, <<<'JS'
                files.items.add(new File(['bukan foto'], 'catatan.txt', { type: 'text/plain' }));
            JS);
            $browser->assertSeeIn('[data-evidence-status]', 'catatan.txt')
                ->assertSeeIn('[data-evidence-status]', 'pilih JPG, PNG, atau WebP')
                ->assertDisabled('[data-evidence-submit]');

            $this->selectBrowserFiles($browser, <<<'JS'
                files.items.add(new File([new Uint8Array(5 * 1024 * 1024 + 1)], 'terlalu-besar.png', { type: 'image/png' }));
            JS);
            $browser->assertSeeIn('[data-evidence-status]', 'terlalu-besar.png')
                ->assertSeeIn('[data-evidence-status]', 'maksimal 5 MB')
                ->assertDisabled('[data-evidence-submit]');
        });

        $this->assertSame(1, $this->draft->fresh()->bukti()->count());
        $this->assertSame('draft', $this->draft->fresh()->status_terkini);
    }
}
