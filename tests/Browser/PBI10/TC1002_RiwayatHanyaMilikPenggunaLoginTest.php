<?php

namespace Tests\Browser\PBI10;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TC1002_RiwayatHanyaMilikPenggunaLoginTest extends DuskTestCase
{
    use PBI10DuskSupport;

    public function test_riwayat_hanya_memuat_pengajuan_pengguna_login(): void
    {
        $account = $this->makeUser('Pemilik Login PBI10');
        $other = $this->makeUser('Pemilik Lain PBI10');
        $owned = $this->makeSubmission($account['user'], ['ticket' => 'PBI10-MILIK-LOGIN']);
        $notOwned = $this->makeSubmission($other['user'], ['ticket' => 'PBI10-MILIK-ORANG-LAIN']);

        $this->browse(function (Browser $browser) use ($account, $owned, $notOwned) {
            $this->signIn($browser, $account)
                ->visit($this->appUrl('/pengajuan/riwayat'))
                ->pause($this->demoPauseDuration())
                ->assertSeeIn('tbody', $owned->nomor_tiket)
                ->assertDontSeeIn('tbody', $notOwned->nomor_tiket)
                ->assertSeeIn('.history-results', 'Menampilkan 1–1 dari 1 pengajuan');
        });
    }
}
