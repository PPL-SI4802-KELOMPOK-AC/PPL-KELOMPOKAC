<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wilayah;
use App\Services\Map\CsvDataset;
use App\Services\Map\Geometry;
use App\Services\Map\MapData;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MapDataMySqlTest extends TestCase
{
    private bool $transactionStarted = false;
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('PBI01_MYSQL_TESTS') !== '1') {
            $this->markTestSkipped('Uji integrasi peta MySQL memerlukan PBI01_MYSQL_TESTS=1 dan cache dataset lokal.');
        }
        $this->assertSame('mysql', DB::connection()->getDriverName());
        DB::beginTransaction();
        $this->transactionStarted = true;
        config(['app.key' => 'base64:'.base64_encode(str_repeat('t', 32))]);
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        if ($this->transactionStarted) {
            DB::rollBack();
        }
        foreach ($this->temporaryFiles as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    private function fixtureDataset(string $code): void
    {
        $dataset = app(MapData::class)->artifact(config('map.dataset_path'));
        $dataset['records'] = [$code => [
            'kemendagri_code' => $code, 'bps_code' => '3299990001', 'kabupaten_code' => '3299', 'kabupaten_name' => 'Kabupaten fixture',
            'belum_listrik' => 23, 'pln' => 9999, 'year' => 2024, 'unit' => 'KELUARGA',
            'source_status' => 'belum_diverifikasi', 'source' => 'Fixture transaksi, bukan data pengguna',
        ]];
        $path = storage_path('framework/testing/map-fixture-'.bin2hex(random_bytes(6)).'.json');
        $this->temporaryFiles[] = $path;
        CsvDataset::writeJson($path, $dataset);
        config(['map.dataset_path' => $path]);
    }

    public function test_actual_csv_codes_match_mysql_and_map_reads_do_not_change_stored_values(): void
    {
        $stored = Wilayah::where('kode_referensi', '32.01.21.2001')->firstOrFail();
        $before = $stored->getRawOriginal();
        $dataset = app(MapData::class)->artifact(config('map.dataset_path'));
        $expected = DB::table('wilayah')->whereIn('kode_referensi', array_keys($dataset['records']))->count();
        $response = $this->getJson('/peta-wilayah/data?q=MALASARI&kabupaten=3201')->assertOk();
        $this->assertSame($expected, $response->json('matching.database_matched'));
        $record = collect($response->json('list.data'))->firstWhere('code', '32.01.21.2001');
        $this->assertNotNull($record);
        $this->assertSame(0, $record['csv']['belum_listrik']);
        $this->assertSame(2690, $record['csv']['pln']);
        $this->assertSame($stored->jumlah_kk_terdampak, $record['government']['affected_kk']);
        $this->assertSame(array_key_exists((string) $stored->kondisi_listrik, config('wilayah.kondisi_listrik')), $record['government']['condition_recorded']);
        $this->assertSame('no_unserved', $record['category']);
        $this->assertSame('village_reference', $record['location']['type']);
        $this->assertArrayNotHasKey('created_by', $record);
        $this->assertSame($before, $stored->fresh()->getRawOriginal());
    }

    public function test_government_crud_is_live_on_map_and_does_not_get_overwritten_by_csv(): void
    {
        $code = '32.99.99.9991';
        $this->fixtureDataset($code);
        $user = new User(['name' => 'Fixture peta', 'email' => bin2hex(random_bytes(8)).'@example.test', 'password' => 'peta-fixture-password']);
        $user->role = 'pemerintah'; $user->save();
        $payload = ['kode_referensi' => $code, 'nama_wilayah' => 'MAP-TEST-'.bin2hex(random_bytes(6)), 'tingkat_wilayah' => 'desa_kelurahan',
            'alamat_deskriptif' => 'Alamat fixture MySQL', 'latitude' => '-6,9', 'longitude' => '107,6',
            'jumlah_kk_terdampak' => 7, 'kondisi_listrik' => 'sudah_berlistrik'];
        $id = $this->actingAs($user)->postJson('/pemerintah/wilayah', $payload)->assertCreated()->json('wilayah.id');
        $url = '/peta-wilayah/data?'.http_build_query(['q' => $payload['nama_wilayah']]);
        $this->getJson($url)->assertOk()->assertJsonPath('summary.total', 1)
            ->assertJsonPath('list.data.0.government.affected_kk', 7)->assertJsonPath('list.data.0.csv.belum_listrik', 23)
            ->assertJsonPath('list.data.0.csv.pln', 9999)->assertJsonPath('list.data.0.government.condition_recorded', true)
            ->assertJsonPath('list.data.0.category', 'has_unserved')
            ->assertJsonPath('list.data.0.location.source', 'database')->assertJsonPath('list.data.0.location.coordinates', [107.6, -6.9]);
        $this->putJson('/pemerintah/wilayah/'.$id, [...$payload, 'latitude' => null, 'longitude' => null, 'kondisi_listrik' => null, 'jumlah_kk_terdampak' => null])->assertOk();
        $this->getJson($url)->assertOk()->assertJsonPath('list.data.0.government.affected_kk', null)
            ->assertJsonPath('list.data.0.location', null)->assertJsonPath('summary.unlocated', 1)
            ->assertJsonPath('list.data.0.category', 'has_unserved');
        $this->getJson($url.'&condition=no_unserved')->assertJsonPath('summary.total', 0);
        $this->getJson($url.'&condition=has_unserved')->assertJsonPath('summary.total', 1);
        $this->assertDatabaseHas('wilayah', ['id' => $id, 'jumlah_kk_terdampak' => null, 'kondisi_listrik' => null]);
    }

    public function test_invalid_manual_coordinates_do_not_silently_fall_back_to_other_source(): void
    {
        $code = '32.99.99.9992';
        $this->fixtureDataset($code);
        $region = Wilayah::create(['kode_referensi' => $code, 'nama_wilayah' => 'MAP-INVALID-'.bin2hex(random_bytes(4)),
            'tingkat_wilayah' => 'desa_kelurahan', 'alamat_deskriptif' => 'Alamat fixture', 'latitude' => 0, 'longitude' => 0]);
        $geography = app(MapData::class)->artifact(config('map.locations_path'));
        $geography['points'][$code] = ['coordinates' => [107.6, -6.9], 'name' => 'Fixture', 'feature_id' => 0];
        $path = storage_path('framework/testing/geo-fixture-'.bin2hex(random_bytes(6)).'.json');
        $this->temporaryFiles[] = $path; CsvDataset::writeJson($path, $geography); config(['map.locations_path' => $path]);
        $url = '/peta-wilayah/data?'.http_build_query(['q' => $region->nama_wilayah]);
        $this->getJson($url)->assertOk()->assertJsonPath('summary.unlocated', 1)->assertJsonCount(0, 'features')->assertJsonPath('list.data.0.location', null);
        $region->update(['latitude' => null, 'longitude' => null]);
        $this->getJson($url)->assertOk()->assertJsonPath('summary.located', 1)->assertJsonPath('list.data.0.location.source', 'big_polygon_interior');
    }

    public function test_positive_zero_null_and_missing_dataset_are_distinct_with_combined_filters(): void
    {
        $codes = ['32.99.99.9981', '32.99.99.9982', '32.99.99.9983', '32.99.99.9984'];
        $this->fixtureDataset($codes[0]);
        $dataset = app(MapData::class)->artifact(config('map.dataset_path'));
        $base = $dataset['records'][$codes[0]];
        foreach ([1 => 0, 2 => null] as $index => $families) {
            $dataset['records'][$codes[$index]] = [...$base, 'kemendagri_code' => $codes[$index], 'belum_listrik' => $families];
        }
        CsvDataset::writeJson(config('map.dataset_path'), $dataset);
        $prefix = 'MAP-COUNTS-'.bin2hex(random_bytes(4));
        foreach ($codes as $index => $code) {
            Wilayah::create(['kode_referensi' => $code, 'nama_wilayah' => $prefix.'-'.$index,
                'tingkat_wilayah' => 'desa_kelurahan', 'alamat_deskriptif' => 'Alamat fixture filter',
                'kondisi_listrik' => 'sudah_berlistrik', 'jumlah_kk_terdampak' => 88]);
        }
        $url = '/peta-wilayah/data?'.http_build_query(['q' => $prefix]);
        $this->getJson($url)->assertOk()->assertJsonPath('summary.total', 4)
            ->assertJsonPath('summary.has_unserved', 1)->assertJsonPath('summary.no_unserved', 1)
            ->assertJsonPath('summary.unavailable', 2)->assertJsonPath('summary.unlocated', 4)
            ->assertJsonPath('list.data.2.csv.belum_listrik', null)->assertJsonPath('list.data.3.csv', null)
            ->assertJsonPath('list.data.2.category', 'unavailable')->assertJsonPath('list.data.3.category', 'unavailable');
        foreach (['has_unserved' => [0, 23], 'no_unserved' => [1, 0]] as $condition => [$index, $families]) {
            $this->getJson($url.'&condition='.$condition.'&kabupaten=3299&location=missing')->assertOk()
                ->assertJsonPath('summary.total', 1)->assertJsonPath('list.data.0.code', $codes[$index])
                ->assertJsonPath('list.data.0.csv.belum_listrik', $families)
                ->assertJsonPath('list.data.0.csv.pln', 9999)->assertJsonPath('list.data.0.government.affected_kk', 88)
                ->assertJsonCount(0, 'features');
        }
        $this->getJson($url.'&kabupaten=3201')->assertJsonPath('summary.total', 0);
        $this->getJson($url.'&condition=unknown')->assertUnprocessable();
        $this->getJson($url.'&condition=verified')->assertUnprocessable();
        $this->assertDatabaseHas('wilayah', ['kode_referensi' => $codes[0], 'jumlah_kk_terdampak' => 88, 'kondisi_listrik' => 'sudah_berlistrik']);
    }

    public function test_clustering_pagination_viewport_and_missing_location_filter_use_actual_counts(): void
    {
        $response = $this->getJson('/peta-wilayah/data?zoom=8')->assertOk();
        $json = $response->json();
        $this->assertSame($json['summary']['total'], $json['summary']['located'] + $json['summary']['unlocated']);
        $sum = 0;
        $boundary = app(MapData::class)->artifact(config('map.boundary_path'));
        foreach ($json['features'] as $feature) {
            $sum += $feature['type'] === 'cluster' ? $feature['count'] : 1;
            $point = $feature['type'] === 'cluster' ? $feature['coordinates'] : $feature['record']['location']['coordinates'];
            $this->assertTrue(Geometry::contains($boundary['geometry'], $point));
        }
        $this->assertSame($sum, $json['summary']['located']);
        $this->assertLessThan(300, count($json['features']));
        $this->assertCount(15, $json['list']['data']);
        $this->getJson('/peta-wilayah/data?bbox=110,-9,112,-7')->assertOk()->assertJsonCount(0, 'features')->assertJsonCount(15, 'list.data');
        $this->getJson('/peta-wilayah/data?location=missing')->assertOk()->assertJsonPath('summary.located', 0)->assertJsonCount(0, 'features');
        $this->getJson('/peta-wilayah/data?bbox=invalid')->assertUnprocessable();
        $this->getJson('/peta-wilayah/data?zoom=100')->assertUnprocessable();
    }

    public function test_prepare_dry_run_matches_codes_without_writing_database_or_cache(): void
    {
        $before = DB::table('wilayah')->count();
        $path = storage_path('framework/testing/map-dry-run-'.bin2hex(random_bytes(6)).'.json');
        config(['map.dataset_path' => $path]);
        $this->artisan('map:prepare', ['--dry-run' => true])->assertSuccessful();
        $this->assertFileDoesNotExist($path);
        $this->assertSame($before, DB::table('wilayah')->count());
    }
}
