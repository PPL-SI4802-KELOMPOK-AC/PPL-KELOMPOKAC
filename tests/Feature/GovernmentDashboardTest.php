<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GovernmentDashboard;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GovernmentDashboardTest extends TestCase
{
    use \Tests\Support\StaffAccessFixture;
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.url' => null,
            'app.key' => 'base64:'.base64_encode(str_repeat('g', 32))]);
        DB::purge('sqlite');
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
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
            $t->unsignedBigInteger('user_id')->nullable();
            $t->unsignedBigInteger('wilayah_id')->nullable();
            $t->string('nomor_tiket')->nullable();
            $t->string('ringkasan_pengajuan')->nullable();
            $t->string('status_terkini')->nullable();
            $t->timestamp('status_diperbarui_pada')->nullable();
        });
        Schema::create('lokasi_pengajuan', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('pengajuan_id');
            $t->text('alamat_deskriptif');
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
        });
        Schema::create('bukti_pengajuan', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('pengajuan_id');
            $t->string('mime_type');
            $t->unsignedInteger('ukuran_byte');
        });
        Schema::create('riwayat_status_pengajuan', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('pengajuan_id');
            $t->unsignedBigInteger('diubah_oleh')->nullable();
            $t->string('status_sebelumnya')->nullable();
            $t->text('catatan')->nullable();
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

        $this->grantStaffFixture($user);
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

    public function test_new_submission_links_by_catalog_code_and_appears_in_the_active_period(): void
    {
        $this->travelTo(now()->setDate(2026, 6, 1));
        $this->region(['kode_referensi' => '32.78.01.1003', 'nama_wilayah' => 'Argasari']);
        $region = $this->region(['kode_referensi' => '32.78.01.1004', 'nama_wilayah' => 'Argasari']);
        $applicant = $this->user('masyarakat');
        $this->actingAs($applicant)->post(route('pengajuan.lokasi.store'), [
            'provinsi' => 'Jawa Barat', 'kabupaten' => '3278', 'kecamatan' => 'Cihideung',
            'desa' => '32.78.01.1004', 'alamat' => 'Alamat pengujian',
        ])->assertRedirect(route('pengajuan.lokasi'));
        $draft = DB::table('pengajuan_plts')->where('user_id', $applicant->id)->sole();
        $this->assertEquals($region, $draft->wilayah_id);
        $dashboard = '/pemerintah/dashboard/data?year=2026';
        $government = $this->user();
        $this->actingAs($government)->getJson($dashboard)->assertOk()->assertJsonPath('areas_with_submissions', []);

        DB::table('bukti_pengajuan')->insert(['pengajuan_id' => $draft->id, 'mime_type' => 'image/png', 'ukuran_byte' => 100]);
        $this->actingAs($applicant)->post(route('pengajuan.kirim'), [
            'draft_id' => $draft->id, 'pernyataan' => '1',
        ])->assertRedirect(route('pengajuan.terkirim', $draft->id));
        $this->assertDatabaseHas('pengajuan_plts', ['id' => $draft->id, 'wilayah_id' => $region, 'status_terkini' => 'diajukan']);

        $this->actingAs($government)->getJson($dashboard)->assertOk()
            ->assertJsonPath('areas_with_submissions', ['32.78.01.1004']);
        $this->getJson($dashboard.'&desa=32.78.01.1003&kecamatan=32.78.01&kabupaten=3278')->assertOk()
            ->assertJsonPath('areas_with_submissions', []);
        $this->getJson('/pemerintah/dashboard/wilayah?wilayah=32.78.01.1004&year=2026')->assertOk()
            ->assertJsonPath('detail.linked_submissions', 1);
    }

    public static function masterAtSubmission(): array
    {
        return [
            'master baru tersedia saat kirim' => [false, 1],
            'master tetap tidak ditemukan' => [false, 0],
            'master menjadi ambigu setelah lokasi disimpan' => [true, 1],
        ];
    }

    #[DataProvider('masterAtSubmission')]
    public function test_submission_rechecks_region_without_blocking_unlinked_applications(bool $hasMaster, int $addedMasters): void
    {
        $this->travelTo(now()->setDate(2026, 6, 1));
        $code = '32.78.01.1004';
        $master = ['kode_referensi' => $code, 'kondisi_listrik' => 'akses_terbatas'];
        $originalId = $hasMaster ? $this->region($master) : null;
        $this->region(['kode_referensi' => '32.78.01.1003', 'nama_wilayah' => 'Argasari']);
        $applicant = $this->user('masyarakat');
        $this->actingAs($applicant)->post(route('pengajuan.lokasi.store'), [
            'provinsi' => 'Jawa Barat', 'kabupaten' => '3278', 'kecamatan' => 'Cihideung',
            'desa' => $code, 'alamat' => 'Kampung Uji, RT 01',
        ])->assertRedirect(route('pengajuan.lokasi'));
        $draft = DB::table('pengajuan_plts')->where('user_id', $applicant->id)->sole();
        $this->assertDatabaseHas('pengajuan_plts', ['id' => $draft->id, 'wilayah_id' => $originalId]);
        $newId = $addedMasters === 1 ? $this->region($master) : null;
        $expectedId = $hasMaster ? null : $newId;
        DB::table('bukti_pengajuan')->insert(['pengajuan_id' => $draft->id, 'mime_type' => 'image/png', 'ukuran_byte' => 100]);

        $this->post(route('pengajuan.kirim'), ['draft_id' => $draft->id, 'pernyataan' => '1'])
            ->assertRedirect(route('pengajuan.terkirim', $draft->id));
        $this->assertDatabaseHas('pengajuan_plts', ['id' => $draft->id, 'wilayah_id' => $expectedId, 'status_terkini' => 'diajukan']);
        $sent = DB::table('pengajuan_plts')->find($draft->id);
        $this->get(route('pengajuan.riwayat'))->assertOk()->assertSee($sent->nomor_tiket);

        $this->actingAs($this->user());
        $dashboard = '/pemerintah/dashboard/data?';
        $this->getJson($dashboard.'year=2026')->assertOk()
            ->assertJsonPath('follow_up.waiting', 1)
            ->assertJsonPath('follow_up.unlinked_submissions_global', $expectedId === null ? 1 : 0)
            ->assertJsonPath('areas_with_submissions', $expectedId === null ? [] : [$code]);
        $this->getJson($dashboard.'year=2026&kabupaten=3278&kondisi=akses_terbatas')->assertOk()
            ->assertJsonPath('areas_with_submissions', $expectedId === null ? [] : [$code]);
        $this->getJson($dashboard.'year=2025')->assertOk()->assertJsonPath('areas_with_submissions', []);
        $this->getJson($dashboard.'year=2026&kondisi=unknown')->assertOk()->assertJsonPath('areas_with_submissions', []);
    }

    public function test_polygon_outlines_and_detail_use_the_same_exact_filtered_master_ids(): void
    {
        $zero = '32.78.01.1001';
        $one = '32.78.01.1002';
        $many = '32.78.01.1004';
        $zeroId = $this->region(['kode_referensi' => $zero]);
        $oneId = $this->region(['kode_referensi' => $one]);
        $manyId = $this->region(['kode_referensi' => $many]);
        // Defensive fixture for duplicate master codes with conflicting condition data.
        $otherId = $this->region(['kode_referensi' => $many, 'kondisi_listrik' => 'akses_terbatas']);
        $badId = $this->region(['kode_referensi' => $zero.' ']);
        $unknownCodeId = $this->region(['kode_referensi' => '32.78.99.9999']);
        $this->submission('draft', $zeroId);
        $this->submission('diajukan', $oneId);
        $this->submission('diajukan', $manyId);
        $this->submission('selesai', $manyId);
        $this->submission('diajukan', $zeroId, '2025-12-31 23:59:59');
        $this->submission('diajukan', $badId);
        $this->submission('diajukan', $unknownCodeId);
        $this->submission('diajukan', null);
        $this->submission('diajukan', 9999);

        $this->actingAs($this->user());
        $data = '/pemerintah/dashboard/data?';
        $detail = '/pemerintah/dashboard/wilayah?wilayah=';
        $year = 'year=2026';
        $geo = 'kabupaten=3278&kecamatan=32.78.01&desa='.$many;
        $limited = '&'.$year.'&'.$geo.'&kondisi=akses_terbatas';
        $this->getJson($data.ltrim($limited, '&'))->assertOk()->assertJsonPath('areas_with_submissions', []);
        $this->getJson($detail.$many.$limited)->assertOk()->assertJsonPath('detail.linked_submissions', 0);
        $this->submission('diperiksa', $otherId);
        $this->getJson($data.$year)->assertOk()->assertJsonPath('areas_with_submissions', [$one, $many]);
        foreach ([$zero => 0, $one => 1, $many => 3] as $code => $count) {
            $this->getJson($detail.$code.'&'.$year)->assertOk()->assertJsonPath('detail.linked_submissions', $count);
        }
        $this->getJson($data.'year=2025')->assertOk()->assertJsonPath('areas_with_submissions', [$zero]);
        $this->getJson($data.$year.'&kabupaten=3279')->assertOk()->assertJsonPath('areas_with_submissions', []);
        foreach (['unknown' => 2, 'akses_terbatas' => 1, 'sudah_berlistrik' => null] as $condition => $count) {
            $filters = '&'.$year.'&'.$geo.'&kondisi='.$condition;
            $result = $this->getJson($data.ltrim($filters, '&'))->assertOk()
                ->assertJsonPath('areas_with_submissions', $count === null ? [] : [$many]);
            $this->assertSame($count === null ? 'outside' : 'unserved', $result->json('areas')[$many]);
            $result = $this->getJson($detail.$many.$filters)->assertOk()
                ->assertJsonPath('status', $count === null ? 'outside' : 'available');
            $this->assertSame($count, $result->json('detail.linked_submissions'));
        }
    }

    public function test_village_detail_returns_only_filtered_points_inside_its_polygon(): void
    {
        $region = $this->region(['kode_referensi' => '32.78.01.1004', 'kondisi_listrik' => 'akses_terbatas']);
        $other = $this->region(['kode_referensi' => '32.78.01.1003']);
        $add = function ($lat, $lng, ?int $master, string $date = '2026-06-01', string $status = 'diajukan'): string {
            $id = $this->submission($status, $master, $date.' 00:00:00');
            DB::table('lokasi_pengajuan')->insert([
                'pengajuan_id' => $id, 'alamat_deskriptif' => 'Alamat privat fixture', 'latitude' => $lat, 'longitude' => $lng,
            ]);

            return (string) $id;
        };
        $first = $add(-7.326, 108.1975, $region);
        $second = $add(-7.326, 108.1975, $region, status: 'diperiksa');
        $third = $add(-7.3255, 108.1985, $region);
        $add(null, 108.1975, $region);
        $add('invalid', 108.1975, $region);
        $add('', 108.1975, $region);
        $add(91, 108.1975, $region);
        $add(-6.9175, 107.6191, $region);
        $old = $add(-7.326, 108.1975, $region, '2025-06-01');
        $add(-7.326, 108.1975, $region, status: 'draft');
        $add(-7.326, 108.1975, null);
        $add(-7.326, 108.1975, $other);
        $this->actingAs($this->user());
        $url = '/pemerintah/dashboard/wilayah?wilayah=32.78.01.1004';
        $response = $this->getJson($url.'&year=2026&kondisi=akses_terbatas')->assertOk()
            ->assertJsonPath('detail.linked_submissions', 8)
            ->assertJsonPath('detail.submission_points.0.submitted_at', '2026-06-01 00:00:00')
            ->assertJsonPath('detail.submission_points.1.status', 'Diperiksa')
            ->assertDontSee('Alamat privat fixture');
        $points = $response->json('detail.submission_points');
        $this->assertSame([$first, $second, $third], array_column($points, 'id'));
        $this->assertSame([-7.326, -7.326, -7.3255], array_column($points, 'latitude'));
        $this->getJson($url.'&year=2025')->assertOk()->assertJsonCount(1, 'detail.submission_points')
            ->assertJsonPath('detail.submission_points.0.id', $old);
        $this->getJson($url.'&kondisi=unknown')->assertOk()->assertJsonPath('status', 'outside')->assertJsonPath('detail', null);
    }

    public function test_polygon_detail_uses_government_scope_and_preserves_zero_without_coordinates(): void
    {
        $region = $this->region(['kode_referensi' => '32.78.01.1004', 'latitude' => null,
            'jumlah_kk_terdampak' => 0, 'kondisi_listrik' => 'akses_terbatas']);
        $this->submission('diajukan', $region, '2026-01-01 00:00:00');
        $this->submission('selesai', $region, '2025-01-01 00:00:00');
        DB::table('progres_elektrifikasi')->insert([
            ['wilayah_id' => $region, 'tahap_progres' => 'pendataan', 'tanggal_progres' => '2026-11-01'],
            ['wilayah_id' => $region, 'tahap_progres' => 'catatan lama', 'tanggal_progres' => '2025-11-01'],
        ]);
        $url = '/pemerintah/dashboard/wilayah?wilayah=32.78.01.1004&year=2026';
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs($this->user('masyarakat'))->getJson($url)->assertForbidden();
        $this->actingAs($this->user());
        $response = $this->getJson('/pemerintah/dashboard/data?year=2026&kabupaten=3278')
            ->assertOk()->assertJsonPath('metrics.mapped', 0);
        $this->assertSame(['32.78.01.1004' => 'unserved'], $response->json('areas'));
        $response->assertJsonPath('areas_with_submissions', ['32.78.01.1004']);
        $this->getJson($url)->assertOk()
            ->assertJsonPath('detail.condition', 'Akses listrik terbatas')
            ->assertJsonPath('detail.affected_kk', 0)
            ->assertJsonPath('detail.submissions.0.status', 'Diajukan')
            ->assertJsonCount(1, 'detail.submissions')
            ->assertJsonPath('detail.progress.0.tahap_progres', 'pendataan')
            ->assertJsonCount(1, 'detail.progress');
        $this->getJson($url.'&kondisi=unknown')->assertOk()->assertJsonPath('status', 'outside')->assertJsonPath('detail', null);
        $this->getJson($url.'&kabupaten=3279')->assertOk()->assertJsonPath('status', 'outside')->assertJsonPath('detail', null);
    }

    public function test_status_list_reuses_the_village_scope_and_does_not_require_coordinates(): void
    {
        $master = $this->region(['kode_referensi' => '32.78.01.1004', 'kondisi_listrik' => 'akses_terbatas']);
        $other = $this->region(['kode_referensi' => '32.78.01.1003']);
        $first = $this->submission('diajukan', $master, '2026-01-01 00:00:00');
        $second = $this->submission('diajukan', $master, '2026-02-01 00:00:00');
        $done = $this->submission('selesai', $master);
        $this->submission('draft', $master);
        $this->submission('diajukan', $other);
        $this->submission('diajukan', null);
        $this->submission('diajukan', $master, '2025-01-01 00:00:00');
        DB::table('lokasi_pengajuan')->insert([
            ['pengajuan_id' => $first, 'alamat_deskriptif' => 'Jalan privat, Argasari, Cihideung, Kota Tasikmalaya, Jawa Barat'],
            ['pengajuan_id' => $second, 'alamat_deskriptif' => 'Alamat ganda 1'],
            ['pengajuan_id' => $second, 'alamat_deskriptif' => 'Alamat ganda 2'],
        ]);
        $url = '/pemerintah/dashboard/wilayah?wilayah=32.78.01.1004&year=2026';
        $this->getJson($url.'&status_pengajuan=diajukan')->assertUnauthorized();
        $this->actingAs($this->user('masyarakat'))->getJson($url.'&status_pengajuan=diajukan')->assertForbidden();
        $this->actingAs($this->user());
        $this->getJson($url)->assertOk()->assertJsonPath('detail.linked_submissions', 3)
            ->assertJsonMissingPath('submission_list')->assertJsonPath('detail.submission_points', []);
        $this->getJson($url.'&status_pengajuan=diajukan')->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('submission_list.total', 2)->assertJsonCount(2, 'submission_list.items')
            ->assertJsonPath('submission_list.items.0.id', (string) $second)
            ->assertJsonPath('submission_list.items.0.location', null)
            ->assertJsonPath('submission_list.items.1.id', (string) $first)
            ->assertJsonPath('submission_list.items.1.submitted_at', '2026-01-01 00:00:00')
            ->assertJsonPath('submission_list.items.1.location', 'Argasari · Cihideung · Kota Tasikmalaya')
            ->assertDontSee('Jalan privat')->assertJsonMissingPath('detail');
        $this->getJson($url.'&status_pengajuan=selesai')->assertOk()->assertJsonPath('submission_list.items.0.id', (string) $done);
        $this->getJson($url.'&status_pengajuan=draf')->assertUnprocessable()->assertJsonValidationErrors('status_pengajuan');
        $this->getJson($url.'&status_pengajuan=diajukan&kondisi=unknown')->assertOk()->assertJsonPath('status', 'outside')
            ->assertJsonMissingPath('submission_list');
        for ($i = 0; $i < 9; $i++) {
            $this->submission('diajukan', $master, '2026-03-01 00:00:00');
        }
        $this->getJson($url.'&status_pengajuan=diajukan&halaman=2')->assertOk()
            ->assertJsonPath('submission_list.total', 11)->assertJsonPath('submission_list.page', 2)
            ->assertJsonPath('submission_list.last_page', 2)->assertJsonCount(1, 'submission_list.items')
            ->assertJsonPath('submission_list.items.0.id', (string) $first);
    }

    public function test_polygon_master_survives_a_year_without_linked_activity(): void
    {
        $master = $this->region(['kode_referensi' => '32.78.01.1004', 'jumlah_kk_terdampak' => null, 'kondisi_listrik' => null]);
        $this->region(['kode_referensi' => '32.78.99.9999']);
        $this->submission('diajukan', $master, '2025-03-04 00:00:00');
        $this->submission('diajukan', null, '2026-03-04 00:00:00');
        $this->actingAs($this->user());
        $base = '/pemerintah/dashboard/data?kabupaten=3278';
        $allPeriods = $this->getJson($base)->assertOk()->assertJsonPath('metrics.submitted', 1)
            ->assertJsonPath('follow_up.regions', 1)->assertJsonPath('follow_up.unlinked_submissions_global', 1);
        $year = $this->getJson($base.'&year=2026')->assertOk()->assertJsonPath('metrics.submitted', 0)
            ->assertJsonPath('metrics.mapped', 0)->assertJsonPath('follow_up.regions', 0)
            ->assertJsonPath('follow_up.unlinked_submissions_global', 1);
        $this->assertSame(['32.78.01.1004' => 'needs_data'], $year->json('areas'));
        $this->assertSame($allPeriods->json('areas'), $year->json('areas'));
        $allPeriods->assertJsonPath('areas_with_submissions', ['32.78.01.1004']);
        $year->assertJsonPath('areas_with_submissions', []);
        $excluded = $this->getJson($base.'&year=2026&kondisi=akses_terbatas')->assertOk();
        $this->assertSame(['32.78.01.1004' => 'outside'], $excluded->json('areas'));

        $detail = '/pemerintah/dashboard/wilayah?wilayah=32.78.01.1004&kabupaten=3278';
        $this->getJson($detail)->assertOk()->assertJsonPath('detail.linked_submissions', 1);
        $this->getJson($detail.'&year=2026&kondisi=unknown')->assertOk()->assertJsonPath('status', 'available')
            ->assertJsonPath('detail.condition', null)->assertJsonPath('detail.affected_kk', null)
            ->assertJsonPath('detail.linked_submissions', 0)->assertJsonCount(0, 'detail.submissions')
            ->assertJsonCount(0, 'detail.progress');
        $this->getJson('/pemerintah/dashboard/wilayah?wilayah=32.78.01.1003&year=2026')
            ->assertOk()->assertJsonPath('status', 'missing_master')->assertJsonPath('detail', null);
    }

    public function test_dashboard_and_data_are_protected_by_role_and_active_account(): void
    {
        $this->get('/pemerintah/dashboard')->assertRedirect('/login');
        $this->getJson('/pemerintah/dashboard/data')->assertUnauthorized();
        $this->getJson('/pemerintah/dashboard/administrasi?kabupaten=3278')->assertUnauthorized();
        foreach (['masyarakat', 'admin'] as $role) {
            $this->actingAs($this->user($role))->get('/pemerintah/dashboard')->assertForbidden();
            $this->getJson('/pemerintah/dashboard/data')->assertForbidden();
            $this->getJson('/pemerintah/dashboard/administrasi?kabupaten=3278')->assertForbidden();
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

    public function test_each_geographic_level_restricts_the_dashboard_to_matching_master_regions(): void
    {
        $village = $this->region(['kode_referensi' => '32.78.01.1004']);
        $sameSubdistrict = $this->region(['kode_referensi' => '32.78.01.1003']);
        $sameDistrict = $this->region(['kode_referensi' => '32.78.02.1002']);
        $otherDistrict = $this->region(['kode_referensi' => '32.79.01.1001']);
        $notInCatalog = $this->region(['kode_referensi' => '32.78.01.9999']);
        foreach ([$village, $sameSubdistrict, $otherDistrict, $notInCatalog] as $region) {
            $this->submission('diajukan', $region);
        }

        $this->actingAs($this->user());
        foreach ([
            ['kabupaten=3278', [$village, $sameSubdistrict, $sameDistrict], 2],
            ['kabupaten=3278&kecamatan=32.78.01', [$village, $sameSubdistrict], 2],
            ['kabupaten=3278&kecamatan=32.78.01&desa=32.78.01.1004', [$village], 1],
        ] as [$query, $expectedIds, $expectedSubmissions]) {
            $response = $this->getJson('/pemerintah/dashboard/data?'.$query)->assertOk()
                ->assertJsonPath('metrics.submitted', $expectedSubmissions)
                ->assertJsonPath('metrics.mapped', count($expectedIds))
                ->assertJsonPath('follow_up.regions', count($expectedIds))
                ->assertJsonPath('follow_up.waiting', $expectedSubmissions)
                ->assertJsonCount(6, 'statuses');
            $this->assertSame($expectedIds, array_column($response->json('map.features'), 'id'));
            $this->assertSame($expectedSubmissions, array_sum(array_column($response->json('statuses'), 'count')));
        }
        $this->assertDatabaseCount('wilayah', 5);
        $this->assertDatabaseCount('pengajuan_plts', 4);
    }

    public function test_electricity_filters_distinguish_unknown_condition_zero_target_and_unlinked_submissions(): void
    {
        $recorded = $this->region(['kondisi_listrik' => 'akses_terbatas', 'jumlah_kk_terdampak' => 0]);
        $unknown = $this->region(['jumlah_kk_terdampak' => null]);
        $unmapped = $this->region(['kode_referensi' => '32.78.01.9999', 'alamat_deskriptif' => 'Alamat tidak diketahui',
            'latitude' => null, 'kondisi_listrik' => 'akses_terbatas']);
        foreach ([$recorded, $unknown, $unmapped, null, 999] as $region) {
            $this->submission('diajukan', $region);
        }

        $this->actingAs($this->user());
        $this->getJson('/pemerintah/dashboard/data?year=2026')->assertOk()->assertJsonPath('metrics.submitted', 5);
        $this->getJson('/pemerintah/dashboard/data?kondisi=akses_terbatas')->assertOk()
            ->assertJsonPath('metrics.submitted', 1)->assertJsonPath('follow_up.unlinked_submissions', 0)
            ->assertJsonPath('metrics.target_kk', 0)->assertJsonPath('metrics.served_kk', null)
            ->assertJsonPath('map.features.0.id', $recorded)->assertJsonCount(1, 'map.features');
        $this->getJson('/pemerintah/dashboard/data?kondisi=unknown')->assertOk()
            ->assertJsonPath('metrics.submitted', 1)->assertJsonPath('metrics.target_kk', null)
            ->assertJsonPath('metrics.completed_plts', null)->assertJsonPath('empty', false)
            ->assertJsonPath('map.features.0.id', $unknown)->assertJsonCount(1, 'map.features');
        $this->assertDatabaseHas('pengajuan_plts', ['wilayah_id' => null]);
    }

    public function test_combined_filters_apply_to_current_data_and_allow_an_empty_match(): void
    {
        $selected = $this->region(['kode_referensi' => '32.78.01.1004', 'kondisi_listrik' => 'belum_berlistrik']);
        $otherVillage = $this->region(['kode_referensi' => '32.78.01.1003', 'kondisi_listrik' => 'belum_berlistrik']);
        $withoutLocation = $this->region(['kode_referensi' => '32.78.01.1001', 'kondisi_listrik' => 'belum_berlistrik', 'latitude' => null]);
        $this->submission('diajukan', $selected);
        $this->submission('selesai', $selected, '2025-12-31 23:59:59');
        $this->submission('diperiksa', $otherVillage);
        $this->submission('ditolak');
        DB::table('progres_elektrifikasi')->insert(['wilayah_id' => $withoutLocation, 'tahap_progres' => 'pendataan', 'tanggal_progres' => '2026-06-01']);

        $this->actingAs($this->user());
        $baseUrl = '/pemerintah/dashboard/data?year=2026&kabupaten=3278&kecamatan=32.78.01&kondisi=belum_berlistrik';
        $this->getJson($baseUrl)->assertOk()->assertJsonPath('metrics.submitted', 2)
            ->assertJsonPath('follow_up.regions', 3)->assertJsonPath('follow_up.unlocated', 1)
            ->assertJsonPath('metrics.mapped', 2)->assertJsonCount(2, 'map.features');
        $url = $baseUrl.'&desa=32.78.01.1004';
        $this->getJson($url)->assertOk()->assertJsonPath('period.year', 2026)->assertJsonPath('empty', false)
            ->assertJsonPath('metrics.submitted', 1)->assertJsonPath('statuses.0.count', 1)
            ->assertJsonPath('statuses.4.count', 0)->assertJsonPath('follow_up.waiting', 1)
            ->assertJsonPath('metrics.mapped', 1)->assertJsonPath('metrics.target_kk', 25)
            ->assertJsonPath('map.features.0.id', $selected)->assertJsonCount(1, 'map.features');

        DB::table('wilayah')->where('id', $selected)->update(['kondisi_listrik' => 'sudah_berlistrik']);
        $this->getJson($url)->assertOk()->assertJsonPath('empty', true)
            ->assertJsonPath('metrics.submitted', 0)->assertJsonPath('metrics.mapped', 0)
            ->assertJsonPath('metrics.target_kk', 0)->assertJsonPath('metrics.served_kk', null)
            ->assertJsonPath('follow_up.regions', 0)->assertJsonCount(0, 'map.features');
    }

    public function test_filter_validation_checks_hierarchy_and_options_are_served_for_the_selected_district(): void
    {
        $this->actingAs($this->user());
        foreach ([
            'kabupaten=3299' => 'kabupaten',
            'kecamatan=32.78.01' => 'kabupaten',
            'kabupaten=3278&kecamatan=32.79.01' => 'kecamatan',
            'kabupaten=3278&desa=32.78.01.1004' => 'kecamatan',
            'kabupaten=3278&kecamatan=32.78.01&desa=32.78.02.1002' => 'desa',
            'kabupaten=3278&kecamatan=32.78.01&desa=32.78.01.9999' => 'desa',
            'kondisi=tidak_dikenal' => 'kondisi',
            'kondisi[]=unknown' => 'kondisi',
        ] as $query => $field) {
            $this->getJson('/pemerintah/dashboard/data?'.$query)->assertUnprocessable()->assertJsonValidationErrors($field);
        }

        $this->get('/pemerintah/dashboard?kabupaten=3299')->assertUnprocessable()
            ->assertSee('Kabupaten/kota tidak tersedia di katalog wilayah.')->assertSee('data-state="error"', false);
        $this->getJson('/pemerintah/dashboard/administrasi?kabupaten=3299')->assertUnprocessable()->assertJsonValidationErrors('kabupaten');
        $this->getJson('/pemerintah/dashboard/administrasi?kabupaten=3278')->assertOk()
            ->assertJsonFragment(['32.78.01' => 'Cihideung'])
            ->assertJsonFragment(['code' => '32.78.01.1004', 'name' => 'Argasari', 'subdistrict' => '32.78.01']);
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
