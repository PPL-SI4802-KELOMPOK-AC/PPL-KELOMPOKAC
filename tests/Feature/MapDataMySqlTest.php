<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wilayah;
use App\Services\Map\Geometry;
use App\Services\Map\MapData;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MapDataMySqlTest extends TestCase
{
    private bool $transactionStarted = false;
    private string $prefix;
    private int $sequence = 9800;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('PBI01_MYSQL_TESTS') !== '1') $this->markTestSkipped('Memerlukan opt-in transaksi database aplikasi.');
        $this->assertSame('mysql', DB::connection()->getDriverName());
        DB::beginTransaction();
        $this->transactionStarted = true;
        $this->prefix = 'MAP-DB-'.bin2hex(random_bytes(5));
        config(['app.key' => 'base64:'.base64_encode(str_repeat('t', 32))]);
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        if ($this->transactionStarted) DB::rollBack();
        parent::tearDown();
    }

    private function region(array $extra = []): Wilayah
    {
        return Wilayah::create([...[
            'kode_referensi' => '32.99.99.'.(++$this->sequence), 'nama_wilayah' => $this->prefix.' '.$this->sequence,
            'tingkat_wilayah' => 'desa_kelurahan',
            'alamat_deskriptif' => 'Desa Uji, KECAMATAN UJI, KABUPATEN UJI, JAWA BARAT',
            'sumber_data' => 'Data uji periode 2026',
        ], ...$extra]);
    }

    private function url(array $filters = []): string
    {
        return '/peta-wilayah/data?'.http_build_query(['q' => $this->prefix, ...$filters]);
    }

    public function test_actual_online_values_are_read_without_mutation_or_local_dataset(): void
    {
        $stored = Wilayah::where('kode_referensi', '32.01.21.2001')->firstOrFail();
        $before = $stored->getRawOriginal();
        $response = $this->getJson('/peta-wilayah/data?q=MALASARI&kabupaten=3201')->assertOk();
        $record = collect($response->json('list.data'))->firstWhere('code', $stored->kode_referensi);
        $this->assertSame($stored->jumlah_kk_terdampak, $record['electricity']['unserved_families']);
        $this->assertSame($stored->sumber_data, $record['electricity']['source']);
        $this->assertNull($record['electricity']['pln_families']);
        $this->assertSame('NANGGUNG', $record['administration']['subdistrict_name']);
        $this->assertSame($stored->id, $response->json('areas.villages')[$stored->kode_referensi]['id']);
        $this->assertArrayNotHasKey('csv', $record);
        $this->assertArrayNotHasKey('created_by', $record);
        $this->assertNull(config('map.dataset_path'));
        $this->assertSame($before, $stored->fresh()->getRawOriginal());
        $commands = Artisan::all();
        $this->assertArrayNotHasKey('map:prepare', $commands);
        $this->assertArrayNotHasKey('wilayah:sync-csv', $commands);
    }

    public function test_government_update_immediately_changes_map_numbers_condition_and_coordinates(): void
    {
        $region = $this->region();
        $user = new User(['name' => 'Penguji peta', 'email' => bin2hex(random_bytes(8)).'@example.test', 'password' => 'peta-test-password']);
        $user->role = 'pemerintah'; $user->save();
        $payload = ['nama_wilayah' => $region->nama_wilayah, 'tingkat_wilayah' => 'desa_kelurahan',
            'alamat_deskriptif' => $region->alamat_deskriptif, 'latitude' => '-6,9', 'longitude' => '107,6',
            'jumlah_kk_terdampak' => 7, 'kondisi_listrik' => 'akses_terbatas', 'data_per_tanggal' => '2026-11-05'];
        $this->actingAs($user)->putJson('/pemerintah/wilayah/'.$region->id, $payload)->assertOk();
        $this->getJson($this->url())->assertOk()->assertJsonPath('summary.total', 1)
            ->assertJsonPath('list.data.0.electricity.unserved_families', 7)
            ->assertJsonPath('list.data.0.electricity.year', 2026)->assertJsonPath('list.data.0.government.condition', 'akses_terbatas')
            ->assertJsonPath('list.data.0.location.coordinates', [107.6, -6.9])->assertJsonPath('list.data.0.location.source', 'database');
        $this->putJson('/pemerintah/wilayah/'.$region->id, [...$payload, 'jumlah_kk_terdampak' => 0,
            'latitude' => null, 'longitude' => null, 'kondisi_listrik' => null])->assertOk();
        $this->getJson($this->url())->assertOk()->assertJsonPath('summary.no_unserved', 1)
            ->assertJsonPath('summary.located', 0)->assertJsonPath('list.data.0.category', 'no_unserved');
        $this->getJson($this->url(['condition' => 'has_unserved']))->assertJsonPath('summary.total', 0);
    }

    public function test_positive_zero_and_null_remain_distinct_with_search_district_and_location_filters(): void
    {
        $this->region(['jumlah_kk_terdampak' => 23]);
        $this->region(['jumlah_kk_terdampak' => 0]);
        $missing = $this->region(['jumlah_kk_terdampak' => null, 'sumber_data' => null, 'alamat_deskriptif' => 'Alamat tanpa komponen administratif']);
        $this->getJson($this->url())->assertOk()->assertJsonPath('summary.total', 3)
            ->assertJsonPath('summary.has_unserved', 1)->assertJsonPath('summary.no_unserved', 1)
            ->assertJsonPath('summary.unavailable', 1)->assertJsonPath('summary.unlocated', 3);
        foreach (['has_unserved' => 23, 'no_unserved' => 0] as $condition => $families) {
            $this->getJson($this->url(['condition' => $condition, 'kabupaten' => '3299', 'location' => 'missing']))
                ->assertOk()->assertJsonPath('summary.total', 1)
                ->assertJsonPath('list.data.0.electricity.unserved_families', $families)
                ->assertJsonPath('list.data.0.administration.district_name', 'KABUPATEN UJI');
        }
        $this->getJson($this->url(['q' => $missing->nama_wilayah]))->assertOk()
            ->assertJsonPath('list.data.0.electricity.unserved_families', null)
            ->assertJsonPath('list.data.0.electricity.year', null)
            ->assertJsonPath('list.data.0.administration.district_name', null);
        $this->getJson($this->url(['kabupaten' => '3201']))->assertJsonPath('summary.total', 0);
        $this->getJson($this->url(['condition' => 'unknown']))->assertUnprocessable();
    }

    public function test_only_valid_coordinates_from_database_create_markers(): void
    {
        $region = $this->region(['latitude' => 0, 'longitude' => 0]);
        $this->getJson($this->url())->assertOk()->assertJsonPath('summary.unlocated', 1)->assertJsonCount(0, 'features');
        $region->update(['latitude' => -6.9, 'longitude' => 107.6]);
        $this->getJson($this->url())->assertOk()->assertJsonPath('summary.located', 1)->assertJsonPath('list.data.0.location.source', 'database');
        $region->update(['latitude' => null, 'longitude' => null]);
        $this->getJson($this->url())->assertOk()->assertJsonPath('list.data.0.location', null)->assertJsonCount(0, 'features');
    }

    public function test_clustering_viewport_and_pagination_use_online_rows(): void
    {
        for ($index = 0; $index < 16; $index++) $this->region(['latitude' => -6.9, 'longitude' => 107.6 + $index * .00001]);
        $response = $this->getJson($this->url(['zoom' => 8]))->assertOk()->assertJsonPath('summary.located', 16)
            ->assertJsonCount(15, 'list.data')->assertJsonPath('features.0.count', 16);
        $boundary = json_decode(file_get_contents(config('map.boundary_path')), true);
        $this->assertTrue(Geometry::contains($boundary['geometry'], $response->json('features.0.coordinates')));
        $this->getJson($this->url(['page' => 2]))->assertOk()->assertJsonCount(1, 'list.data');
        $this->getJson($this->url(['bbox' => '110,-9,112,-7']))->assertOk()->assertJsonCount(0, 'features')->assertJsonCount(15, 'list.data');
        $this->getJson($this->url(['bbox' => 'invalid']))->assertUnprocessable();
    }

    public function test_database_failure_returns_an_error_without_dataset_fallback(): void
    {
        $this->mock(MapData::class, function ($mock) {
            $mock->shouldReceive('response')->once()->andThrow(new \RuntimeException('private-password-canary'));
        });
        $this->getJson('/peta-wilayah/data')->assertStatus(503)->assertDontSee('private-password-canary');
    }
}
