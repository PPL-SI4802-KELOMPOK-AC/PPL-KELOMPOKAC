<?php

namespace Tests\Browser\PBI05;

use Laravel\Dusk\Browser;

class TC0507PencarianKarakterKhususTest extends PBI05BrowserTestCase
{
    /** TC-05-07 - Exception: karakter khusus diperlakukan sebagai teks pencarian. */
    public function test_tc_05_07_karakter_khusus_tidak_membuka_semua_data(): void
    {
        $this->browse(function (Browser $browser) {
            $this->openMap($browser);
            $this->assertGreaterThan(0, $this->resultTotal($browser));
            $query = "' OR 1=1 --";
            $browser->type('q', $query);
            $this->waitForResults($browser, $query);
            $this->assertSame(0, $this->resultTotal($browser));
            $this->assertSame([], $this->resultIds($browser));
            $browser->assertPathIs('/peta-wilayah')
                ->assertInputValue('q', $query)
                ->assertVisible('[data-map-empty]')
                ->assertSeeIn('[data-map-empty]', 'Tidak ada wilayah yang sesuai pencarian atau filter.')
                ->assertMissing('[data-map-data-retry]')
                ->screenshot('tc-05-07-karakter-khusus-aman');
        });
    }
}
