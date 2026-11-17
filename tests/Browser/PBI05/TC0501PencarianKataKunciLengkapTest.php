<?php

namespace Tests\Browser\PBI05;

use Laravel\Dusk\Browser;

class TC0501PencarianKataKunciLengkapTest extends PBI05BrowserTestCase
{
    /** TC-05-01 - Normal: kata kunci lengkap menampilkan wilayah yang cocok. */
    public function test_tc_05_01_cari_dengan_kata_kunci_lengkap(): void
    {
        $this->browse(function (Browser $browser) {
            $this->openMap($browser, self::KEYWORD)
                ->assertPathIs('/peta-wilayah')
                ->assertInputValue('q', self::KEYWORD);
            $this->assertGreaterThan(0, $this->resultTotal($browser));
            $this->assertMatchingRows($browser, self::KEYWORD);
            $browser->screenshot('tc-05-01-kata-kunci-lengkap');
        });
    }
}
