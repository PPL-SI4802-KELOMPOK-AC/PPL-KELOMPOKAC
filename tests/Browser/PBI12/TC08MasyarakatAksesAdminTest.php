<?php

namespace Tests\Browser\PBI12;

use Laravel\Dusk\Browser;

class TC08MasyarakatAksesAdminTest extends PBI12TestCase
{
    /** TC-PBI12-08: Masyarakat tidak boleh membuka halaman Admin. */
    public function test_tc_pbi12_08_masyarakat_dilarang_mengakses_admin(): void
    {
        $this->browse(function (Browser $browser) {
            try {
                $this->clearBrowserSession($browser);

                $this->login(
                    $browser,
                    'masyarakat.uji@example.test',
                    self::PASSWORD_MASYARAKAT
                );

                $browser->visit('/admin/ringkasan')->pause(2000);

                $status = $browser->script(
                    'return performance.getEntriesByType("navigation")[0].responseStatus;'
                )[0] ?? null;

                $this->assertSame(403, $status);
            } finally {
                $browser->pause(3000);
            }
        });
    }
}
