<?php

namespace App\Console\Commands;

use App\Models\Wilayah;
use App\Services\Map\CsvDataset;
use Illuminate\Console\Command;

class PrepareMapDataset extends Command
{
    protected $signature = 'map:prepare {--csv= : Lokasi CSV bersih} {--dry-run : Hanya audit, tanpa menulis cache}';

    protected $description = 'Cocokkan CSV 2024 berdasarkan kode Kemendagri dan siapkan cache peta, tanpa mengubah database';

    public function handle(CsvDataset $compiler): int
    {
        try {
            $data = $compiler->compile($this->option('csv') ?: config('map.csv_path'));
            $codes = Wilayah::query()->pluck('kode_referensi')->filter()->all();
            $csvCodes = array_keys($data['records']);
            $report = [...$data['report'],
                'matched_database' => count(array_intersect($csvCodes, $codes)),
                'csv_without_database_match' => count(array_diff($csvCodes, $codes)),
                'database_without_csv_match' => count(array_diff($codes, $csvCodes)),
                'proposed_database_updates' => 0,
                'cache_will_be_written' => ! $this->option('dry-run'),
            ];
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            if (! $this->option('dry-run')) {
                if ($data['report']['duplicate_codes'] > 0 || ! count($data['records'])) {
                    $this->error('Cache tidak diganti: kode ganda atau tidak ada data yang dapat digunakan.');

                    return self::FAILURE;
                }
                CsvDataset::writeJson(config('map.dataset_path'), $data);
                $this->info('Cache CSV diperbarui. Tidak ada perubahan pada tabel wilayah.');
            }

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            report($exception);
            $this->error('Persiapan gagal: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
