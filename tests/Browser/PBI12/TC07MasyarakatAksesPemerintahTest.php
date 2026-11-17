<?php

namespace Tests\Browser\PBI12;

use Laravel\Dusk\Browser;

class TC07MasyarakatAksesPemerintahTest extends PBI12TestCase
{
    /** TC-PBI12-07: Masyarakat tidak boleh membuka halaman Pemerintah. */
    public function test_tc_pbi12_07_masyarakat_dilarang_mengakses_pemerintah(): void
    {
        $this->browse(function (Browser $browser) {
            $this->clearBrowserSession($browser);

            $this->login(
                $browser,
                'masyarakat.uji@example.test',
                self::PASSWORD_MASYARAKAT
            );

            $browser->visit('/pemerintah/wilayah');

            $status = $browser->script(
                'return performance.getEntriesByType("navigation")[0].responseStatus;'
            )[0] ?? null;

            $this->assertSame(403, $status);
        });
    }
}
