<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GovernmentDashboard;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GovernmentDashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'app.key' => 'base64:'.base64_encode(str_repeat('g', 32))]);
        DB::purge('sqlite');
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->withoutVite();
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email');
            $t->string('password');
            $t->string('role');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('wilayah', function (Blueprint $t) {
            $t->id();
            $t->string('kode_referensi')->nullable();
            $t->string('nama_wilayah');
            $t->text('alamat_deskriptif');
            $t->text('latitude')->nullable();
            $t->text('longitude')->nullable();
            $t->string('kondisi_listrik')->nullable();
            $t->integer('jumlah_kk_terdampak')->nullable();
        });
        Schema::create('pengajuan_plts', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('wilayah_id')->nullable();
            $t->string('status_terkini')->nullable();
            $t->timestamp('status_diperbarui_pada')->nullable();
        });
        Schema::create('riwayat_status_pengajuan', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('pengajuan_id');
            $t->string('status_baru');
            $t->string('jenis_tindakan');
            $t->timestamp('created_at');
        });
        Schema::create('progres_elektrifikasi', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('wilayah_id');
            $t->unsignedBigInteger('pengajuan_id')->nullable();
            $t->string('tahap_progres');
            $t->date('tanggal_progres');
            $t->text('catatan_progres')->nullable();
        });
    }

    private function user(string $role = 'pemerintah', bool $active = true): User
    {
        $user = new User(['name' => 'Petugas Uji', 'email' => $role.'@example.test', 'password' => 'password-khusus-uji']);
        $user->role = $role;
        $user->is_active = $active;
        $user->save();

        return $user;
    }

    private function region(array $extra = []): int
    {
        return DB::table('wilayah')->insertGetId($extra + ['nama_wilayah' => 'Mekarjaya', 'alamat_deskriptif' => 'Mekarjaya, Kabupaten Bandung, Jawa Barat',
            'kode_referensi' => null, 'latitude' => '-6.9175', 'longitude' => '107.6191', 'jumlah_kk_terdampak' => 25, 'kondisi_listrik' => null]);
    }

    private function submission(string $status = 'diajukan', ?int $region = null, ?string $date = '2026-11-01 00:00:00'): int
    {
        $id = DB::table('pengajuan_plts')->insertGetId(['status_terkini' => $status, 'wilayah_id' => $region, 'status_diperbarui_pada' => '2030-01-01 00:00:00']);
        if ($date && $status !== 'draft') {
            DB::table('riwayat_status_pengajuan')->insert(['pengajuan_id' => $id, 'status_baru' => 'diajukan', 'jenis_tindakan' => 'pengajuan_baru', 'created_at' => $date]);
        }

        return $id;
    }

    public function test_dashboard_and_data_are_protected_by_role_and_active_account(): void
    {
        $this->get('/pemerintah/dashboard')->assertRedirect('/login');
        $this->getJson('/pemerintah/dashboard/data')->assertUnauthorized();
        foreach (['masyarakat', 'admin'] as $role) {
            $this->actingAs($this->user($role))->get('/pemerintah/dashboard')->assertForbidden();
            $this->getJson('/pemerintah/dashboard/data')->assertForbidden();
        }
        $this->actingAs($this->user())->get('/pemerintah/dashboard')->assertOk()->assertSee('Gambaran layanan elektrifikasi')
            ->assertSee('id="sidebar"', false)->assertSee('Pemerintah')->assertSee('data-government-dashboard', false);
        $inactive = $this->user('pemerintah', false);
        $this->actingAs($inactive)->getJson('/pemerintah/dashboard/data')->assertUnauthorized();
    }

    public function test_six_statuses_reconcile_with_total_excluding_draft_and_do_not_duplicate_regions(): void
    {
        $id = $this->region();
        foreach (array_keys(GovernmentDashboard::STATUSES) as $status) {
            $this->submission($status, $id);
        }
        $this->submission('draft', $id);
        $response = $this->actingAs($this->user())->getJson('/pemerintah/dashboard/data')->assertOk()
            ->assertJsonPath('metrics.submitted', 6)->assertJsonPath('metrics.mapped', 1)->assertJsonPath('metrics.target_kk', 25)
            ->assertJsonCount(6, 'statuses')->assertJsonCount(1, 'map.features');
        $this->assertSame(6, array_sum(array_column($response->json('statuses'), 'count')));
        $response->assertHeader('Cache-Control', 'no-store, private');
        $this->assertArrayNotHasKey('created_by', $response->json('map.features.0.properties'));
    }

    public function test_period_uses_first_submission_not_status_update_and_progress_cohort_is_distinct(): void
    {
        $a = $this->region();
        $b = $this->region(['nama_wilayah' => 'Desa Progres']);
        $this->region(['nama_wilayah' => 'Desa Lama']);
        $id = $this->submission('selesai', $a, '2025-12-31 23:59:59');
        DB::table('riwayat_status_pengajuan')->insert(['pengajuan_id' => $id, 'status_baru' => 'diajukan', 'jenis_tindakan' => 'pengajuan_baru', 'created_at' => '2026-01-01 00:00:00']);
        $this->submission('diajukan', $a, '2026-01-01 00:00:00');
        $this->submission('diperiksa', $a, '2027-01-01 00:00:00');
        foreach ([1, 2] as $n) {
            DB::table('progres_elektrifikasi')->insert(['wilayah_id' => $b, 'tahap_progres' => 'pendataan', 'tanggal_progres' => '2026-06-01']);
        }
        $data = app(GovernmentDashboard::class)->data(2026);
        $this->assertSame(1, $data['metrics']['submitted']);
        $this->assertSame(2, $data['metrics']['mapped']);
        $this->assertSame(50, $data['metrics']['target_kk']);
        $this->assertSame(1, $data['follow_up']['waiting']);
        $this->assertSame(0, $data['statuses'][4]['count']);
    }

    public function test_missing_submission_dates_are_disclosed_and_not_invented_from_status_time(): void
    {
        $this->submission('diajukan', null, null);
        $all = app(GovernmentDashboard::class)->data();
        $this->assertSame(1, $all['metrics']['submitted']);
        $this->assertSame(1, $all['follow_up']['unlinked_submissions']);
        $this->assertStringContainsString('tidak memiliki riwayat', implode(' ', $all['notes']));
        $this->assertSame(0, app(GovernmentDashboard::class)->data(2030)['metrics']['submitted']);
    }

    public function test_actual_progress_schema_cannot_be_used_to_invent_served_kk_or_installed_plts(): void
    {
        $region = $this->region();
        foreach (['selesai', 'plts_terpasang', 'selesai'] as $stage) {
            DB::table('progres_elektrifikasi')->insert(['wilayah_id' => $region, 'tahap_progres' => $stage,
                'tanggal_progres' => '2026-01-01', 'catatan_progres' => '85 KK (teks bebas bukan kolom realisasi)']);
        }
        $data = app(GovernmentDashboard::class)->data();
        foreach (['served_kk', 'served_percent', 'completed_plts'] as $key) {
            $this->assertNull($data['metrics'][$key]);
        }
        $this->assertNull($data['follow_up']['installed']);
        $this->assertSame(25, $data['metrics']['target_kk']);
        $this->assertSame('unserved', $data['map']['features'][0]['properties']['category']);
    }

    public function test_null_text_out_of_range_and_outside_jabar_coordinates_are_not_mapped(): void
    {
        $this->region();
        foreach ([['latitude' => null], ['latitude' => 'salah'], ['latitude' => '91'], ['latitude' => '0', 'longitude' => '0']] as $extra) {
            $this->region($extra);
        }
        $data = app(GovernmentDashboard::class)->data();
        $this->assertSame(1, $data['metrics']['mapped']);
        $this->assertCount(1, $data['map']['features']);
        $this->assertSame(4, $data['follow_up']['unlocated']);
        $this->assertSame(5, $data['follow_up']['regions']);
    }

    public function test_empty_zero_and_missing_target_are_distinct(): void
    {
        $data = app(GovernmentDashboard::class)->data();
        $this->assertTrue($data['empty']);
        $this->assertSame(0, $data['metrics']['submitted']);
        $this->assertSame(0, $data['metrics']['target_kk']);
        $this->assertNull($data['metrics']['served_kk']);
        $this->region(['jumlah_kk_terdampak' => 0]);
        $data = app(GovernmentDashboard::class)->data();
        $this->assertFalse($data['empty']);
        $this->assertSame(0, $data['metrics']['target_kk']);
        $this->assertNull($data['metrics']['served_percent']);
        $this->assertSame('needs_data', $data['map']['features'][0]['properties']['category']);
        $this->region(['jumlah_kk_terdampak' => null]);
        $this->assertNull(app(GovernmentDashboard::class)->data()['metrics']['target_kk']);
    }

    public function test_requests_always_read_new_progress_region_and_submission_data_without_stale_cache(): void
    {
        $this->actingAs($this->user());
        $this->getJson('/pemerintah/dashboard/data')->assertJsonPath('metrics.submitted', 0);
        $region = $this->region();
        $this->submission('diajukan', $region);
        $this->getJson('/pemerintah/dashboard/data')->assertJsonPath('metrics.submitted', 1)->assertJsonPath('metrics.target_kk', 25);
        DB::table('wilayah')->where('id', $region)->update(['jumlah_kk_terdampak' => 42]);
        $this->getJson('/pemerintah/dashboard/data')->assertJsonPath('metrics.target_kk', 42);
    }

    public function test_unmapped_status_is_reported_instead_of_presenting_inconsistent_totals(): void
    {
        $this->submission('status_lama');
        $this->actingAs($this->user())->getJson('/pemerintah/dashboard/data')->assertStatus(503)->assertSee('Pemetaan status perlu diperiksa');
    }

    public function test_invalid_period_and_data_failure_do_not_expose_secrets(): void
    {
        $this->actingAs($this->user());
        foreach (['abc', '1800', '2200'] as $year) {
            $this->getJson('/pemerintah/dashboard/data?year='.$year)->assertUnprocessable();
        }
        $this->mock(GovernmentDashboard::class, fn ($mock) => $mock->shouldReceive('data')->once()->andThrow(new \RuntimeException('secret-password private-sql')));
        $this->getJson('/pemerintah/dashboard/data')->assertStatus(503)->assertDontSee('secret-password')->assertDontSee('private-sql');
    }
}
