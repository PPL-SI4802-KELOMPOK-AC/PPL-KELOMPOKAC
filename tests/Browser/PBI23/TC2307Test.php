<?php

namespace Tests\Browser\PBI23;

use Illuminate\Support\Facades\DB;
use Laravel\Dusk\Browser;

/** Jalankan: php artisan dusk --filter=TC2307Test */
class TC2307Test extends PBI23TestCase
{
    public function test_tc_23_07_aktifkan_kembali_akun(): void
    {
        $user = $this->fixture['society'];
        $user->forceFill(['is_active' => false])->save();
        $id = DB::table('pengajuan_plts')->insertGetId(['user_id' => $user->id, 'wilayah_id' => $this->fixture['region'],
            'status_terkini' => 'diajukan', 'status_diperbarui_pada' => now()]);
        $this->browse(function (Browser $b) use ($user, $id) {
            $this->admin($b, '/admin/pengguna/'.$user->id.'/edit');
            $b->assertSelected('is_active', '0')->select('is_active', '1')->pause($this->stepPause())->press('Simpan perubahan')->pause($this->stepPause())
                ->waitForText('Perubahan pengguna berhasil disimpan.');
            $this->assertTrue($user->fresh()->is_active);
            $this->assertDatabaseHas('pengajuan_plts', ['id' => $id, 'user_id' => $user->id]);
            $b->logout()->visit('/login')->pause($this->stepPause())->typeSlowly('email', $user->email, $this->typingPause())->pause($this->stepPause())->typeSlowly('password', 'password-khusus-uji', $this->typingPause())->pause($this->stepPause())
                ->press('Masuk')->pause($this->stepPause())->waitForLocation('/peta-wilayah')->assertSee('Masyarakat Uji');
            $this->capture($b, 'TC-23-07-aktifkan-kembali');
        });
    }
}
