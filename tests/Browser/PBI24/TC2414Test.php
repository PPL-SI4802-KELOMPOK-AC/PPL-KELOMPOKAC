<?php

namespace Tests\Browser\PBI24;

use Illuminate\Support\Facades\DB;
use Laravel\Dusk\Browser;

/** Jalankan: php artisan dusk --filter=TC2414Test */
class TC2414Test extends PBI24TestCase
{
    public function test_tc_24_14_batal_perubahan_dan_penghapusan(): void
    {
        $profile = $this->accessProfile();
        $before = DB::table('admin_activity_logs')->count();
        $this->browse(function (Browser $b) use ($profile, $before) {
            $this->admin($b, '/admin/hak-akses/'.$profile->id.'/edit');
            $b->typeSlowly('name', 'Nama Tidak Disimpan', $this->typingPause())->pause($this->stepPause())->press('Tinjau dampak')->pause($this->stepPause())->waitForText('Tinjau perubahan profil')
                ->clickLink('Batalkan perubahan')->pause($this->stepPause())->waitForText('Rincian izin');
            $b->visit('/admin/hak-akses/'.$profile->id.'/hapus')->pause($this->stepPause())->clickLink('Batal')->pause($this->stepPause())->waitForText('Rincian izin');
            $this->assertSame('Petugas Verifikasi', $profile->fresh()->name);
            $this->assertSame($before, DB::table('admin_activity_logs')->count());
            $this->capture($b, 'TC-24-14-pembatalan');
        });
    }
}
