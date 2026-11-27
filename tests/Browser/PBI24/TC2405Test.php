<?php

namespace Tests\Browser\PBI24;

use Laravel\Dusk\Browser;

/** Jalankan: php artisan dusk --filter=TC2405Test */
class TC2405Test extends PBI24TestCase
{
    public function test_tc_24_05_petugas_memakai_modul_dan_tindakan_diizinkan(): void
    {
        $profile = $this->accessProfile('Petugas Input', ['wilayah.view', 'wilayah.create']);
        $this->bind($profile);
        $this->browse(function (Browser $b) {
            $b->resize(1440, 1050)->pause($this->stepPause())->loginAs($this->fixture['staff'])->visit('/pemerintah/wilayah')->pause($this->stepPause())->waitForTextIn('[data-rows]', 'Mekarjaya')
                ->typeSlowly('nama_wilayah', 'Desa Izin Uji', $this->typingPause())->pause($this->stepPause())->typeSlowly('tingkat_wilayah', 'desa_kelurahan', $this->typingPause())->pause($this->stepPause())
                ->typeSlowly('alamat_deskriptif', 'Kabupaten Bandung, Jawa Barat', $this->typingPause())->pause($this->stepPause())->click('[data-save]')->pause($this->stepPause())
                ->waitForText('Wilayah berhasil ditambahkan.')->waitForTextIn('[data-rows]', 'Desa Izin Uji');
            $this->assertDatabaseHas('wilayah', ['nama_wilayah' => 'Desa Izin Uji', 'created_by' => $this->fixture['staff']->id]);
            $this->capture($b, 'TC-24-05-tindakan-diizinkan');
        });
    }
}
