<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wilayah;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Opt-in: transaksi pada skema MySQL asli, tanpa migrate/seed/truncate.
 * Semua baris yang ditulis adalah fixture baru dan di-rollback di tearDown.
 */
class WilayahManagementTest extends TestCase
{
    private bool $transactionStarted = false;
    private User $government;
    private string $prefix;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('PBI01_MYSQL_TESTS') !== '1') {
            $this->markTestSkipped('Set PBI01_MYSQL_TESTS=1 untuk uji transaksi MySQL pada skema TerangDesa.');
        }
        $this->assertSame('mysql', DB::connection()->getDriverName());
        DB::beginTransaction();
        $this->transactionStarted = true;
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('t', 32))]);
        $this->prefix = 'PBI01-'.Str::random(12);
        $this->government = $this->user('pemerintah');
    }

    protected function tearDown(): void
    {
        if ($this->transactionStarted) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    private function user(string $role): User
    {
        $user = new User(['name' => 'Penguji PBI-01', 'email' => Str::uuid().'@example.test', 'password' => 'uji-terangdesa-123']);
        $user->role = $role;
        $user->save();

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'nama_wilayah' => $this->prefix.' Desa Uji',
            'kode_referensi' => $this->prefix,
            'tingkat_wilayah' => 'desa_kelurahan',
            'alamat_deskriptif' => 'Alamat uji, Kecamatan Uji, Kabupaten Uji',
            'latitude' => null, 'longitude' => null, 'kondisi_listrik' => null,
            'jumlah_kk_terdampak' => null, 'jumlah_warga_terdampak' => null,
            'sumber_data' => 'Fixture transaksi PBI-01', 'data_per_tanggal' => null,
        ], $overrides);
    }

    private function region(array $overrides = []): Wilayah
    {
        $region = new Wilayah($this->payload($overrides));
        $region->created_by = $this->government->id;
        $region->save();

        return $region;
    }

    public function test_guests_and_other_roles_cannot_access_any_management_endpoint(): void
    {
        $region = $this->region();
        $this->get('/pemerintah/wilayah')->assertRedirect('/login');
        foreach ([null, 'masyarakat', 'admin'] as $role) {
            if ($role) {
                $this->actingAs($this->user($role));
            }
            $status = $role ? 403 : 401;
            $this->getJson('/pemerintah/wilayah')->assertStatus($status);
            $this->getJson('/pemerintah/wilayah/data')->assertStatus($status);
            $this->getJson('/pemerintah/wilayah/'.$region->id)->assertStatus($status);
            $this->postJson('/pemerintah/wilayah', $this->payload())->assertStatus($status);
            $this->putJson('/pemerintah/wilayah/'.$region->id, $this->payload())->assertStatus($status);
            $this->deleteJson('/pemerintah/wilayah/'.$region->id)->assertStatus($status);
        }
    }

    public function test_create_and_reload_preserve_nulls_and_authenticated_creator(): void
    {
        $this->actingAs($this->government);
        $id = $this->postJson('/pemerintah/wilayah', $this->payload(['created_by' => 999999, 'id' => 999999]))
            ->assertCreated()->assertJsonPath('wilayah.created_by', $this->government->id)
            ->assertJsonPath('wilayah.latitude', null)->assertJsonPath('wilayah.kondisi_listrik', null)
            ->json('wilayah.id');
        $this->getJson('/pemerintah/wilayah/'.$id)->assertOk()
            ->assertJsonPath('jumlah_kk_terdampak', null)->assertJsonPath('data_per_tanggal', null);
        $this->assertDatabaseHas('wilayah', ['id' => $id, 'nama_wilayah' => $this->payload()['nama_wilayah'], 'created_by' => $this->government->id]);
    }

    public function test_update_normalizes_decimal_commas_and_keeps_original_creator_and_submission(): void
    {
        $region = $this->region();
        $ticket = DB::table('pengajuan_plts')->insertGetId([
            'nomor_tiket' => $this->prefix, 'user_id' => $this->government->id,
            'wilayah_id' => $region->id, 'ringkasan_pengajuan' => 'Kebutuhan asli warga',
        ]);
        DB::table('lokasi_pengajuan')->insert(['pengajuan_id' => $ticket, 'alamat_deskriptif' => 'Lokasi asli', 'latitude' => -7, 'longitude' => 108]);
        DB::table('penginputan_plts')->insert(['pengajuan_id' => $ticket, 'kondisi_wilayah' => 'Isian asli', 'jumlah_kk_terdampak' => 8, 'estimasi_kebutuhan_daya_watt' => 1000]);
        $editor = $this->user('pemerintah');
        $this->actingAs($editor)->putJson('/pemerintah/wilayah/'.$region->id, $this->payload([
            'nama_wilayah' => 'Nama baru', 'latitude' => '-6,1234567', 'longitude' => '107,1234567',
            'jumlah_kk_terdampak' => 0, 'jumlah_warga_terdampak' => 12, 'kondisi_listrik' => 'akses_terbatas',
            'created_by' => $editor->id, 'data_per_tanggal' => '2026-11-02',
        ]))->assertOk()->assertJsonPath('wilayah.created_by', $this->government->id);
        $this->getJson('/pemerintah/wilayah/'.$region->id)->assertJsonPath('latitude', '-6.1234567')->assertJsonPath('jumlah_kk_terdampak', 0);
        $this->assertDatabaseHas('lokasi_pengajuan', ['pengajuan_id' => $ticket, 'alamat_deskriptif' => 'Lokasi asli', 'latitude' => -7]);
        $this->assertDatabaseHas('penginputan_plts', ['pengajuan_id' => $ticket, 'kondisi_wilayah' => 'Isian asli', 'jumlah_kk_terdampak' => 8]);
    }

    public function test_duplicate_reference_is_rejected_but_the_same_region_can_keep_its_code(): void
    {
        $region = $this->region();
        $this->actingAs($this->government)->postJson('/pemerintah/wilayah', $this->payload())
            ->assertUnprocessable()->assertJsonValidationErrors('kode_referensi')
            ->assertJsonPath('errors.kode_referensi.0', 'Kode referensi sudah digunakan oleh wilayah lain.');
        $this->putJson('/pemerintah/wilayah/'.$region->id, $this->payload())->assertOk();
        $this->postJson('/pemerintah/wilayah', $this->payload(['kode_referensi' => null]))->assertCreated();
        $this->postJson('/pemerintah/wilayah', $this->payload(['kode_referensi' => null]))->assertCreated();
    }

    public function test_invalid_fields_are_rejected_without_writing_rows(): void
    {
        $this->actingAs($this->government);
        $before = DB::table('wilayah')->count();
        $cases = [
            ['nama_wilayah' => '', 'tingkat_wilayah' => '', 'alamat_deskriptif' => ''],
            ['latitude' => '-6,2', 'longitude' => null],
            ['latitude' => null, 'longitude' => '107,1'],
            ['latitude' => 91, 'longitude' => -181],
            ['latitude' => '-6,12,34', 'longitude' => '107.1'],
            ['latitude' => '-6.12345678', 'longitude' => '107.1'],
            ['jumlah_kk_terdampak' => -1, 'jumlah_warga_terdampak' => 1.5],
            ['jumlah_kk_terdampak' => 4294967296, 'jumlah_warga_terdampak' => 'abc'],
            ['kondisi_listrik' => 'terpasang'],
            ['data_per_tanggal' => '2024'], ['data_per_tanggal' => '2026-02-30'],
        ];
        foreach ($cases as $invalid) {
            $this->postJson('/pemerintah/wilayah', $this->payload($invalid))->assertUnprocessable();
        }
        $this->assertSame($before, DB::table('wilayah')->count());
    }

    public function test_search_filter_and_backend_pagination_work_together(): void
    {
        for ($i = 0; $i < 18; $i++) {
            $this->region(['kode_referensi' => $this->prefix.'-'.$i, 'nama_wilayah' => $this->prefix.' '.sprintf('%02d', $i)]);
        }
        $this->region(['kode_referensi' => $this->prefix.'-known', 'kondisi_listrik' => 'sudah_berlistrik']);
        $this->actingAs($this->government)->getJson('/pemerintah/wilayah/data?'.http_build_query(['q' => $this->prefix, 'kondisi' => 'unknown']))
            ->assertOk()->assertJsonPath('total', 18)->assertJsonCount(15, 'data')->assertJsonPath('last_page', 2);
        $this->getJson('/pemerintah/wilayah/data?'.http_build_query(['q' => $this->prefix, 'kondisi' => 'unknown', 'page' => 2]))
            ->assertJsonCount(3, 'data')->assertJsonPath('current_page', 2);
        $this->getJson('/pemerintah/wilayah/data?'.http_build_query(['q' => $this->prefix, 'kondisi' => 'sudah_berlistrik']))->assertJsonPath('total', 1);
        $this->getJson('/pemerintah/wilayah/data?'.http_build_query(['q' => 'Kabupaten Uji '.$this->prefix]))->assertJsonPath('total', 0);
        $this->region(['kode_referensi' => $this->prefix.'-address', 'nama_wilayah' => 'Lain', 'alamat_deskriptif' => 'Kabupaten '.$this->prefix]);
        $this->getJson('/pemerintah/wilayah/data?'.http_build_query(['q' => 'Kabupaten '.$this->prefix]))->assertJsonPath('total', 1);
        $this->getJson('/pemerintah/wilayah/data?q=%25'.$this->prefix)->assertJsonPath('total', 0);
        $this->getJson('/pemerintah/wilayah/data?kondisi=terpasang')->assertUnprocessable();
    }

    public function test_unreferenced_region_can_be_deleted(): void
    {
        $region = $this->region();
        $this->actingAs($this->government)->deleteJson('/pemerintah/wilayah/'.$region->id)->assertOk();
        $this->assertDatabaseMissing('wilayah', ['id' => $region->id]);
    }

    public function test_submissions_and_progress_block_deletion_and_database_enforces_restrict(): void
    {
        foreach (['pengajuan_plts', 'progres_elektrifikasi'] as $index => $table) {
            $region = $this->region(['kode_referensi' => $this->prefix.'-fk'.$index]);
            $values = $table === 'pengajuan_plts'
                ? ['nomor_tiket' => $this->prefix, 'user_id' => $this->government->id, 'ringkasan_pengajuan' => 'Uji FK']
                : ['dicatat_oleh' => $this->government->id, 'tahap_progres' => 'uji', 'tanggal_progres' => '2026-11-02'];
            $relation = DB::table($table)->insertGetId(['wilayah_id' => $region->id, ...$values]);
            $this->actingAs($this->government)->deleteJson('/pemerintah/wilayah/'.$region->id)->assertConflict()
                ->assertJsonPath('message', 'Wilayah masih digunakan oleh data pengajuan atau progres sehingga belum dapat dihapus.');
            $this->assertDatabaseHas($table, ['id' => $relation, 'wilayah_id' => $region->id]);
            try {
                DB::table('wilayah')->where('id', $region->id)->delete();
                $this->fail('FK harus menolak penghapusan langsung.');
            } catch (QueryException $exception) {
                $this->assertSame(1451, $exception->errorInfo[1]);
            }
        }
    }

    public function test_real_login_logout_and_csrf_protection(): void
    {
        $this->post('/login', ['email' => $this->government->email, 'password' => 'salah'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/login', ['email' => $this->government->email, 'password' => 'uji-terangdesa-123'])->assertRedirect('/pemerintah/wilayah');
        $this->assertAuthenticatedAs($this->government);
        $this->get('/pemerintah/wilayah')->assertOk()->assertSee('Kelola data wilayah')->assertSee($this->government->name)->assertDontSee('ambient__cursor');
        // Aktifkan pemeriksaan CSRF yang biasanya dilewati Laravel di lingkungan testing.
        $this->app['env'] = 'local';
        $this->postJson('/pemerintah/wilayah', $this->payload())->assertStatus(419);
        $token = $this->app['session']->token();
        $this->withHeader('X-CSRF-TOKEN', $token)->postJson('/pemerintah/wilayah', $this->payload())->assertCreated();
        $this->withHeader('X-CSRF-TOKEN', $token)->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_database_failures_do_not_expose_connection_details_to_browser(): void
    {
        config(['app.debug' => true]);
        $this->actingAs($this->government);
        DB::listen(function ($event) {
            if (str_contains($event->sql, 'wilayah')) {
                throw new QueryException('mysql', 'SELECT private-db-canary', [], new \PDOException('private-password-canary'));
            }
        });
        $this->getJson('/pemerintah/wilayah/data')->assertStatus(503)
            ->assertJsonPath('message', 'Database belum dapat diakses. Periksa layanan MySQL dan konfigurasi koneksi, lalu coba kembali.')
            ->assertDontSee('private-db-canary')->assertDontSee('private-password-canary');
    }

    public function test_map_data_requests_do_not_consume_login_rate_limit(): void
    {
        $this->mock(\App\Services\Map\MapData::class, function ($mock) {
            $mock->shouldReceive('response')->times(8)->andReturn([]);
        });
        for ($i = 0; $i < 8; $i++) {
            $this->getJson('/peta-wilayah/data')->assertOk();
        }
        $this->post('/login', ['email' => $this->government->email, 'password' => 'uji-terangdesa-123'])->assertRedirect('/pemerintah/wilayah');
        $this->assertAuthenticatedAs($this->government);
    }
}
