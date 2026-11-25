<?php

namespace Tests\Browser\PBI04;

use Illuminate\Support\Facades\DB;
use Laravel\Dusk\Browser;
use Tests\Browser\MapDuskTestCase;

class PBI04TC0405Test extends MapDuskTestCase
{
    /** TC-04-05 | Data opsional kosong ditampilkan dengan placeholder tanpa crash. */
    public function test_tc_04_05_kode_dan_tahun_kosong_menggunakan_placeholder(): void
    {
        $record = $this->addSampleRegion('3201', null);
        DB::table('wilayah')->where('id', $record['id'])->update([
            'kode_referensi' => null, 'sumber_data' => null, 'data_per_tanggal' => null,
        ]);
        $this->browse(function (Browser $browser) use ($record) {
            $this->openMap($browser);
            $browser->click('[data-map-rows] tr[data-id="'.$record['id'].'"] button')
                ->waitForTextIn('#map-detail-title', $record['name'], 15)
                ->assertSeeIn('[data-map-detail]', 'Data belum tersedia')
                ->assertSeeIn('[data-map-detail]', 'Belum tersedia');
        });
    }
}
