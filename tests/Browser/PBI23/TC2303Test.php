<?php

namespace Tests\Browser\PBI23;

use Laravel\Dusk\Browser;

/** Jalankan: php artisan dusk --filter=TC2303Test */
class TC2303Test extends PBI23TestCase
{
    public function test_tc_23_03_cari_filter_dan_reset(): void
    {
        $this->browse(function (Browser $b) {
            $this->admin($b);
            $b->typeSlowly('q', 'Petugas', $this->typingPause())->pause($this->stepPause())->select('role', 'pemerintah')->pause($this->stepPause())->select('access', 'inactive')->pause($this->stepPause())->press('Terapkan filter')->pause($this->stepPause())
                ->waitForText('Petugas Nonaktif')->assertCount('.admin-user-table tbody tr', 1)
                ->assertDontSeeIn('.admin-user-table', 'Petugas Uji')->assertSeeIn('.admin-stats article:first-child strong', '4');
            $this->capture($b, 'TC-23-03-filter-gabungan');
            $b->clickLink('Reset filter')->pause($this->stepPause())->waitForLocation('/admin/pengguna')->assertCount('.admin-user-table tbody tr', 4)
                ->assertInputValue('q', '')->assertSelected('role', '')->assertSelected('access', '');
            $b->typeSlowly('q', 'society@pbi24.example.test', $this->typingPause())->pause($this->stepPause())->press('Terapkan filter')->pause($this->stepPause())->waitForText('Masyarakat Uji')
                ->assertCount('.admin-user-table tbody tr', 1)->assertSeeIn('.admin-stats article:first-child strong', '4');
            $this->capture($b, 'TC-23-03-cari-email');
        });
    }
}
