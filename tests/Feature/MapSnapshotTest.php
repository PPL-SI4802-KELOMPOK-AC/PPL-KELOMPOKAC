<?php

namespace Tests\Feature;

use App\Models\Wilayah;
use App\Services\Map\MapData;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MapSnapshotTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'cache.stores.file' => ['driver' => 'array'],
            'map.snapshot_cache_store' => 'array',
        ]);
        DB::purge('sqlite');
        Schema::create('wilayah', function (Blueprint $table) {
            $table->id();
            foreach (['kode_referensi', 'nama_wilayah', 'alamat_deskriptif', 'latitude', 'longitude', 'kondisi_listrik', 'sumber_data', 'data_per_tanggal'] as $column) {
                $table->string($column)->nullable();
            }
            $table->integer('jumlah_kk_terdampak')->nullable();
        });
    }

    private function region(string $name, ?int $families): Wilayah
    {
        return Wilayah::create([
            'kode_referensi' => '32.01.21.2001', 'nama_wilayah' => $name,
            'alamat_deskriptif' => 'MALASARI, NANGGUNG, KABUPATEN BOGOR, JAWA BARAT',
            'jumlah_kk_terdampak' => $families,
        ]);
    }

    public function test_warm_snapshot_matches_cold_response_and_filters_without_queries(): void
    {
        $this->region('Alpha', 7);
        $this->region('Beta', 0);
        $map = new MapData;
        $cold = $map->response([]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->assertEquals($cold, $map->response([]));
        $filtered = $map->response(['q' => 'Beta', 'condition' => 'no_unserved', 'location' => 'missing']);
        $this->assertSame(1, $filtered['summary']['total']);
        $this->assertSame('Beta', $filtered['list']['data'][0]['name']);
        $this->assertSame(0, $map->response(['kabupaten' => '3202'])['summary']['total']);
        $this->assertSame([], DB::getQueryLog());
    }

    public function test_create_update_delete_and_committed_transaction_invalidate_snapshot(): void
    {
        $map = new MapData;
        $this->assertSame(0, $map->response([])['summary']['total']);
        $region = $this->region('Alpha', 7);
        $this->assertSame(7, $map->response([])['summary']['affected_kk']);
        $region->update(['jumlah_kk_terdampak' => 9]);
        $this->assertSame(9, $map->response([])['summary']['affected_kk']);
        DB::beginTransaction();
        $region->update(['jumlah_kk_terdampak' => 12]);
        $this->assertSame(9, $map->response([])['summary']['affected_kk']);
        DB::commit();
        $this->assertSame(12, $map->response([])['summary']['affected_kk']);
        DB::beginTransaction();
        $region->update(['jumlah_kk_terdampak' => 99]);
        DB::rollBack();
        $this->assertSame(12, $map->response([])['summary']['affected_kk']);
        $region->delete();
        $this->assertSame(0, $map->response([])['summary']['total']);
    }

    public function test_external_updates_are_visible_after_cache_expiry(): void
    {
        $region = $this->region('Alpha', 7);
        $map = new MapData;
        $map->response([]);
        DB::table('wilayah')->where('id', $region->id)->update(['jumlah_kk_terdampak' => 30]);
        $this->assertSame(7, $map->response([])['summary']['affected_kk']);
        $this->travel(301)->seconds();
        $this->assertSame(30, $map->response([])['summary']['affected_kk']);
        $this->travelBack();
    }
    public function test_dashboard_cache_preserves_unknown_values_and_refreshes_after_changes(): void
    {
        $service = new \App\Services\DashboardSummary;
        $unknown = $this->region('Unknown', null);
        $first = $service->data();
        $this->assertTrue($first['all_unknown']);
        $this->assertNull($first['metrics']['unserved_kk']);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->assertSame($first, $service->data());
        $this->assertSame([], DB::getQueryLog());
        $zero = $this->region('Zero', 0);
        $this->assertSame(0, $service->data()['metrics']['unserved_kk']);
        $unknown->update(['jumlah_kk_terdampak' => 12]);
        $this->assertSame(12, $service->data()['metrics']['unserved_kk']);
        $unknown->delete();
        $this->assertSame(1, $service->data()['metrics']['total']);
        DB::table('wilayah')->where('id', $zero->id)->update(['jumlah_kk_terdampak' => 4]);
        $this->assertSame(0, $service->data()['metrics']['unserved_kk']);
        $this->travel(301)->seconds();
        $this->assertSame(4, $service->data()['metrics']['unserved_kk']);
        $this->travelBack();
    }

}
