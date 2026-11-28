<?php

namespace Tests\Browser\PBI23;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\BeforeClass;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\DuskTestCase;
use Tests\Support\PBI24Fixture;
use Throwable;

// Router ini hanya aktif pada server PHP sementara yang dimulai oleh tes ini.
// Tile sintetis membuat tes dapat diulang tanpa membebani layanan pihak ketiga.
if (PHP_SAPI === 'cli-server') {
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';
    if ($uri === '/_pbi23-data-fail') {
        http_response_code(503);
        header('Content-Type: application/json');
        echo json_encode(['message' => 'Data dashboard belum dapat dimuat. Periksa koneksi lalu coba kembali.']);

        return;
    }
    if (preg_match('~^/_pbi23-tiles/\d+/\d+/\d+\.png$~', $uri)) {
        $chunk = static fn (string $type, string $data): string => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        $pixels = str_repeat("\0".str_repeat("\xe7\xf0\xf4", 256), 256);
        header('Content-Type: image/png');
        header('Cache-Control: no-store');
        echo "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', 256, 256, 8, 2, 0, 0, 0))
            .$chunk('IDAT', gzcompress($pixels)).$chunk('IEND', '');

        return;
    }

    return require dirname(__DIR__, 3).'/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php';
}

/**
 * Per TC: php artisan dusk --filter=TC2301Test; seluruhnya: php artisan dusk tests/Browser/PBI23
 * Chrome, server lokal, skema, dan fixture disiapkan di file ini.
 * Seluruh operasi tulis hanya menuju SQLite sementara, bukan MySQL/TiDB.
 */
abstract class PBI23TestCase extends DuskTestCase
{
    use PBI24Fixture;

    protected array $fixture;

    private static ?Process $testServer = null;

    private static ?string $testDatabase = null;

    private static int $driverPort;

    private static array $originalEnvironment = [];

    #[BeforeClass]
    public static function prepare(): void
    {
        $root = dirname(__DIR__, 3);
        if (is_file($root.'/public/hot') || ! is_file($root.'/public/build/manifest.json')) {
            throw new RuntimeException('Hentikan Vite dev server, hapus public/hot yang usang, lalu jalankan npm.cmd run build.');
        }
        $directory = $root.'/storage/framework/testing';
        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException('Folder database uji tidak dapat dibuat.');
        }
        $token = bin2hex(random_bytes(8));
        self::$testDatabase = $directory.'/pbi23-dusk-'.$token.'.sqlite';
        $file = fopen(self::$testDatabase, 'x');
        if ($file === false) {
            throw new RuntimeException('Database SQLite uji tidak dapat dibuat.');
        }
        fclose($file);

        // Idempotent: membersihkan proses/berkas milik kelas ini juga saat setup gagal.
        register_shutdown_function([self::class, 'cleanUpTestEnvironment']);
        try {
            $port = self::freePort();
            self::$driverPort = self::freePort();
            $environment = [
                'APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_URL' => 'http://127.0.0.1:'.$port,
                'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
                'APP_CONFIG_CACHE' => $directory.'/pbi23-config-'.$token.'.php',
                'APP_ROUTES_CACHE' => $directory.'/pbi23-routes-'.$token.'.php',
                'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => self::$testDatabase, 'DB_URL' => '', 'DATABASE_URL' => '',
                'DB_FOREIGN_KEYS' => 'true', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'cookie',
                // Snapshot peta mempunyai store terpisah. Fixture memakai query builder
                // (tanpa event model), jadi tiap request harus membaca data skenario terbaru.
                'MAP_SNAPSHOT_CACHE_STORE' => 'array',
                'SESSION_COOKIE' => 'pbi23_dusk_'.$token, 'SESSION_SECURE_COOKIE' => 'false', 'SESSION_DOMAIN' => 'null',
                'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array', 'BCRYPT_ROUNDS' => '4',
                'MAP_TILE_URL' => 'http://127.0.0.1:'.$port.'/_pbi23-tiles/{z}/{x}/{y}.png',
                'MAP_ATTRIBUTION' => 'Tile dummy lokal untuk pengujian otomatis',
            ];
            foreach ($environment as $key => $value) {
                self::$originalEnvironment[$key] = [
                    'env' => $_ENV[$key] ?? null, 'server' => $_SERVER[$key] ?? null, 'process' => getenv($key),
                ];
                $_ENV[$key] = $_SERVER[$key] = $value;
                putenv($key.'='.$value);
            }
            self::$testServer = new Process([
                PHP_BINARY, '-S', '127.0.0.1:'.$port, '-t', $root.'/public',
                __FILE__,
            ], $root.'/public', $environment);
            self::$testServer->setTimeout(null)->disableOutput();
            self::$testServer->start();
            self::waitForPort($port, self::$testServer, 'Server Laravel uji');

            // Port sendiri agar tidak mengganggu ChromeDriver/PBI lain.
            static::startChromeDriver(['--port='.self::$driverPort, '--log-path='.$directory.'/pbi23-chrome.log']);
            self::waitForPort(self::$driverPort, static::$chromeProcess, 'ChromeDriver');
            static::afterClass(static function () {
                self::cleanUpTestEnvironment();
            });
        } catch (Throwable $exception) {
            static::stopChromeDriver();
            self::cleanUpTestEnvironment();
            throw $exception;
        }
    }

    /** Chrome selalu terlihat untuk PBI-23, tanpa mengubah DuskTestCase PBI lain. */
    protected function driver(): RemoteWebDriver
    {
        $options = (new ChromeOptions)->addArguments([
            '--window-size=1920,1080', '--disable-search-engine-choice-screen', '--disable-smooth-scrolling',
        ]);

        return RemoteWebDriver::create(
            'http://127.0.0.1:'.self::$driverPort,
            DesiredCapabilities::chrome()->setCapability(ChromeOptions::CAPABILITY, $options),
            15000,
            60000,
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(self::$testDatabase, DB::connection()->getDatabaseName());
        $this->assertSame('array', config('map.snapshot_cache_store'));
        $this->fixture = $this->buildAccessFixture();
        Schema::table('pengajuan_plts', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
        });
    }

    protected function tearDown(): void
    {
        try {
            DB::disconnect('sqlite');
        } finally {
            parent::tearDown();
        }
    }

    private static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        if ($socket === false) {
            throw new RuntimeException('Port uji tidak tersedia: '.$error);
        }
        $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        return $port;
    }

    private static function waitForPort(int $port, Process $process, string $label): void
    {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            if (! $process->isRunning()) {
                throw new RuntimeException($label.' gagal dimulai. Periksa PHP/ChromeDriver yang terpasang.');
            }
            $socket = @fsockopen('127.0.0.1', $port, $errno, $error, 0.1);
            if ($socket !== false) {
                fclose($socket);

                return;
            }
            usleep(100000);
        }
        throw new RuntimeException($label.' belum siap setelah 10 detik.');
    }

    public static function cleanUpTestEnvironment(): void
    {
        self::$testServer?->stop();
        self::$testServer = null;
        if (self::$testDatabase !== null) {
            foreach ([self::$testDatabase, self::$testDatabase.'-wal', self::$testDatabase.'-shm'] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            self::$testDatabase = null;
        }
        foreach (self::$originalEnvironment as $key => $original) {
            if ($original['env'] === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $original['env'];
            }
            if ($original['server'] === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $original['server'];
            }
            putenv($original['process'] === false ? $key : $key.'='.$original['process']);
        }
        self::$originalEnvironment = [];
    }

    protected function admin(Browser $b, string $path = '/admin/pengguna'): void
    {
        $b->resize(1440, 1050)->pause($this->stepPause())->loginAs($this->fixture['admin'])->visit($path)->pause($this->stepPause())->waitFor('h1');
    }

    protected function fillAccount(Browser $b, array $overrides = []): void
    {
        $data = $overrides + ['name' => 'Warga Demo Baru', 'email' => 'baru@pbi23.example.test', 'role' => 'masyarakat',
            'password' => 'password-demo-123', 'password_confirmation' => 'password-demo-123'];
        $b->typeSlowly('name', $data['name'], $this->typingPause())->pause($this->stepPause())->typeSlowly('email', $data['email'], $this->typingPause())->pause($this->stepPause())->select('role', $data['role'])->pause($this->stepPause())->select('is_active', '1')->pause($this->stepPause())
            ->typeSlowly('password', $data['password'], $this->typingPause())->pause($this->stepPause())->typeSlowly('password_confirmation', $data['password_confirmation'], $this->typingPause())->pause($this->stepPause());
    }

    protected function capture(Browser $b, string $name): void
    {
        $b->pause($this->resultPause())->screenshot('PBI23-'.$name);
    }

    protected function accountPayload(array $changes = []): array
    {
        return $changes + ['name' => 'Warga Demo Baru', 'email' => 'baru@pbi23.example.test',
            'role' => 'masyarakat', 'is_active' => '1', 'password' => 'password-demo-123',
            'password_confirmation' => 'password-demo-123'];
    }

    protected function request(Browser $b, string $method, string $url, array $data, ?string $token = null): array
    {
        $b->pause($this->stepPause());
        $script = 'window.pbi23Response=null;fetch('.json_encode($url).',{method:'.json_encode($method).',headers:{Accept:"application/json","Content-Type":"application/json","X-CSRF-TOKEN":'.json_encode($token).' || document.querySelector("input[name=_token]")?.value || ""},body:JSON.stringify('.json_encode($data).')}).then(async r=>{const t=await r.text();let body;try{body=JSON.parse(t)}catch{body={}}window.pbi23Response={status:r.status,body}}).catch(()=>window.pbi23Response={status:0,body:{}})';
        $b->script($script);
        $b->waitUntil('window.pbi23Response !== null', 15)->pause($this->stepPause());

        return $b->script('return window.pbi23Response')[0];
    }

    protected function pendingDeletion(string $tc): void
    {
        $this->browse(function (Browser $b) use ($tc) {
            $this->admin($b);
            $b->assertSee('Pengguna & akses');
            $this->capture($b, $tc.'-fitur-hapus-belum-tersedia');
        });
        $this->markTestIncomplete($tc.': endpoint, konfirmasi, dan kebijakan hapus akun belum tersedia. Skenario belum dapat diuji; bukan hasil lulus.');
    }

    protected function stepPause(): int
    {
        $value = getenv('DUSK_STEP_PAUSE');

        return $value === false || $value === '' ? 3000 : max(0, (int) $value);
    }

    protected function typingPause(): int
    {
        $value = getenv('DUSK_TYPING_PAUSE');

        return $value === false || $value === '' ? 80 : max(0, (int) $value);
    }

    protected function resultPause(): int
    {
        $value = getenv('DUSK_RESULT_PAUSE');

        return $value === false || $value === '' ? 5000 : max(0, (int) $value);
    }
}
