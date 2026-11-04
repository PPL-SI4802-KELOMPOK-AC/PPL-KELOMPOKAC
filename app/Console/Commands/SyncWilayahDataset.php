<?php

namespace App\Console\Commands;

use App\Services\Map\CsvDataset;
use App\Services\WilayahDatasetSync;
use Illuminate\Console\Command;

class SyncWilayahDataset extends Command
{
    protected $signature = 'wilayah:sync-csv {--apply : Isi kolom kosong; tanpa opsi ini hanya preview}';

    protected $description = 'Isi jumlah keluarga dan kondisi wilayah kosong dari CSV 2024 berdasarkan kode Kemendagri';

    public function handle(CsvDataset $compiler, WilayahDatasetSync $sync): int
    {
        try {
            $dataset = $compiler->compile(config('map.csv_path'));
            $apply = (bool) $this->option('apply');
            $path = storage_path('app/imports/wilayah-2024-'.($apply ? 'apply-' : 'preview-').now()->format('Ymd-His').'-'.bin2hex(random_bytes(4)).'.json');
            $report = $sync->run($dataset, $apply, $path);
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info('Audit dan nilai sebelum/sesudah: '.$path);
            $this->info($apply ? 'Sinkronisasi selesai. Data manual dipertahankan.' : 'Preview selesai. Database belum diubah.');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            report($exception);
            $this->error('Sinkronisasi gagal: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
