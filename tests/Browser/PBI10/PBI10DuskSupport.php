<?php

namespace Tests\Browser\PBI10;

use App\Models\PengajuanPlts;
use App\Models\User;
use App\Services\Pengajuan\LocationCatalog;
use Carbon\CarbonInterface;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\BeforeClass;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

trait PBI10DuskSupport
{
    private static ?Process $testServer = null;

    private static ?string $testDatabase = null;

    private static ?string $testStorage = null;

    private static array $testCacheFiles = [];

    private static int $driverPort;

    private static string $baseUrl;

    private static array $originalEnvironment = [];

    #[BeforeClass]
    public static function prepare(): void
    {
        $root = dirname(__DIR__, 3);
        $directory = $root.'/storage/framework/testing';
        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException('Folder database uji PBI10 tidak dapat dibuat.');
        }

        $token = bin2hex(random_bytes(8));
        self::$testDatabase = $directory.'/pbi10-dusk-'.$token.'.sqlite';
        self::$testStorage = $directory.'/pbi10-storage-'.$token;
        $file = fopen(self::$testDatabase, 'x');
        if ($file === false) {
            throw new RuntimeException('Database SQLite uji PBI10 tidak dapat dibuat.');
        }
        fclose($file);

        $filesystem = new Filesystem;
        foreach (['app', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs'] as $path) {
            $filesystem->ensureDirectoryExists(self::$testStorage.'/'.$path);
        }

        register_shutdown_function([self::class, 'cleanUpTestEnvironment']);
        try {
            $port = self::freePort();
            self::$driverPort = self::freePort();
            self::$baseUrl = 'http://127.0.0.1:'.$port;
            self::$testCacheFiles = [
                $directory.'/pbi10-config-'.$token.'.php',
                $directory.'/pbi10-routes-'.$token.'.php',
            ];
            $environment = [
                'APP_ENV' => 'testing',
                'APP_DEBUG' => 'false',
                'APP_URL' => self::$baseUrl,
                'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
                'APP_CONFIG_CACHE' => self::$testCacheFiles[0],
                'APP_ROUTES_CACHE' => self::$testCacheFiles[1],
                'LARAVEL_STORAGE_PATH' => self::$testStorage,
                'DB_CONNECTION' => 'sqlite',
                'DB_DATABASE' => self::$testDatabase,
                'DB_URL' => '',
                'DATABASE_URL' => '',
                'DB_FOREIGN_KEYS' => 'true',
                'CACHE_STORE' => 'array',
                'SESSION_DRIVER' => 'file',
                'SESSION_COOKIE' => 'pbi10_dusk_'.$token,
                'SESSION_SECURE_COOKIE' => 'false',
                'SESSION_DOMAIN' => 'null',
                'QUEUE_CONNECTION' => 'sync',
                'MAIL_MAILER' => 'array',
                'BCRYPT_ROUNDS' => '4',
                'FILESYSTEM_DISK' => 'local',
                'R2_ACCESS_KEY_ID' => 'pbi10-test-key',
                'R2_SECRET_ACCESS_KEY' => 'pbi10-test-secret',
                'R2_BUCKET' => 'pbi10-test-bucket',
                'R2_ENDPOINT' => self::$baseUrl,
                'MAP_TILE_URL' => self::$baseUrl.'/_pbi10-tiles/{z}/{x}/{y}.png',
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
            self::waitForPort($port, self::$testServer, 'Server Laravel PBI10');

            static::startChromeDriver(['--port='.self::$driverPort]);
            self::waitForPort(self::$driverPort, static::$chromeProcess, 'ChromeDriver PBI10');
            static::afterClass(static fn () => self::cleanUpTestEnvironment());
        } catch (Throwable $exception) {
            static::stopChromeDriver();
            self::cleanUpTestEnvironment();
            throw $exception;
        }
    }

    protected function driver(): RemoteWebDriver
    {
        $options = (new ChromeOptions)->addArguments([
            '--disable-gpu',
            '--window-size=1920,1080',
            '--disable-smooth-scrolling',
            '--blink-settings=imagesEnabled=false',
        ]);

        return RemoteWebDriver::create(
            'http://127.0.0.1:'.self::$driverPort,
            DesiredCapabilities::chrome()->setCapability(ChromeOptions::CAPABILITY, $options),
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(self::$testDatabase, DB::connection()->getDatabaseName());
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        try {
            DB::disconnect('sqlite');
        } finally {
            parent::tearDown();
        }
    }

    private function createSchema(): void
    {
        foreach (['riwayat_status_pengajuan', 'lokasi_pengajuan', 'pengajuan_plts', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role');
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('pengajuan_plts', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_tiket', 100)->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->unsignedBigInteger('wilayah_id')->nullable();
            $table->text('ringkasan_pengajuan');
            $table->string('status_terkini');
            $table->timestamp('status_diperbarui_pada')->nullable();
        });
        Schema::create('lokasi_pengajuan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->unique()->constrained('pengajuan_plts');
            $table->text('alamat_deskriptif');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 11, 7)->nullable();
        });
        Schema::create('riwayat_status_pengajuan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->constrained('pengajuan_plts');
            $table->foreignId('diubah_oleh')->constrained('users');
            $table->string('status_sebelumnya')->nullable();
            $table->string('status_baru');
            $table->string('jenis_tindakan', 50);
            $table->string('hasil_verifikasi', 50)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamp('created_at');
        });
    }

    protected function makeUser(string $name = 'Pengguna Uji PBI10', string $role = 'masyarakat'): array
    {
        $email = 'dusk-pbi10-'.Str::uuid().'@example.test';
        $password = 'password-uji-pbi10';
        $user = new User;
        $user->name = $name;
        $user->email = $email;
        $user->password = Hash::make($password);
        $user->role = $role;
        $user->is_active = true;
        $user->save();

        return compact('user', 'email', 'password');
    }

    protected function makeSubmission(User $user, array $options = []): PengajuanPlts
    {
        $submission = new PengajuanPlts;
        $submission->user_id = $user->id;
        $submission->nomor_tiket = $options['ticket'] ?? 'PBI10-'.Str::upper(Str::random(14));
        $submission->ringkasan_pengajuan = $options['summary'] ?? 'Pengajuan uji riwayat PBI10';
        $submission->status_terkini = $options['status'] ?? 'diajukan';
        $submission->status_diperbarui_pada = $options['status_updated_at'] ?? now();
        $submission->save();

        if (($options['location'] ?? true) !== false) {
            $address = array_key_exists('address', $options)
                ? $options['address']
                : app(LocationCatalog::class)->address([
                    'kabupaten' => '3205',
                    'kecamatan' => 'Cikelet',
                    'desa' => '32.05.30.2010',
                    'alamat' => 'Kampung Uji PBI10, RT 01',
                ]);
            if ($address !== null) {
                $submission->lokasi()->create([
                    'alamat_deskriptif' => $address,
                    'latitude' => null,
                    'longitude' => null,
                ]);
            }
        }

        $submittedAt = $options['submitted_at'] ?? now();
        if ($submittedAt !== false) {
            $this->addInitialHistory($submission, $user, $submittedAt);
        }

        return $submission;
    }

    protected function addInitialHistory(
        PengajuanPlts $submission,
        User $user,
        CarbonInterface|string $createdAt,
    ): void {
        DB::table('riwayat_status_pengajuan')->insert([
            'pengajuan_id' => $submission->id,
            'diubah_oleh' => $user->id,
            'status_sebelumnya' => null,
            'status_baru' => $submission->status_terkini,
            'jenis_tindakan' => 'pengajuan_baru',
            'hasil_verifikasi' => null,
            'catatan' => 'Catatan awal uji PBI10.',
            'created_at' => $createdAt,
        ]);
    }

    protected function signIn(Browser $browser, array $account): Browser
    {
        return $browser->visit(self::$baseUrl.'/login')
            ->type('email', $account['email'])
            ->type('password', $account['password'])
            ->press('Masuk');
    }

    protected function appUrl(string $path): string
    {
        return self::$baseUrl.$path;
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
            foreach ([self::$testDatabase, self::$testDatabase.'-wal', self::$testDatabase.'-shm'] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            self::$testDatabase = null;
        }

        if (self::$testStorage !== null) {
            (new Filesystem)->deleteDirectory(self::$testStorage);
            self::$testStorage = null;
        }

        foreach (self::$testCacheFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        self::$testCacheFiles = [];

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
}
