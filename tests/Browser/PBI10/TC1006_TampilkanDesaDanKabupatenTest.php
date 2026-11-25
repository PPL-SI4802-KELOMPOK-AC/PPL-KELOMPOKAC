<?php

namespace Tests\Browser\PBI10;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TC1006_TampilkanDesaDanKabupatenTest extends DuskTestCase
{
    use PBI10DuskSupport;

    public function test_desa_dan_kabupaten_diuraikan_dari_alamat(): void
    {
        $account = $this->makeUser();
        $submission = $this->makeSubmission($account['user'], ['ticket' => 'PBI10-ALAMAT-VALID']);

        $this->browse(function (Browser $browser) use ($account, $submission) {
            $this->signIn($browser, $account)
                ->visit($this->appUrl('/pengajuan/riwayat'))
                ->pause($this->demoPauseDuration())
                ->assertSeeIn('tbody tr:first-child', $submission->nomor_tiket)
                ->assertSeeIn('tbody tr:first-child', 'Awassagara')
                ->assertSeeIn('tbody tr:first-child', 'Kabupaten Garut');
        });
    }
}
