<?php

namespace Tests\Browser\PBI05;

use Laravel\Dusk\Browser;

class TC0503PencarianKataSebagianTest extends PBI05BrowserTestCase
{
    /** TC-05-03 - Normal: bagian kata dicari dalam nama/alamat, bukan hanya awalan. */
    public function test_tc_05_03_cari_dengan_kata_sebagian(): void
    {
        $this->browse(function (Browser $browser) {
            $this->openMap($browser, 'Cing')->assertInputValue('q', 'Cing');
            $this->assertGreaterThan(0, $this->resultTotal($browser));
            $this->assertMatchingRows($browser, 'Cing');
            $browser->screenshot('tc-05-03-kata-sebagian');
        });
    }
}
