<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wilayah;
use App\Services\DashboardSummary;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardMySqlTest extends TestCase
{
    private bool $transactionStarted = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('PBI01_MYSQL_TESTS') !== '1') {
            $this->markTestSkipped('Set PBI01_MYSQL_TESTS=1 untuk menguji dashboard pada MySQL dengan fixture transaksi.');
        }
        $this->assertSame('mysql', DB::connection()->getDriverName());
        DB::beginTransaction(); $this->transactionStarted = true;
        config(['app.key' => 'base64:'.base64_encode(str_repeat('t', 32))]);
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        if ($this->transactionStarted) DB::rollBack();
        parent::tearDown();
    }

    private function payload(array $extra = []): array
    {
        return [...['nama_wilayah' => 'DASHBOARD-'.bin2hex(random_bytes(6)), 'tingkat_wilayah' => 'desa_kelurahan',
            'alamat_deskriptif' => 'Alamat fixture dashboard', 'kode_referensi' => 'DASH-'.bin2hex(random_bytes(6))], ...$extra];
    }

    private function government(): User
    {
        $user = new User(['name' => 'Penguji dashboard', 'email' => bin2hex(random_bytes(8)).'@example.test', 'password' => 'dashboard-test-password']);
        $user->role = 'pemerintah'; $user->save();

        return $user;
    }

    public function test_family_counts_keep_positive_zero_and_null_separate_from_manual_condition(): void
    {
        $baseline = app(DashboardSummary::class)->data();
        foreach ([
            ['kondisi_listrik' => 'sudah_berlistrik', 'jumlah_kk_terdampak' => null],
            ['kondisi_listrik' => '', 'jumlah_kk_terdampak' => 0],
            ['kondisi_listrik' => '   ', 'jumlah_kk_terdampak' => 5],
            ['kondisi_listrik' => 'belum_diverifikasi', 'jumlah_kk_terdampak' => 9],
            ['kondisi_listrik' => 'belum_berlistrik', 'jumlah_kk_terdampak' => 12],
            ['kondisi_listrik' => 'sudah_berlistrik', 'jumlah_kk_terdampak' => 0],
            ['kondisi_listrik' => 'akses_terbatas', 'jumlah_kk_terdampak' => null],
            ['kondisi_listrik' => 'Nilai lama', 'jumlah_kk_terdampak' => 1],
        ] as $fixture) Wilayah::create($this->payload($fixture));
        $response = $this->getJson('/dashboard/data')->assertOk()
            ->assertJsonPath('metrics.total', $baseline['metrics']['total'] + 8)
            ->assertJsonPath('metrics.unserved_kk', ($baseline['metrics']['unserved_kk'] ?? 0) + 27)
            ->assertJsonPath('metrics.served_kk', null)
            ->assertJsonPath('coverage.kk_recorded', $baseline['coverage']['kk_recorded'] + 6)
            ->assertJsonPath('coverage.kk_unknown', $baseline['coverage']['kk_unknown'] + 2);
        $categories = collect($response->json('categories'));
        foreach (['has_unserved' => 4, 'no_unserved' => 2, 'unavailable' => 2] as $key => $increment) {
            $old = collect($baseline['categories'])->firstWhere('id', $key)['count'] ?? 0;
            $this->assertSame($old + $increment, $categories->firstWhere('id', $key)['count']);
        }
        $this->assertNull($categories->firstWhere('id', 'unavailable')['kk_total']);
        $this->assertSame(0, $categories->firstWhere('id', 'no_unserved')['kk_total']);
        $this->assertSame($response->json('metrics.total'), $categories->sum('count'));
        $this->assertArrayNotHasKey('condition_filled', $response->json('metrics'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_government_crud_changes_family_summary_and_preserves_original_rows(): void
    {
        $baseline = app(DashboardSummary::class)->data();
        $this->actingAs($this->government());
        $payload = $this->payload(['kondisi_listrik' => 'akses_terbatas', 'jumlah_kk_terdampak' => 7]);
        $id = $this->postJson('/pemerintah/wilayah', $payload)->assertCreated()->json('wilayah.id');
        $this->getJson('/dashboard/data')->assertJsonPath('metrics.total', $baseline['metrics']['total'] + 1)
            ->assertJsonPath('metrics.unserved_kk', ($baseline['metrics']['unserved_kk'] ?? 0) + 7)
            ->assertJsonPath('metrics.served_kk', null);
        $this->putJson('/pemerintah/wilayah/'.$id, [...$payload, 'jumlah_kk_terdampak' => 0])->assertOk();
        $this->getJson('/dashboard/data')->assertJsonPath('metrics.unserved_kk', $baseline['metrics']['unserved_kk']);
        $this->putJson('/pemerintah/wilayah/'.$id, [...$payload, 'jumlah_kk_terdampak' => null])->assertOk();
        $this->getJson('/dashboard/data')->assertJsonPath('metrics.unserved_kk', $baseline['metrics']['unserved_kk'])
            ->assertJsonPath('coverage.kk_unknown', $baseline['coverage']['kk_unknown'] + 1);
        $this->deleteJson('/pemerintah/wilayah/'.$id)->assertOk();
        $this->getJson('/dashboard/data')->assertJsonPath('metrics', $baseline['metrics'])->assertJsonPath('coverage', $baseline['coverage']);
    }

    public function test_summary_requests_do_not_consume_login_attempts(): void
    {
        $user = $this->government();
        $this->mock(DashboardSummary::class, fn ($mock) => $mock->shouldReceive('data')->times(8)->andReturn([]));
        for ($i = 0; $i < 8; $i++) $this->getJson('/dashboard/data')->assertOk();
        $this->post('/login', ['email' => $user->email, 'password' => 'dashboard-test-password'])->assertRedirect('/pemerintah/wilayah');
        $this->assertAuthenticatedAs($user);
    }
}
