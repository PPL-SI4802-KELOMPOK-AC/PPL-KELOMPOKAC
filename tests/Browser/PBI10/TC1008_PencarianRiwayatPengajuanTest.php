<?php

namespace Tests\Browser\PBI10;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TC1008_PencarianRiwayatPengajuanTest extends DuskTestCase
{
    use PBI10DuskSupport;

    public function test_pencarian_nama_desa_tetap_membatasi_pengguna(): void
    {
        $account = $this->makeUser();
        $other = $this->makeUser('Pemilik Lain PBI10');
        $owned = $this->makeSubmission($account['user'], ['ticket' => 'PBI10-CARI-TIKET-OWN']);
        $otherOwned = $this->makeSubmission($account['user'], [
            'ticket' => 'PBI10-CARI-DESA-OWN',
            'address' => 'Kampung PBI10, Awasagara, Cikelet, Kabupaten Garut, Jawa Barat',
        ]);
        $otherUserSubmission = $this->makeSubmission($other['user'], [
            'ticket' => 'PBI10-CARI-DESA-OTHER',
            'address' => 'Kampung PBI10, Awasagara, Cikelet, Kabupaten Garut, Jawa Barat',
        ]);

        $this->browse(function (Browser $browser) use ($account, $owned, $otherOwned, $otherUserSubmission) {
            $this->signIn($browser, $account)
                ->visit($this->appUrl('/pengajuan/riwayat'))
                ->pause($this->demoPauseDuration())
                ->type('#history-search', $owned->nomor_tiket)
                ->press('Terapkan')
                ->pause($this->demoPauseDuration())
                ->assertQueryStringHas('q', $owned->nomor_tiket)
                ->assertDontSeeIn('tbody', $owned->nomor_tiket)
                ->assertDontSeeIn('tbody', $otherOwned->nomor_tiket)
                ->assertDontSeeIn('tbody', $otherUserSubmission->nomor_tiket)
                ->type('#history-search', 'aSaGa')
                ->press('Terapkan')
                ->pause($this->demoPauseDuration())
                ->assertQueryStringHas('q', 'aSaGa')
                ->assertSeeIn('tbody', $otherOwned->nomor_tiket)
                ->assertDontSeeIn('tbody', $otherUserSubmission->nomor_tiket)
                ->assertSeeIn('.history-results', 'Menampilkan 1–1 dari 1 pengajuan');
        });
    }
}
