<?php

namespace Tests\Browser\PBI12;

use App\Models\User;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\BeforeClass;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\DuskTestCase;
use Throwable;

abstract class PBI12TestCase extends DuskTestCase
{
    protected const PASSWORD_MASYARAKAT = '1234567890';
    protected const PASSWORD_PEMERINTAH = '1234567890';
    protected const PASSWORD_ADMIN = '123456780123456780';

    private static ?Process $testServer = null;
    private static ?string $testDatabase = null;
    private static int $driverPort;
    private static array $originalEnvironment = [];

    protected array $fixture = [];

    #[BeforeClass]
    public static function prepare(): void
    {
        $root = dirname(__DIR__, 3);

        if (
            is_file($root.'/public/hot')
            || ! is_file($root.'/public/build/manifest.json')
        ) {
            throw new RuntimeException(
                'Hentikan Vite dan pastikan public/build/manifest.json tersedia.'
            );
        }

        $directory = $root.'/storage/framework/testing';

        if (
            ! is_dir($directory)
            && ! mkdir($directory, 0777, true)
            && ! is_dir($directory)
        ) {
            throw new RuntimeException('Folder database uji tidak dapat dibuat.');
        }

        $token = bin2hex(random_bytes(8));
        self::$testDatabase = $directory.'/pbi12-dusk-'.$token.'.sqlite';

        $file = fopen(self::$testDatabase, 'x');

        if ($file === false) {
            throw new RuntimeException('Database SQLite uji tidak dapat dibuat.');
        }

        fclose($file);

        register_shutdown_function([self::class, 'cleanUpTestEnvironment']);

        try {
            $port = self::freePort();
            self::$driverPort = self::freePort();

            $environment = [
                'APP_ENV' => 'testing',
                'APP_DEBUG' => 'false',
                'APP_URL' => 'http://127.0.0.1:'.$port,
                'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
                'APP_CONFIG_CACHE' => $directory.'/pbi12-config-'.$token.'.php',
                'APP_ROUTES_CACHE' => $directory.'/pbi12-routes-'.$token.'.php',
                'DB_CONNECTION' => 'sqlite',
                'DB_DATABASE' => self::$testDatabase,
                'DB_URL' => '',
                'DATABASE_URL' => '',
                'DB_FOREIGN_KEYS' => 'true',
                'CACHE_STORE' => 'array',
                'SESSION_DRIVER' => 'cookie',
                'SESSION_COOKIE' => 'pbi12_dusk_'.$token,
                'SESSION_SECURE_COOKIE' => 'false',
                'SESSION_DOMAIN' => 'null',
                'QUEUE_CONNECTION' => 'sync',
                'MAIL_MAILER' => 'array',
                'BCRYPT_ROUNDS' => '4',
            ];

            foreach ($environment as $key => $value) {
                self::$originalEnvironment[$key] = [
                    'env' => $_ENV[$key] ?? null,
                    'server' => $_SERVER[$key] ?? null,
                    'process' => getenv($key),
                ];

                $_ENV[$key] = $_SERVER[$key] = $value;
                putenv($key.'='.$value);
            }

            self::$testServer = new Process([
                PHP_BINARY,
                '-S',
                '127.0.0.1:'.$port,
                '-t',
                $root.'/public',
                $root.'/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php',
            ], $root.'/public', $environment);

            self::$testServer->setTimeout(null)->disableOutput();
            self::$testServer->start();

            self::waitForPort($port, self::$testServer, 'Server Laravel uji');

            static::startChromeDriver(['--port='.self::$driverPort]);
            self::waitForPort(self::$driverPort, static::$chromeProcess, 'ChromeDriver');

            static::afterClass(function () {
                self::cleanUpTestEnvironment();
            });
        } catch (Throwable $exception) {
            static::stopChromeDriver();
            self::cleanUpTestEnvironment();

            throw $exception;
        }
    }

    protected function driver(): RemoteWebDriver
    {
        $options = (new ChromeOptions)->addArguments([
            '--window-size=1920,1080',
            '--disable-search-engine-choice-screen',
            '--disable-smooth-scrolling',
        ]);

        return RemoteWebDriver::create(
            'http://127.0.0.1:'.self::$driverPort,
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY,
                $options
            ),
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(
            self::$testDatabase,
            DB::connection()->getDatabaseName()
        );

        $this->fixture = $this->rebuildFixture();
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

        $port = (int) substr(
            strrchr(stream_socket_get_name($socket, false), ':'),
            1
        );

        fclose($socket);

        return $port;
    }

    private static function waitForPort(
        int $port,
        Process $process,
        string $label
    ): void {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            if (! $process->isRunning()) {
                throw new RuntimeException($label.' gagal dimulai.');
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
            foreach ([
                self::$testDatabase,
                self::$testDatabase.'-wal',
                self::$testDatabase.'-shm',
            ] as $file) {
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

            putenv(
                $original['process'] === false
                    ? $key
                    : $key.'='.$original['process']
            );
        }

        self::$originalEnvironment = [];
    }

    private function rebuildFixture(): array
    {
        $connection = DB::connection();
        $path = $connection->getDatabaseName();
        $directory = realpath(storage_path('framework/testing'));

        if (
            $connection->getDriverName() !== 'sqlite'
            || ! $directory
            || realpath(dirname($path)) !== $directory
            || ! preg_match(
                '/^pbi12-dusk-[a-f0-9]+\.sqlite$/',
                basename($path)
            )
        ) {
            throw new RuntimeException(
                'PBI-12 hanya boleh menggunakan SQLite uji terisolasi.'
            );
        }

        Schema::dropIfExists('admin_activity_logs');
        Schema::dropIfExists('pengajuan_plts');
        Schema::dropIfExists('wilayah');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role');
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('wilayah', function (Blueprint $table) {
            $table->id();
            $table->string('kode_referensi', 32)->nullable()->unique();
            $table->string('nama_wilayah', 150);
            $table->string('tingkat_wilayah', 50);
            $table->text('alamat_deskriptif');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 11, 7)->nullable();
            $table->string('kondisi_listrik')->nullable();
            $table->unsignedInteger('jumlah_kk_terdampak')->nullable();
            $table->unsignedInteger('jumlah_warga_terdampak')->nullable();
            $table->string('sumber_data')->nullable();
            $table->date('data_per_tanggal')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
        });

        Schema::create('pengajuan_plts', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_tiket', 50)->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('wilayah_id')->nullable()->constrained('wilayah')->restrictOnDelete();
            $table->text('ringkasan_pengajuan');
            $table->string('status_terkini');
            $table->timestamp('status_diperbarui_pada')->nullable();
        });

        Schema::create('admin_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name', 150);
            $table->foreignId('subject_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject_name', 150);
            $table->string('action', 40)->index();
            $table->json('changes');
            $table->timestamp('created_at')->index();
        });

        $masyarakat = $this->createFixtureUser(
            'Masyarakat Uji',
            'masyarakat.uji@example.test',
            self::PASSWORD_MASYARAKAT,
            'masyarakat'
        );

        $pemerintah = $this->createFixtureUser(
            'Pemerintah Uji',
            'sep@mail.com',
            self::PASSWORD_PEMERINTAH,
            'pemerintah'
        );

        $admin = $this->createFixtureUser(
            'Admin Uji',
            'tom@mail.com',
            self::PASSWORD_ADMIN,
            'admin'
        );

        return compact('masyarakat', 'pemerintah', 'admin');
    }

    private function createFixtureUser(
        string $name,
        string $email,
        string $password,
        string $role
    ): User {
        $user = new User([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        $user->role = $role;
        $user->is_active = true;
        $user->save();

        return $user;
    }

    protected function clearBrowserSession(Browser $browser): void
    {
        $browser->visit('/login');
        $browser->driver->manage()->deleteAllCookies();
    }

    protected function login(
        Browser $browser,
        string $email,
        string $password
    ): void {
        $browser->visit('/login')
            ->type('email', $email)
            ->type('password', $password)
            ->press('Masuk');
    }
}
