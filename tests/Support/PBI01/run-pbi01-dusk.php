<?php

// Menjalankan Dusk langsung melalui PHPUnit agar .env pengguna tidak pernah ditukar.
use Laravel\Dusk\Dusk;
use Symfony\Component\Process\Process;

require __DIR__.'/../../../vendor/autoload.php';
$root = dirname(__DIR__, 3);
chdir($root);
if (PHP_SAPI !== 'cli') {
    exit(1);
}
if (! class_exists(Dusk::class)) {
    fwrite(STDERR, "Jalankan composer install terlebih dahulu.\n");
    exit(1);
}
if (is_file($root.'/public/hot') || ! is_file($root.'/public/build/manifest.json')) {
    fwrite(STDERR, "Hentikan Vite dev server, hapus public/hot yang usang, lalu jalankan npm run build.\n");
    exit(1);
}
$directory = $root.'/storage/framework/testing';
if (! is_dir($directory)) {
    mkdir($directory, 0777, true);
}
$token = bin2hex(random_bytes(8));
$database = $directory.'/pbi01-dusk-'.$token.'.sqlite';
fclose(fopen($database, 'x'));
$freePort = static function (): int {
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if (! $socket) {
        throw new RuntimeException('Tidak dapat menyediakan port server uji: '.$error);
    }
    $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
    fclose($socket);

    return $port;
};
$port = $freePort();
$driverPort = $freePort();
$url = 'http://127.0.0.1:'.$port;
$env = [
    'APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_URL' => $url,
    'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
    'APP_CONFIG_CACHE' => $directory.'/pbi01-config-'.$token.'.php',
    'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'DB_URL' => '', 'DATABASE_URL' => '',
    'DB_FOREIGN_KEYS' => 'true', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'cookie',
    'SESSION_COOKIE' => 'pbi01_dusk_'.$token, 'SESSION_SECURE_COOKIE' => 'false', 'SESSION_DOMAIN' => 'null',
    'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array', 'BCRYPT_ROUNDS' => '4',
    'PBI01_DUSK_TESTS' => '1', 'PBI01_DUSK_DRIVER_PORT' => (string) $driverPort,
    'DUSK_DRIVER_URL' => 'http://127.0.0.1:'.$driverPort,
    // Dusk memeriksa keberadaan variabel, bukan nilai string "false".
    'DUSK_HEADLESS_DISABLED' => in_array('--headless', $argv, true) ? false : 'true',
    'PBI01_DEMO_PAUSE' => getenv('PBI01_DEMO_PAUSE') ?: '1000',
];
$screenshots = $root.'/tests/Browser/screenshots/PBI01';
if (! is_dir($screenshots)) {
    mkdir($screenshots, 0777, true);
}
$server = new Process([PHP_BINARY, '-S', '127.0.0.1:'.$port, '-t', $root.'/public',
    $root.'/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'], $root.'/public', $env);
$server->setTimeout(null);
$server->disableOutput();
$exit = 1;
try {
    $server->start();
    $ready = false;
    for ($attempt = 0; $attempt < 50; $attempt++) {
        if (! $server->isRunning()) {
            throw new RuntimeException('Server uji gagal dijalankan.');
        }
        $connection = @fsockopen('127.0.0.1', $port, $errno, $error, 0.2);
        if ($connection) {
            fclose($connection);
            $ready = true;
            break;
        }
        usleep(100000);
    }
    if (! $ready) {
        throw new RuntimeException('Server uji belum siap.');
    }
    echo 'PBI-01 Dusk: '.(in_array('--headless', $argv, true) ? 'headless' : 'GUI Chrome terlihat')."\n";
    echo "Database: SQLite sintetis terisolasi. MySQL/TiDB dan .env tidak diubah.\n";
    $test = new Process([PHP_BINARY, $root.'/vendor/phpunit/phpunit/phpunit',
        '--configuration', 'phpunit.pbi01-dusk.xml', '--testdox',
        '--log-junit', $root.'/tests/Browser/screenshots/PBI01/results.xml'], $root, $env);
    $test->setTimeout(300);
    $exit = $test->run(static function ($type, $output) {
        echo $output;
    });
    echo "\nScreenshot: tests/Browser/screenshots/PBI01/\n";
} finally {
    $server->stop();
    // Hanya berkas database acak milik proses ini yang dibersihkan.
    foreach ([$database, $database.'-wal', $database.'-shm'] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
}
exit($exit);
