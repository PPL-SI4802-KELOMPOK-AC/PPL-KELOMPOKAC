<?php

namespace Tests\Browser\PBI23;

use Illuminate\Support\Facades\DB;
use Laravel\Dusk\Browser;

/** Jalankan: php artisan dusk --filter=TC2316Test */
class TC2316Test extends PBI23TestCase
{
    public function test_tc_23_16_non_admin_ditolak_semua_operasi(): void
    {
        $before = DB::table('users')->orderBy('id')->get()->toJson();
        $audit = DB::table('admin_activity_logs')->count();
        $this->browse(function (Browser $b) use ($before, $audit) {
            foreach (['society', 'staff'] as $key) {
                $b->loginAs($this->fixture[$key])->visit('/peta-wilayah')->pause($this->stepPause())->waitForText('Peta Wilayah Jawa Barat');
                $token = $b->attribute('form[action$="/logout"] input[name="_token"]', 'value');
                $b->visit('/admin/pengguna')->pause($this->stepPause())->assertSee('403')->assertDontSee('society@pbi24.example.test');
                foreach (['POST' => '/admin/pengguna', 'PUT' => '/admin/pengguna/'.$this->fixture['admin']->id] as $method => $url) {
                    $this->assertSame(403, $this->request($b, $method, $url, $this->accountPayload(), $token)['status']);
                }
                $this->assertSame(403, $this->request($b, 'PUT', '/admin/pengguna/'.$this->fixture['admin']->id,
                    $this->accountPayload(['is_active' => '0']), $token)['status']);
                $this->assertSame($before, DB::table('users')->orderBy('id')->get()->toJson());
                $this->assertSame($audit, DB::table('admin_activity_logs')->count());
                $this->capture($b, 'TC-23-16-'.$key.'-ditolak');
            }
        });
        $this->markTestIncomplete('GET, POST, PUT dan penonaktifan non-Admin sudah diperiksa dengan CSRF valid. Bagian DELETE belum tersedia sehingga seluruh TC belum dapat dinyatakan lulus.');
    }
}
