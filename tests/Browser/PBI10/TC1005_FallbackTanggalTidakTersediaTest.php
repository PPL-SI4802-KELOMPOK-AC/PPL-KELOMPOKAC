<?php

namespace Tests\Browser\PBI10;

use Carbon\Carbon;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TC1005_FallbackTanggalTidakTersediaTest extends DuskTestCase
{
    use PBI10DuskSupport;

    public function test_pengajuan_tanpa_catatan_awal_tampil_setelah_yang_bertanggal(): void
    {
        $account = $this->makeUser();
        $dated = $this->makeSubmission($account['user'], [
            'ticket' => 'PBI10-BERTANGGAL',
            'submitted_at' => Carbon::create(2026, 10, 1, 8, 0),
        ]);
        $missingDate = $this->makeSubmission($account['user'], [
            'ticket' => 'PBI10-TANPA-TANGGAL',
            'submitted_at' => false,
        ]);

        $this->browse(function (Browser $browser) use ($account, $dated, $missingDate) {
            $this->signIn($browser, $account)
                ->visit($this->appUrl('/pengajuan/riwayat'))
                ->pause($this->demoPauseDuration())
                ->assertSeeIn('tbody tr:first-child', $dated->nomor_tiket)
                ->assertSeeIn('tbody tr:nth-child(2)', $missingDate->nomor_tiket)
                ->assertSeeIn('tbody tr:nth-child(2)', 'Tanggal tidak tersedia');
        });
    }
}
