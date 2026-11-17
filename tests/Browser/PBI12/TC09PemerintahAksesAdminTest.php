<?php

namespace Tests\Browser\PBI12;

use Laravel\Dusk\Browser;

class TC09PemerintahAksesAdminTest extends PBI12TestCase
{
    /** TC-PBI12-09: Pemerintah tidak boleh membuka halaman Admin. */
    public function test_tc_pbi12_09_pemerintah_dilarang_mengakses_admin(): void
    {
        $this->browse(function (Browser $browser) {
            $this->clearBrowserSession($browser);

            $this->login(
                $browser,
                'sep@mail.com',
                self::PASSWORD_PEMERINTAH
            );

            $browser->visit('/admin/ringkasan');

            $status = $browser->script(
                'return performance.getEntriesByType("navigation")[0].responseStatus;'
            )[0] ?? null;

            $this->assertSame(403, $status);
        });
    }
}
