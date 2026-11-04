<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wilayah;
use App\Services\DashboardSummary;
use App\Services\WilayahDatasetSync;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WilayahDatasetSyncTest extends TestCase
{
    private bool $transactionStarted = false;
    private array $files = [];
    private int $sequence = 9800;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('PBI01_MYSQL_TESTS') !== '1') $this->markTestSkipped('Memerlukan opt-in transaksi MySQL.');
        $this->assertSame('mysql', DB::connection()->getDriverName());
        DB::beginTransaction(); $this->transactionStarted = true;
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        if ($this->transactionStarted) DB::rollBack();
        foreach ($this->files as $file) @unlink($file);
        parent::tearDown();
    }

    private function region(array $extra = []): Wilayah
    {
        return Wilayah::create([...['kode_referensi' => '32.99.98.'.(++$this->sequence),
            'nama_wilayah' => 'SYNC-'.bin2hex(random_bytes(6)), 'tingkat_wilayah' => 'desa_kelurahan',
            'alamat_deskriptif' => 'Fixture sinkronisasi', 'sumber_data' => WilayahDatasetSync::LEGACY_SOURCE], ...$extra]);
    }

    private function dataset(array $pairs): array
    {
        $records = [];
        foreach ($pairs as [$region, $families]) {
            $records[$region->kode_referensi] = ['kemendagri_code' => $region->kode_referensi,
                'year' => 2024, 'unit' => 'KELUARGA', 'belum_listrik' => $families, 'pln' => 9999];
        }
        return ['year' => 2024, 'source_sha256' => 'fixture-only', 'report' => ['duplicate_codes' => 0], 'records' => $records];
    }

    private function runSync(array $dataset, bool $apply): array
    {
        $path = storage_path('framework/testing/sync-'.bin2hex(random_bytes(6)).'.json');
        $this->files[] = $path;
        return app(WilayahDatasetSync::class)->run($dataset, $apply, $path);
    }

    public function test_preview_then_apply_preserves_zero_null_units_and_other_fields_and_is_idempotent(): void
    {
        $positive = $this->region(['latitude' => -6.9, 'longitude' => 107.6]);
        $zero = $this->region();
        $missing = $this->region();
        $dataset = $this->dataset([[$positive, 12], [$zero, 0], [$missing, null]]);
        $report = $this->runSync($dataset, false);
        $this->assertSame(2, $report['changed_rows']);
        $this->assertSame(1, $report['missing_values']);
        $this->assertNull($positive->fresh()->jumlah_kk_terdampak);
        $this->assertNull($zero->fresh()->kondisi_listrik);
        $audit = json_decode(file_get_contents($this->files[0]), true);
        $this->assertNull($audit['changes'][0]['before']['jumlah_kk_terdampak']);
        $this->assertSame(12, $audit['changes'][0]['after']['jumlah_kk_terdampak']);
        $baseline = app(DashboardSummary::class)->data()['metrics']['condition_filled'];
        $this->runSync($dataset, true);
        $this->assertDatabaseHas('wilayah', ['id' => $positive->id, 'jumlah_kk_terdampak' => 12,
            'kondisi_listrik' => 'ada_kk_belum_berlistrik', 'latitude' => -6.9, 'longitude' => 107.6,
            'jumlah_warga_terdampak' => null, 'data_per_tanggal' => null, 'created_by' => null]);
        $this->assertDatabaseHas('wilayah', ['id' => $zero->id, 'jumlah_kk_terdampak' => 0,
            'kondisi_listrik' => 'tidak_tercatat_kk_belum_berlistrik', 'sumber_data' => WilayahDatasetSync::SOURCE]);
        $this->assertDatabaseHas('wilayah', ['id' => $missing->id, 'jumlah_kk_terdampak' => null, 'kondisi_listrik' => null]);
        $summary = app(DashboardSummary::class)->data();
        $this->assertSame($baseline + 2, $summary['metrics']['condition_filled']);
        $this->assertStringContainsString('2024', collect($summary['categories'])->firstWhere('value', 'ada_kk_belum_berlistrik')['meaning']);
        $this->assertSame(0, $this->runSync($dataset, true)['changed_rows']);
        $user = new User(['name' => 'Penguji sinkronisasi', 'email' => bin2hex(random_bytes(8)).'@example.test', 'password' => 'sync-test-password']);
        $user->role = 'pemerintah'; $user->save();
        $this->actingAs($user)->getJson('/pemerintah/wilayah/'.$zero->id)->assertOk()
            ->assertJsonPath('jumlah_kk_terdampak', 0)->assertJsonPath('kondisi_listrik', 'tidak_tercatat_kk_belum_berlistrik');
        $this->getJson('/pemerintah/wilayah/data?'.http_build_query(['q' => $zero->nama_wilayah, 'kondisi' => 'tidak_tercatat_kk_belum_berlistrik']))
            ->assertOk()->assertJsonPath('total', 1);
        $this->putJson('/pemerintah/wilayah/'.$positive->id, ['nama_wilayah' => $positive->nama_wilayah,
            'tingkat_wilayah' => 'desa_kelurahan', 'alamat_deskriptif' => 'Edit fixture',
            'kondisi_listrik' => 'ada_kk_belum_berlistrik', 'jumlah_kk_terdampak' => 14])->assertOk();
        $this->assertSame(0, $this->runSync($dataset, true)['changed_rows']);
        $this->assertSame(14, $positive->fresh()->jumlah_kk_terdampak);
        $this->putJson('/pemerintah/wilayah/'.$positive->id, ['nama_wilayah' => $positive->nama_wilayah,
            'tingkat_wilayah' => 'desa_kelurahan', 'alamat_deskriptif' => 'Edit fixture',
            'kondisi_listrik' => null, 'jumlah_kk_terdampak' => null])->assertOk();
        $this->assertSame(0, $this->runSync($dataset, true)['changed_rows']);
        $this->assertNull($positive->fresh()->jumlah_kk_terdampak);
        $this->assertNull($positive->fresh()->kondisi_listrik);
    }

    public function test_manual_values_dates_ownership_and_other_sources_are_protected(): void
    {
        $user = new User(['name' => 'Penguji manual', 'email' => bin2hex(random_bytes(8)).'@example.test', 'password' => 'sync-test-password']);
        $user->role = 'pemerintah'; $user->save();
        $owner = $this->region(); $owner->created_by = $user->id; $owner->save();
        $dated = $this->region(['data_per_tanggal' => '2026-11-04']);
        $source = $this->region(['sumber_data' => 'Survei manual']);
        $filled = $this->region(['jumlah_kk_terdampak' => 0, 'kondisi_listrik' => 'sudah_berlistrik']);
        $partialCount = $this->region(['jumlah_kk_terdampak' => 0]);
        $partialCondition = $this->region(['kondisi_listrik' => 'sudah_berlistrik']);
        $report = $this->runSync($this->dataset([[$owner, 23], [$dated, 23], [$source, 23], [$filled, 23],
            [$partialCount, 23], [$partialCondition, 23]]), true);
        $this->assertSame(5, $report['protected_rows']);
        $this->assertSame(1, $report['unchanged']);
        $this->assertSame(0, $report['changed_rows']);
        foreach ([$owner, $dated, $source] as $region) {
            $this->assertNull($region->fresh()->jumlah_kk_terdampak);
            $this->assertNull($region->fresh()->kondisi_listrik);
        }
        $this->assertSame(0, $filled->fresh()->jumlah_kk_terdampak);
        $this->assertSame('sudah_berlistrik', $filled->fresh()->kondisi_listrik);
        $this->assertNull($partialCount->fresh()->kondisi_listrik);
        $this->assertNull($partialCondition->fresh()->jumlah_kk_terdampak);
    }

    public function test_invalid_dataset_rolls_back_all_changes(): void
    {
        $first = $this->region(); $invalid = $this->region();
        $dataset = $this->dataset([[$first, 12], [$invalid, 10]]);
        $dataset['records'][$invalid->kode_referensi]['year'] = 2023;
        try {
            $this->runSync($dataset, true);
            $this->fail('Periode selain 2024 harus ditolak.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('tidak valid', $exception->getMessage());
        }
        $this->assertNull($first->fresh()->jumlah_kk_terdampak);
        $this->assertNull($invalid->fresh()->kondisi_listrik);
    }
}
