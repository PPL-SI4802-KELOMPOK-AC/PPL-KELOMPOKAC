<?php

namespace Tests\Browser\PBI10;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TC1007_FallbackAlamatLokasiTest extends DuskTestCase
{
    use PBI10DuskSupport;

    public function test_alamat_yang_tidak_dapat_diuraikan_ditampilkan_sebagai_fallback(): void
    {
        $account = $this->makeUser();
        $fallbackAddress = 'Gang Contoh Uji PBI10, Desa Rekaan, Kabupaten Rekaan';
        $submission = $this->makeSubmission($account['user'], [
            'ticket' => 'PBI10-ALAMAT-FALLBACK',
            'address' => $fallbackAddress,
        ]);

        $this->browse(function (Browser $browser) use ($account, $submission, $fallbackAddress) {
            $this->signIn($browser, $account)
                ->visit($this->appUrl('/pengajuan/riwayat'))
                ->assertSeeIn('tbody tr:first-child', $submission->nomor_tiket)
                ->assertSeeIn('tbody tr:first-child', $fallbackAddress)
                ->assertSeeIn('tbody tr:first-child', 'Tidak tersedia');
        });
    }
}
