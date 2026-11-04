<?php

namespace Tests\Feature;

use App\Services\Map\CsvDataset;
use Tests\TestCase;

class CsvMapDatasetTest extends TestCase
{
    private array $paths = [];

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            @unlink($path);
        }
        parent::tearDown();
    }

    private function csv(array $rows): string
    {
        $path = storage_path('framework/testing/csv-map-'.bin2hex(random_bytes(5)).'.csv');
        $this->paths[] = $path;
        $base = ['kode_referensi' => '32.01.21.2001', 'kemendagri_kode_desa_kelurahan' => '32.01.21.2001',
            'bps_kode_desa_kelurahan' => '3201010001', 'kode_provinsi' => '32', 'bps_kode_kabupaten_kota' => '3201',
            'bps_nama_kabupaten_kota' => 'KABUPATEN BOGOR', 'jumlah_keluarga_sumber_belum_listrik' => '',
            'jumlah_keluarga_sumber_pln' => '2690', 'tahun' => '2024', 'satuan' => 'KELUARGA',
            'status_verifikasi_indikator' => 'belum_diverifikasi', 'sumber_data' => 'Fixture CSV'];
        $handle = fopen($path, 'w');
        fputcsv($handle, array_keys($base), ';');
        foreach ($rows as $row) {
            fputcsv($handle, array_values(array_merge($base, $row)), ';');
        }
        fclose($handle);

        return $path;
    }

    public function test_period_null_zero_and_pln_remain_separate_and_codes_are_not_interchanged(): void
    {
        $data = app(CsvDataset::class)->compile($this->csv([
            [], ['tahun' => '2023', 'jumlah_keluarga_sumber_belum_listrik' => '999'],
            ['kode_referensi' => '32.01.21.2002', 'kemendagri_kode_desa_kelurahan' => '32.01.21.2002', 'jumlah_keluarga_sumber_belum_listrik' => '0'],
            ['kode_referensi' => '32.01.21.2003', 'kemendagri_kode_desa_kelurahan' => '32.01.21.2003', 'jumlah_keluarga_sumber_belum_listrik' => '12'],
        ]));
        $this->assertCount(3, $data['records']);
        $this->assertNull($data['records']['32.01.21.2001']['belum_listrik']);
        $this->assertSame(2690, $data['records']['32.01.21.2001']['pln']);
        $this->assertSame(0, $data['records']['32.01.21.2002']['belum_listrik']);
        $this->assertSame(12, $data['records']['32.01.21.2003']['belum_listrik']);
        $this->assertArrayNotHasKey('3201010001', $data['records']);
        $this->assertSame(1, $data['report']['skipped_other_period_or_province']);
    }

    public function test_duplicate_codes_are_reported_and_excluded_instead_of_summed(): void
    {
        $data = app(CsvDataset::class)->compile($this->csv([['jumlah_keluarga_sumber_belum_listrik' => '2'], ['jumlah_keluarga_sumber_belum_listrik' => '3']]));
        $this->assertSame(1, $data['report']['duplicate_codes']);
        $this->assertSame([], $data['records']);
    }

    public function test_bps_cannot_be_used_as_the_kemendagri_reference_key(): void
    {
        $this->expectException(\RuntimeException::class);
        app(CsvDataset::class)->compile($this->csv([['kode_referensi' => '3201010001']]));
    }

    public function test_invalid_numbers_are_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        app(CsvDataset::class)->compile($this->csv([['jumlah_keluarga_sumber_belum_listrik' => '-1']]));
    }

    public function test_missing_map_artifact_returns_safe_error_without_database_access(): void
    {
        config(['map.dataset_path' => storage_path('framework/testing/does-not-exist-map.json'), 'app.debug' => true]);
        \Illuminate\Support\Facades\DB::shouldReceive('connection')->never();
        $this->getJson('/peta-wilayah/data')->assertStatus(503)->assertDontSee('password')->assertDontSee('stack');
    }
}
