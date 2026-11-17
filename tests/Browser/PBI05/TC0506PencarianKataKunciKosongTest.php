<?php

namespace Tests\Browser\PBI05;

use Laravel\Dusk\Browser;

class TC0506PencarianKataKunciKosongTest extends PBI05BrowserTestCase
{
    /** TC-05-06 - Exception: mengosongkan q mengembalikan hasil tanpa filter aktif. */
    public function test_tc_05_06_kata_kunci_kosong_mengembalikan_seluruh_hasil(): void
    {
        $this->browse(function (Browser $browser) {
            $this->openMap($browser);
            $expectedTotal = $this->resultTotal($browser);
            $expectedIds = $this->resultIds($browser);
            $this->assertGreaterThan(0, $expectedTotal, 'Prasyarat: database memuat data wilayah.');
            $browser->type('q', 'xyzabc');
            $this->waitForResults($browser, 'xyzabc');
            $this->assertSame(0, $this->resultTotal($browser));
            $browser->keys('#map-search', ['{control}', 'a'], '{backspace}');
            $this->waitForResults($browser, '');
            $this->assertSame($expectedTotal, $this->resultTotal($browser));
            $this->assertSame($expectedIds, $this->resultIds($browser));
            $browser->assertInputValue('q', '')
                ->assertSelected('kabupaten', '')
                ->assertMissing('[data-map-empty]')
                ->screenshot('tc-05-06-kata-kunci-dikosongkan');
        });
    }
}
