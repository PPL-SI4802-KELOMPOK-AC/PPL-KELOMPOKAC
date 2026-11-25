<?php

namespace Tests\Browser\PBI10;

use Carbon\Carbon;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TC1004_TampilkanTanggalCatatanAwalTest extends DuskTestCase
{
    use PBI10DuskSupport;

    public function test_tanggal_diambil_dari_catatan_awal_pengajuan(): void
    {
        $account = $this->makeUser();
        $initialDate = Carbon::create(2026, 8, 14, 9, 30);
        $submission = $this->makeSubmission($account['user'], [
            'ticket' => 'PBI10-TANGGAL-CATATAN-AWAL',
            'submitted_at' => $initialDate,
            'status_updated_at' => Carbon::create(2026, 9, 20, 15, 0),
        ]);
        $expectedDate = $initialDate->locale('id')->translatedFormat('d M Y');

        $this->browse(function (Browser $browser) use ($account, $submission, $expectedDate) {
            $this->signIn($browser, $account)
                ->visit($this->appUrl('/pengajuan/riwayat'))
                ->pause($this->demoPauseDuration())
                ->assertSeeIn('tbody tr:first-child', $submission->nomor_tiket)
                ->assertSeeIn('tbody tr:first-child', $expectedDate);
        });
    }
}
