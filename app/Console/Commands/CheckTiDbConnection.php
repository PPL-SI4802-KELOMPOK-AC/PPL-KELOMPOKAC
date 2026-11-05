<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Throwable;

class CheckTiDbConnection extends Command
{
    protected $signature = 'terangdesa:tidb-check {--write-test : Uji tulis/baca dalam transaksi yang di-rollback}';

    protected $description = 'Periksa endpoint, sertifikat TLS, skema, dan data TiDB tanpa menampilkan kredensial';

    public function handle(): int
    {
        $report = ['status' => 'pending', 'username' => '[REDACTED]', 'password' => '[REDACTED]'];
        $stage = 'configuration';
        try {
            $connection = DB::connection();
            $configuration = $connection->getConfig();
            $report['endpoint'] = $configuration['host'];
            $report['port'] = (int) $configuration['port'];
            $report['database_configured'] = $configuration['database'];
            if ($configuration['driver'] !== 'mysql' || ! is_string($configuration['host'])
                || ! str_ends_with($configuration['host'], '.tidbcloud.com')
                || $configuration['database'] !== 'terangdesa' || (int) $configuration['port'] !== 4000) {
                throw new RuntimeException('Konfigurasi aktif harus memakai mysql, endpoint TiDB, port 4000, dan database terangdesa. Periksa DB_URL serta override proses.');
            }
            if (! empty($configuration['read']) || ! empty($configuration['write']) || ! empty($configuration['unix_socket'])) {
                throw new RuntimeException('Koneksi khusus read/write atau socket masih aktif. Gunakan endpoint TiDB yang sama.');
            }
            $ca = $configuration['options'][PDO::MYSQL_ATTR_SSL_CA] ?? '';
            $report['ca_path'] = $ca;
            $report['ca_readable'] = is_string($ca) && $ca !== '' && is_readable($ca);
            $report['verify_server_certificate'] = ($configuration['options'][PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] ?? false) === true;
            if (! $report['ca_readable'] || ! $report['verify_server_certificate']) {
                throw new RuntimeException('CA harus dapat dibaca dan verifikasi sertifikat server harus aktif.');
            }
            $password = $configuration['password'] ?? '';
            if ($password === '' || str_contains($password, 'ISI_PASSWORD') || str_contains($password, 'YOUR_')) {
                $report['reason'] = 'Isi DB_PASSWORD asli pada .env, lalu jalankan php artisan config:clear.';
                $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                return self::FAILURE;
            }

            $stage = 'connection';
            $pdo = $connection->getPdo();
            $identity = $pdo->query('SELECT DATABASE() AS database_name, VERSION() AS server_version')->fetch(PDO::FETCH_ASSOC);
            if ($identity['database_name'] !== 'terangdesa' || stripos($identity['server_version'], 'TiDB') === false) {
                throw new RuntimeException('Identitas database/server aktif tidak cocok dengan TiDB terangdesa.');
            }
            $report['database_active'] = $identity['database_name'];
            $report['server_version'] = $identity['server_version'];
            $stage = 'tls';
            $tls = $pdo->query("SHOW SESSION STATUS WHERE Variable_name IN ('Ssl_cipher', 'Ssl_version')")->fetchAll(PDO::FETCH_KEY_PAIR);
            $report['tls'] = $tls;
            if (empty($tls['Ssl_cipher']) || ! in_array($tls['Ssl_version'] ?? '', ['TLSv1.2', 'TLSv1.3'], true)) {
                throw new RuntimeException('Sesi belum melaporkan cipher dan versi TLS yang didukung.');
            }
            $stage = 'schema';
            $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            $report['tables'] = $tables;
            foreach (['users', 'wilayah', 'pengajuan_plts', 'progres_elektrifikasi'] as $table) {
                if (! in_array($table, $tables, true)) {
                    throw new RuntimeException('Tabel aplikasi belum lengkap. Perlu pemeriksaan skema dan pemindahan data.');
                }
            }
            $report['users_count'] = (int) $connection->table('users')->count();
            $report['wilayah_count'] = (int) $connection->table('wilayah')->count();
            if ($this->option('write-test')) {
                $stage = 'write_read';
                $marker = 'TIDB-CHECK-'.bin2hex(random_bytes(6));
                $connection->beginTransaction();
                try {
                    $id = $connection->table('wilayah')->insertGetId([
                        'kode_referensi' => $marker, 'nama_wilayah' => $marker,
                        'tingkat_wilayah' => 'desa_kelurahan', 'alamat_deskriptif' => 'Uji transaksi koneksi TiDB',
                    ]);
                    if (! $connection->table('wilayah')->where('id', $id)->where('kode_referensi', $marker)->exists()) {
                        throw new RuntimeException('Data uji belum dapat dibaca kembali.');
                    }
                    $report['transactional_write_read'] = true;
                } finally {
                    $connection->rollBack();
                }
                $report['test_cleanup_verified'] = ! $connection->table('wilayah')->where('kode_referensi', $marker)->exists();
            }
            $report['status'] = 'ok';
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $code = (int) ($exception->errorInfo[1] ?? $exception->getCode());
            $report['status'] = 'failed';
            $report['stage'] = $stage;
            $report['driver_code'] = $code;
            $report['reason'] = match (true) {
                $exception instanceof RuntimeException && ! $exception instanceof \PDOException => $exception->getMessage(),
                $code === 1045 => 'Autentikasi ditolak. Periksa username cluster dan DB_PASSWORD pada .env.',
                $code === 1049 => 'Database terangdesa belum tersedia pada server tujuan.',
                $code === 2026 => 'Validasi TLS gagal. Periksa CA, hostname, dan izin baca sertifikat.',
                in_array($code, [2002, 2003, 2005], true) => 'Koneksi jaringan/DNS gagal. Periksa endpoint, port 4000, dan aturan akses TiDB.',
                default => 'Pemeriksaan gagal pada tahap yang dilaporkan. Detail kredensial dan SQL telah disamarkan.',
            };
            foreach ([$configuration['password'] ?? '', $configuration['username'] ?? '', $configuration['url'] ?? ''] as $secret) {
                if (is_string($secret) && $secret !== '') {
                    $report['reason'] = str_replace($secret, '[REDACTED]', $report['reason']);
                }
            }
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::FAILURE;
        }
    }
}
