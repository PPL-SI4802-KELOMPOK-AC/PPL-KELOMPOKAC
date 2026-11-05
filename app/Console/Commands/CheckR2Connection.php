<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CheckR2Connection extends Command
{
    protected $signature = 'terangdesa:r2-check {--write-test : Simpan, baca, dan hapus satu objek uji unik}';

    protected $description = 'Periksa sambungan bucket privat Cloudflare R2 tanpa menampilkan kredensial';

    public function handle(): int
    {
        $result = ['status' => 'pending', 'stage' => 'configuration', 'write_test' => (bool) $this->option('write-test')];
        $disk = null;
        $path = null;
        try {
            $config = config('filesystems.disks.r2', []);
            $missing = [];
            foreach (['key' => 'R2_ACCESS_KEY_ID', 'secret' => 'R2_SECRET_ACCESS_KEY',
                'bucket' => 'R2_BUCKET', 'endpoint' => 'R2_ENDPOINT'] as $field => $variable) {
                $value = $config[$field] ?? '';
                if (! is_string($value) || trim($value) === '' || preg_match('/YOUR_|ISI_|CHANGE_ME/i', $value)) {
                    $missing[] = $variable;
                }
            }
            if ($missing) {
                $result['missing'] = $missing;
                $result['message'] = 'Isi variabel R2 pada .env, lalu jalankan php artisan config:clear.';
            } else {
                $endpoint = parse_url($config['endpoint']);
                // Batasi endpoint pada domain R2 resmi agar kredensial tidak terkirim
                // ke alamat lain akibat salah konfigurasi. HTTPS tetap diverifikasi SDK.
                if (! $endpoint || ($endpoint['scheme'] ?? '') !== 'https'
                    || ! preg_match('/^[a-f0-9]{32}(?:\.(?:eu|us|fedramp))?\.r2\.cloudflarestorage\.com$/i', $endpoint['host'] ?? '')
                    || ! in_array($endpoint['path'] ?? '', ['', '/'], true)
                    || isset($endpoint['user']) || isset($endpoint['pass'])
                    || isset($endpoint['query']) || isset($endpoint['fragment'])
                    || (isset($endpoint['port']) && $endpoint['port'] !== 443)
                    || ($config['driver'] ?? '') !== 's3' || ($config['region'] ?? '') !== 'auto'
                    || ($config['visibility'] ?? '') !== 'private'
                    || ! preg_match('/^[a-z0-9][a-z0-9-]{1,61}[a-z0-9]$/', $config['bucket'])) {
                    throw new RuntimeException('Konfigurasi R2 tidak valid.');
                }
                $result['stage'] = 'connection';
                $disk = Storage::disk('r2');
                $disk->getClient()->headBucket(['Bucket' => $config['bucket']]);
                if ($this->option('write-test')) {
                    $path = 'connection-checks/'.Str::uuid().'.txt';
                    $payload = 'TerangDesa R2 connection check '.Str::uuid();
                    $result['stage'] = 'write';
                    if (! $disk->put($path, $payload, ['ContentType' => 'text/plain'])) {
                        throw new RuntimeException('Objek uji tidak tersimpan.');
                    }
                    $result['stage'] = 'read';
                    if (! hash_equals($payload, $disk->get($path))) {
                        throw new RuntimeException('Isi objek uji berbeda.');
                    }
                }
                $result['status'] = 'ready';
            }
        } catch (Throwable $exception) {
            $result['status'] = 'failed';
            $result['message'] = $result['stage'] === 'configuration'
                ? 'Periksa endpoint HTTPS R2 resmi, region auto, bucket, dan disk privat.'
                : 'R2 belum dapat diakses. Periksa kredensial, izin bucket, jaringan, dan sertifikat TLS.';
            // Pesan exception SDK dapat memuat detail request. Jangan cetak atau
            // mencatatnya bersama endpoint, signature, maupun kredensial.
        } finally {
            if ($disk && $path) {
                try {
                    if (! $disk->delete($path) || $disk->exists($path)) {
                        throw new RuntimeException('Pembersihan objek uji belum berhasil.');
                    }
                    $result['cleanup'] = 'deleted';
                } catch (Throwable $exception) {
                    $result['status'] = 'failed';
                    $result['stage'] = 'cleanup';
                    $result['cleanup'] = 'failed';
                    $result['test_object'] = $path;
                    $result['message'] = 'Objek uji belum dapat dibersihkan. Hapus path test_object di bucket, lalu periksa izin koneksi.';
                }
            }
        }
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $result['status'] === 'ready' ? self::SUCCESS : self::FAILURE;
    }
}
