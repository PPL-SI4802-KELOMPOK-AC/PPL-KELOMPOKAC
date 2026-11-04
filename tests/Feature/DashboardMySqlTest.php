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

    public function test_metrics_and_categories_keep_null_blank_pending_and_real_conditions_distinct(): void
    {
        $baseline = app(DashboardSummary::class)->data();
        foreach ([
            ['kondisi_listrik' => null], ['kondisi_listrik' => ''], ['kondisi_listrik' => '   '],
            ['kondisi_listrik' => 'belum_diverifikasi'],
            ['kondisi_listrik' => 'belum_berlistrik', 'latitude' => -6.9, 'longitude' => 107.6],
            ['kondisi_listrik' => 'sudah_berlistrik', 'latitude' => 0, 'longitude' => 0],
            ['kondisi_listrik' => 'akses_terbatas', 'latitude' => -7, 'longitude' => 108],
            ['kondisi_listrik' => 'Nilai lama'],
        ] as $fixture) Wilayah::create($this->payload($fixture));
        $response = $this->getJson('/dashboard/data')->assertOk()
            ->assertJsonPath('metrics.total', $baseline['metrics']['total'] + 8)
            ->assertJsonPath('metrics.valid_coordinates', $baseline['metrics']['valid_coordinates'] + 2)
            ->assertJsonPath('metrics.condition_filled', $baseline['metrics']['condition_filled'] + 3);
        $categories = collect($response->json('categories'));
        $unknown = collect($baseline['categories'])->firstWhere('id', 'unknown')['count'] ?? 0;
        $this->assertSame($unknown + 3, $categories->firstWhere('id', 'unknown')['count']);
        $this->assertFalse($categories->firstWhere('value', 'belum_diverifikasi')['condition_filled']);
        $this->assertFalse($categories->firstWhere('value', 'Nilai lama')['condition_filled']);
        $this->assertSame($response->json('metrics.total'), $categories->sum('count'));
        $this->assertStringContainsString('tidak sama', $response->json('verification_note'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_government_crud_is_reflected_after_reloading_summary_without_mutating_reference_rows(): void
    {
        $baseline = app(DashboardSummary::class)->data()['metrics'];
        $this->actingAs($this->government());
        $payload = $this->payload(['kondisi_listrik' => 'akses_terbatas', 'latitude' => '-6,9', 'longitude' => '107,6']);
        $id = $this->postJson('/pemerintah/wilayah', $payload)->assertCreated()->json('wilayah.id');
        $this->getJson('/dashboard/data')->assertJsonPath('metrics.total', $baseline['total'] + 1)
            ->assertJsonPath('metrics.valid_coordinates', $baseline['valid_coordinates'] + 1)
            ->assertJsonPath('metrics.condition_filled', $baseline['condition_filled'] + 1);
        $this->putJson('/pemerintah/wilayah/'.$id, [...$payload, 'kondisi_listrik' => null, 'latitude' => null, 'longitude' => null])->assertOk();
        $this->getJson('/dashboard/data')->assertJsonPath('metrics.total', $baseline['total'] + 1)
            ->assertJsonPath('metrics.valid_coordinates', $baseline['valid_coordinates'])->assertJsonPath('metrics.condition_filled', $baseline['condition_filled']);
        $this->deleteJson('/pemerintah/wilayah/'.$id)->assertOk();
        $this->getJson('/dashboard/data')->assertJsonPath('metrics', $baseline);
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
