<?php

namespace Tests\Browser\PBI24;

use Laravel\Dusk\Browser;

/** Jalankan: php artisan dusk --filter=TC2409Test */
class TC2409Test extends PBI24TestCase
{
    public function test_tc_24_09_validasi_nama_dan_izin_tidak_dikenal(): void
    {
        $this->browse(function (Browser $b) {
            $this->admin($b, '/admin/hak-akses/tambah');
            $b->script('document.querySelector("[data-access-submit]").noValidate=true');
            $b->press('Tinjau dampak')->pause($this->stepPause())->waitForText('Nama profil wajib diisi.')
                ->typeSlowly('name', '  PETUGAS   PEMERINTAH ', $this->typingPause())->pause($this->stepPause())->press('Tinjau dampak')->pause($this->stepPause())->waitForText('Nama profil sudah digunakan');
            $b->typeSlowly('name', 'Profil Permintaan Tidak Sah', $this->typingPause())->pause($this->stepPause())->check('permissions[]', 'wilayah.view')->pause($this->stepPause());
            $b->script('document.querySelector("input[value=\"wilayah.view\"]").value="izin.tidak.dikenal"');
            $b->press('Tinjau dampak')->pause($this->stepPause())->waitForText('Kode izin tidak dikenal.');
            $this->assertDatabaseMissing('staff_access_profiles', ['name' => 'Profil Permintaan Tidak Sah']);
            $this->capture($b, 'TC-24-09-validasi-formulir');
        });
    }
}
