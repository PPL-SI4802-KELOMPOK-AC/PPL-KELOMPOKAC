<?php

namespace Tests\Browser\PBI24;

use Laravel\Dusk\Browser;

/** Jalankan: php artisan dusk --filter=TC2401Test */
class TC2401Test extends PBI24TestCase
{
    public function test_tc_24_01_buka_cari_dan_rincian_profil(): void
    {
        $profile = $this->accessProfile();
        $this->browse(function (Browser $b) {
            $this->admin($b, '/admin/pengguna');
            $b->clickLink('Kelola hak akses')->pause($this->stepPause())->waitForText('Kelola hak akses petugas')
                ->typeSlowly('q', 'Verifikasi', $this->typingPause())->pause($this->stepPause())->press('Cari')->pause($this->stepPause())->waitForText('Petugas Verifikasi')
                ->clickLink('Rincian')->pause($this->stepPause())->waitForText('Rincian izin')->assertSee('Melihat daftar dan rincian wilayah')
                ->assertSee('Belum ada petugas yang menggunakan profil ini.')
                ->assertAttribute('#sidebar a[aria-current]', 'href', url('/admin/pengguna'));
            $this->capture($b, 'TC-24-01-rincian-profil');
        });
    }
}
