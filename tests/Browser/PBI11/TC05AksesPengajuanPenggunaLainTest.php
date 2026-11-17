<?php

namespace Tests\Browser\PBI11;

use Laravel\Dusk\Browser;

class TC05AksesPengajuanPenggunaLainTest extends PBI11TestCase
{
    /**
     * ID TC: TC-11-05
     * Skenario: Pengguna mencoba mengakses pengajuan milik pengguna lain.
     * Kondisi awal: Pengguna lain sudah login dan pengajuan dimiliki user berbeda.
     * Hasil yang diharapkan: Sistem menolak akses dengan respons 403 atau 404.
     */
    public function test_user_cannot_access_another_users_submission(): void
    {
        $this->browse(function (Browser $browser) {
            try {
                $this->login($browser, 'pemohon-lain-pbi11@example.test');
                $browser->assertPathIs('/peta-wilayah');

                $submissionUrl = route('pengajuan.terkirim', [
                    'pengajuan' => $this->fixture['pengajuan'],
                ]);

                $browser->visit($submissionUrl)->pause(2000)
                    ->assertPathIs(
                        '/pengajuan/lokasi/terkirim/'.$this->fixture['pengajuan']
                    );

                $status = $browser->script(
                    'return performance.getEntriesByType("navigation")[0].responseStatus;'
                )[0] ?? null;

                $this->assertTrue(
                    in_array($status, [403, 404], true),
                    'Access to another user\'s submission should return HTTP 403 or 404.'
                );
            } finally {
                $browser->pause(3000);
            }
        });
    }
}
