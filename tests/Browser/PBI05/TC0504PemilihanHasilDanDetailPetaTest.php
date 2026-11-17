<?php

namespace Tests\Browser\PBI05;

use Laravel\Dusk\Browser;

class TC0504PemilihanHasilDanDetailPetaTest extends PBI05BrowserTestCase
{
    /** TC-05-04 - Normal: memilih saran membuka detail dan sorotan area yang sama. */
    public function test_tc_05_04_pilih_hasil_menyorot_peta_dan_membuka_detail(): void
    {
        $this->browse(function (Browser $browser) {
            $this->openMap($browser, self::KEYWORD);
            $this->assertMatchingRows($browser, self::KEYWORD);
            $browser->click('#map-search')
                ->waitFor('#map-search-suggestions button', self::TIMEOUT)
                ->assertAttribute('#map-search', 'aria-expanded', 'true');
            $name = $browser->text('#map-search-suggestions button:first-child strong');
            $firstId = $browser->attribute('[data-map-rows] tr:first-child', 'data-id');
            $browser->click('#map-search-suggestions button:first-child')
                ->waitForTextIn('#map-detail-title', $name, self::TIMEOUT)
                ->waitFor('.selected-area', self::TIMEOUT)
                ->assertVisible('[data-map-detail-panel]')
                ->assertSeeIn('#map-detail-title', $name)
                ->assertVisible('[data-map-rows] tr[data-id="'.$firstId.'"].is-selected')
                ->assertAttribute('#map-search', 'aria-expanded', 'false')
                ->screenshot('tc-05-04-peta-dan-detail-terpilih');
        });
    }
}
