<?php

namespace Tests\Browser\PBI10;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TC1001_TampilkanRiwayatPengajuanTest extends DuskTestCase
{
    use PBI10DuskSupport;

    public function test_masyarakat_melihat_pengajuan_terkirim_miliknya(): void
    {
        $account = $this->makeUser();
        $submission = $this->makeSubmission($account['user'], ['ticket' => 'PBI10-TC1001']);

        $this->browse(function (Browser $browser) use ($account, $submission) {
            $this->signIn($browser, $account)
                ->visit($this->appUrl('/pengajuan/riwayat'))
                ->pause($this->demoPauseDuration())
                ->assertPathIs('/pengajuan/riwayat')
                ->assertSee('Riwayat Pengajuan Anda')
                ->assertSeeIn('tbody tr:first-child', $submission->nomor_tiket)
                ->assertSeeIn('.history-results', 'Menampilkan 1–1 dari 1 pengajuan');
        });
    }
}
