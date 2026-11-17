<?php

namespace Tests\Browser\PBI10;

use Carbon\Carbon;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TC1009_FilterStatusDanPeriodeTest extends DuskTestCase
{
    use PBI10DuskSupport;

    public function test_filter_status_dan_periode_memakai_catatan_awal(): void
    {
        $account = $this->makeUser();
        $october = $this->makeSubmission($account['user'], [
            'ticket' => 'PBI10-FILTER-OCT',
            'status' => 'diajukan',
            'submitted_at' => Carbon::create(2026, 10, 12, 9, 0),
        ]);
        $september = $this->makeSubmission($account['user'], [
            'ticket' => 'PBI10-FILTER-SEP',
            'status' => 'selesai',
            'submitted_at' => Carbon::create(2026, 9, 4, 9, 0),
        ]);
        $withoutDate = $this->makeSubmission($account['user'], [
            'ticket' => 'PBI10-FILTER-NO-DATE',
            'status' => 'diajukan',
            'submitted_at' => false,
        ]);

        $this->browse(function (Browser $browser) use ($account, $october, $september, $withoutDate) {
            $this->signIn($browser, $account)
                ->visit($this->appUrl('/pengajuan/riwayat'))
                ->select('#history-status', 'selesai')
                ->press('Terapkan')
                ->assertQueryStringHas('status', 'selesai')
                ->assertSeeIn('tbody', $september->nomor_tiket)
                ->assertSeeIn('tbody tr:first-child [data-history-status]', 'Selesai')
                ->assertDontSeeIn('tbody', $october->nomor_tiket)
                ->select('#history-status', 'diajukan');
            $browser->script("document.querySelector('#history-period').value = '2026-10';");
            $browser->press('Terapkan')
                ->assertQueryStringHas('status', 'diajukan')
                ->assertQueryStringHas('periode', '2026-10')
                ->assertSeeIn('tbody', $october->nomor_tiket)
                ->assertDontSeeIn('tbody', $withoutDate->nomor_tiket)
                ->assertDontSeeIn('tbody', $september->nomor_tiket);
        });
    }
}
