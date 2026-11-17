<?php

namespace Tests\Browser\PBI05;

use Laravel\Dusk\Browser;

class TC0502PencarianHurufBesarKecilTest extends PBI05BrowserTestCase
{
    /** TC-05-02 - Normal: huruf kecil menghasilkan total dan daftar ID yang sama. */
    public function test_tc_05_02_pencarian_tidak_membedakan_huruf_besar_kecil(): void
    {
        $this->browse(function (Browser $browser) {
            $this->openMap($browser, self::KEYWORD);
            $this->assertMatchingRows($browser, self::KEYWORD);
            $expectedTotal = $this->resultTotal($browser);
            $expectedIds = $this->resultIds($browser);
            $this->openMap($browser, 'cingambul')->assertInputValue('q', 'cingambul');
            $this->assertSame($expectedTotal, $this->resultTotal($browser));
            $this->assertSame($expectedIds, $this->resultIds($browser));
            $this->assertMatchingRows($browser, 'cingambul');
            $browser->screenshot('tc-05-02-huruf-kecil');
        });
    }
}
