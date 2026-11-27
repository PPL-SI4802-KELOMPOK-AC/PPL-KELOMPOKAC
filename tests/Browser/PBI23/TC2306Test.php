<?php

namespace Tests\Browser\PBI23;

use Illuminate\Support\Facades\DB;
use Laravel\Dusk\Browser;

/** Jalankan: php artisan dusk --filter=TC2306Test */
class TC2306Test extends PBI23TestCase
{
    public function test_tc_23_06_nonaktifkan_akun_berriwayat_dan_sesi(): void
    {
        $user = $this->fixture['society'];
        $id = DB::table('pengajuan_plts')->insertGetId(['user_id' => $user->id, 'wilayah_id' => $this->fixture['region'],
            'status_terkini' => 'diajukan', 'status_diperbarui_pada' => now()]);
        DB::table('riwayat_status_pengajuan')->insert(['pengajuan_id' => $id, 'status_baru' => 'diajukan',
            'jenis_tindakan' => 'pengajuan_baru', 'created_at' => now()]);
        $history = DB::table('riwayat_status_pengajuan')->get()->toJson();
        $this->browse(function (Browser $admin, Browser $person) use ($user, $id, $history) {
            $person->loginAs($user)->visit('/peta-wilayah')->pause($this->stepPause())->waitForText('Peta Wilayah Jawa Barat');
            $this->admin($admin, '/admin/pengguna/'.$user->id.'/edit');
            $admin->select('is_active', '0')->pause($this->stepPause())->press('Simpan perubahan')->pause($this->stepPause())->waitForText('Perubahan pengguna berhasil disimpan.');
            $this->assertFalse($user->fresh()->is_active);
            $person->visit('/pengajuan/riwayat')->pause($this->stepPause())->waitForLocation('/login')
                ->typeSlowly('email', $user->email, $this->typingPause())->pause($this->stepPause())->typeSlowly('password', 'password-khusus-uji', $this->typingPause())->pause($this->stepPause())->press('Masuk')->pause($this->stepPause())->waitForText('Email atau kata sandi salah');
            $this->assertDatabaseHas('pengajuan_plts', ['id' => $id, 'user_id' => $user->id]);
            $this->assertSame($history, DB::table('riwayat_status_pengajuan')->get()->toJson());
            $this->capture($person, 'TC-23-06-akun-nonaktif-riwayat-utuh');
        });
    }
}
