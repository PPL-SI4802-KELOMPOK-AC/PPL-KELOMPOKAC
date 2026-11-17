<?php

namespace Tests\Browser\PBI11;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Dusk\Browser;

class TC08PemerintahAksesPengajuanTest extends PBI11TestCase
{
    /**
     * ID TC: TC-11-08
     * Skenario: Pengguna dengan role Pemerintah mengakses status pengajuan masyarakat.
     * Kondisi awal: Pengguna sudah login sebagai Pemerintah.
     * Hasil yang diharapkan: Sistem menolak akses dengan HTTP 403.
     */

    public function test_government_user_cannot_access_submission_status(): void
    {
        $government = new User([
            'name' => 'Pemerintah PBI11',
            'email' => 'pemerintah-pbi11@example.test',
            'password' => Hash::make(self::PASSWORD),
        ]);
        $government->role = 'pemerintah';
        $government->is_active = true;
        $government->save();

        $this->browse(function (Browser $browser) {
            $this->clearBrowserSession($browser);

            $this->login($browser, 'pemerintah-pbi11@example.test');
            $browser->assertPathIs('/pemerintah/wilayah');

            $submissionUrl = '/pengajuan/lokasi/terkirim/'
                .$this->fixture['pengajuan'];
            $browser->visit($submissionUrl)
                ->assertPathIs($submissionUrl);

            $status = $browser->script(
                'return performance.getEntriesByType("navigation")[0].responseStatus;'
            )[0] ?? null;

            $this->assertSame(403, $status);
        });
    }
}
