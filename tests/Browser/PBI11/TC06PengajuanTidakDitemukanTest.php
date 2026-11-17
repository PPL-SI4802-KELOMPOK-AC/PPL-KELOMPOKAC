<?php

namespace Tests\Browser\PBI11;

use Laravel\Dusk\Browser;

class TC06PengajuanTidakDitemukanTest extends PBI11TestCase
{
    /**
     * ID TC: TC-11-06
     * Skenario: Pengguna membuka pengajuan yang tidak ditemukan.
     * Kondisi awal: Pengguna sudah login.
     * Hasil yang diharapkan: Sistem menampilkan halaman 404.
     */

    public function test_nonexistent_submission_returns_404(): void
    {
        $this->browse(function (Browser $browser) {
            $this->login($browser, 'pemohon-pbi11@example.test');
            $browser->assertPathIs('/peta-wilayah');

            $browser->visit('/pengajuan/lokasi/terkirim/999999')
                ->assertDontSee('PLTS-PBI11-001');

            $this->assertSame(
                404,
                $browser->driver->executeScript(
                    'return window.performance.getEntriesByType("navigation")[0].responseStatus'
                )
            );
        });
    }
}
