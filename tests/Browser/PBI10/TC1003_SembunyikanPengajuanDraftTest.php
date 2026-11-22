<?php

namespace Tests\Browser\PBI10;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TC1003_SembunyikanPengajuanDraftTest extends DuskTestCase
{
    use PBI10DuskSupport;

    public function test_draft_tidak_muncul_di_daftar_atau_total(): void
    {
        $account = $this->makeUser();
        $sent = $this->makeSubmission($account['user'], ['ticket' => 'PBI10-TERKIRIM']);
        $draft = $this->makeSubmission($account['user'], [
            'ticket' => 'PBI10-DRAFT-TERSEMBUNYI',
            'status' => 'draft',
            'submitted_at' => false,
        ]);

        $this->browse(function (Browser $browser) use ($account, $sent, $draft) {
            $this->signIn($browser, $account)
                ->visit($this->appUrl('/pengajuan/riwayat'))
                ->pause($this->demoPauseDuration())
                ->assertSeeIn('tbody', $sent->nomor_tiket)
                ->assertDontSeeIn('tbody', $draft->nomor_tiket)
                ->assertSeeIn('.history-summary-card:first-child .history-summary-value', '1')
                ->assertSeeIn('.history-results', 'Menampilkan 1–1 dari 1 pengajuan');
        });
    }
}
