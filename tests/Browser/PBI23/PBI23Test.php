<?php

namespace Tests\Browser\PBI23;

use App\Models\User;
use Facebook\WebDriver\Chrome\ChromeDevToolsDriver;
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
use Throwable;

// Router ini hanya aktif pada server PHP sementara yang dimulai oleh tes ini.
// Tile sintetis membuat tes dapat diulang tanpa membebani layanan pihak ketiga.
if (PHP_SAPI === 'cli-server') {
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';
    if ($uri === '/_pbi19-data-fail') {
        http_response_code(503);
        header('Content-Type: application/json');
        echo json_encode(['message' => 'Data dashboard belum dapat dimuat. Periksa koneksi lalu coba kembali.']);

        return;
    }
    if (preg_match('~^/_pbi19-tiles/\d+/\d+/\d+\.png$~', $uri)) {
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
 * Jalankan: php artisan dusk --filter=PBI19Test
 * Chrome, server lokal, skema, dan fixture disiapkan di file ini.
 * Seluruh operasi tulis hanya menuju SQLite sementara, bukan MySQL/TiDB.
 */
class PBI19Test extends DuskTestCase
{
    use \Tests\Support\StaffAccessFixture;
    private array $fixture;

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
        self::$testDatabase = $directory.'/pbi19-dusk-'.$token.'.sqlite';
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
                'APP_CONFIG_CACHE' => $directory.'/pbi19-config-'.$token.'.php',
                'APP_ROUTES_CACHE' => $directory.'/pbi19-routes-'.$token.'.php',
                'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => self::$testDatabase, 'DB_URL' => '', 'DATABASE_URL' => '',
                'DB_FOREIGN_KEYS' => 'true', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'cookie',
                // Snapshot peta mempunyai store terpisah. Fixture memakai query builder
                // (tanpa event model), jadi tiap request harus membaca data skenario terbaru.
                'MAP_SNAPSHOT_CACHE_STORE' => 'array',
                'SESSION_COOKIE' => 'pbi19_dusk_'.$token, 'SESSION_SECURE_COOKIE' => 'false', 'SESSION_DOMAIN' => 'null',
                'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array', 'BCRYPT_ROUNDS' => '4',
                'MAP_TILE_URL' => 'http://127.0.0.1:'.$port.'/_pbi19-tiles/{z}/{x}/{y}.png',
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
            static::startChromeDriver(['--port='.self::$driverPort, '--log-path='.$directory.'/pbi19-chrome.log']);
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

    /** Chrome selalu terlihat untuk PBI-19, tanpa mengubah DuskTestCase PBI lain. */
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

    public function test_tc_19_01_login_pemerintah_dan_ringkasan_aktual(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->logout()->visit('/login')->type('email', 'pemerintah@pbi19.example.test')
                ->type('password', 'password-khusus-uji')->press('Masuk')->waitForLocation('/pemerintah/dashboard', 15);
            $this->ready($browser);
            $browser->assertPresent('#sidebar')->assertSee('Petugas ESDM Uji')
                ->assertSeeIn('[data-gov-metric="submitted"]', '3')
                ->assertSeeIn('[data-gov-metric="mapped"]', '2')
                ->assertSeeIn('[data-gov-metric="served_kk"]', 'Belum tersedia')
                ->assertSeeIn('[data-gov-metric="completed_plts"]', 'Belum tersedia')
                ->assertCount('.gov-bar', 6)->assertCount('[data-gov-status-rows] tr', 6)
                ->assertAttribute('#sidebar a[aria-current]', 'href', url('/pemerintah/dashboard'));
            $data = $this->dashboardData($browser);
            $this->assertSame(3, array_sum(array_column($data['statuses'], 'count')));
            $this->assertSame(40, $data['metrics']['target_kk']);
            $this->assertSame(1, $data['follow_up']['unlocated']);
            $browser->mouseover('.gov-bar')->assertSeeIn('[data-gov-chart-focus]', 'Diajukan: 1 pengajuan');
            $this->capture($browser, 'TC-19-01-dashboard-desktop');
        });
    }

    public function test_tc_19_02_akses_pengunjung_dan_role_lain_ditolak(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->logout()->visit('/pemerintah/dashboard')->assertPathIs('/login');
            foreach (['masyarakat', 'admin'] as $role) {
                $browser->loginAs($this->fixture[$role])->visit('/pemerintah/dashboard')->assertSee('403');
                $browser->script('window.pbi19Forbidden = null; fetch("/pemerintah/dashboard/data", {headers:{Accept:"application/json"}}).then(r=>window.pbi19Forbidden=r.status)');
                $browser->waitUntil('window.pbi19Forbidden !== null')->assertScript('window.pbi19Forbidden', 403);
            }
            $this->capture($browser, 'TC-19-02-akses-ditolak');
        });
    }

    public function test_tc_19_03_filter_tahun_dan_data_kosong(): void
    {
        $this->browse(function (Browser $browser) {
            $this->open($browser);
            $browser->select('year', '2026')->click('[data-gov-period-form] button');
            $this->ready($browser);
            $browser->assertSeeIn('[data-gov-period]', 'Tahun 2026')
                ->assertSeeIn('[data-gov-metric="submitted"]', '2')->assertSeeIn('[data-gov-metric="mapped"]', '1');
            $this->assertSame(25, $this->dashboardData($browser)['metrics']['target_kk']);
            $browser->select('year', '2025')->click('[data-gov-period-form] button');
            $this->ready($browser);
            $browser->assertSeeIn('[data-gov-metric="submitted"]', '1')->assertSeeIn('[data-gov-metric="mapped"]', '0');
            $browser->visit('/pemerintah/dashboard?year=2024');
            $this->ready($browser);
            $browser->assertVisible('[data-gov-empty]')->assertSeeIn('[data-gov-metric="submitted"]', '0')
                ->assertSeeIn('[data-gov-metric="served_kk"]', 'Belum tersedia')->assertCount('.gov-bar', 6);
            $this->capture($browser, 'TC-19-03-keadaan-kosong');
        });
    }

    public function test_tc_19_04_duplikasi_progres_tidak_mengarang_realisasi_atau_plts(): void
    {
        foreach ([10, 20, 20] as $number) {
            DB::table('progres_elektrifikasi')->insert([
                'wilayah_id' => $this->fixture['region'], 'pengajuan_id' => null, 'dicatat_oleh' => $this->fixture['pemerintah']->id,
                'tahap_progres' => 'selesai', 'catatan_progres' => "$number KK kumulatif pada teks bebas", 'tanggal_progres' => '2026-11-02',
            ]);
        }
        $this->browse(function (Browser $browser) {
            $this->open($browser, '?year=2026');
            $data = $this->dashboardData($browser);
            $this->assertSame(2, $data['metrics']['submitted']);
            $this->assertSame(1, $data['metrics']['mapped']);
            $this->assertSame(25, $data['metrics']['target_kk']);
            $this->assertNull($data['metrics']['served_kk']);
            $this->assertNull($data['metrics']['completed_plts']);
            $browser->assertSeeIn('[data-gov-help="served_kk"]', 'Sasaran: 25 KK');
            $this->capture($browser, 'TC-19-04-sumber-realisasi-belum-tersedia');
        });
    }

    public function test_tc_19_05_koordinat_rusak_dan_pilih_penanda(): void
    {
        foreach ([null, 'salah', '91', '0'] as $latitude) {
            DB::table('wilayah')->insert([
                'nama_wilayah' => 'Desa Koordinat Rusak', 'alamat_deskriptif' => 'Kabupaten Bandung, Jawa Barat',
                'latitude' => $latitude, 'longitude' => '0', 'jumlah_kk_terdampak' => 1,
            ]);
        }
        $this->browse(function (Browser $browser) {
            $this->open($browser);
            $data = $this->dashboardData($browser);
            $this->assertSame(2, $data['metrics']['mapped']);
            $this->assertSame(5, $data['follow_up']['unlocated']);
            $browser->click('.gov-map-list summary')->select('#gov-village', (string) $this->fixture['region'])
                ->waitFor('.leaflet-popup-content')->assertSeeIn('[data-gov-selected]', 'Mekarjaya')
                ->assertSeeIn('.leaflet-popup-content', 'KK sasaran: 25');
            $this->capture($browser, 'TC-19-05-pilih-wilayah');
        });
    }

    public function test_tc_19_06_gagal_data_dan_coba_lagi(): void
    {
        $this->browse(function (Browser $browser) {
            $this->open($browser);
            $browser->script('document.querySelector("[data-government-dashboard]").dataset.url="/_pbi19-data-fail"');
            $browser->click('[data-gov-period-form] button')->waitUntil('document.querySelector("[data-government-dashboard]").dataset.state === "error"')
                ->assertVisible('[data-gov-retry]')->assertSeeIn('[data-gov-status]', 'Data dashboard belum dapat dimuat')
                ->assertSeeIn('[data-gov-metric="submitted"]', '—');
            $this->capture($browser, 'TC-19-06-data-gagal');
            $browser->script('document.querySelector("[data-government-dashboard]").dataset.url="/pemerintah/dashboard/data"');
            $browser->click('[data-gov-retry]');
            $this->ready($browser);
            $browser->assertSeeIn('[data-gov-metric="submitted"]', '3')->assertMissing('[data-gov-retry]');
        });
    }

    public function test_tc_19_07_gagal_tile_tetap_menampilkan_data_dan_dapat_pulih(): void
    {
        $this->browse(function (Browser $browser) {
            $cdp = new ChromeDevToolsDriver($browser->driver);
            $cdp->execute('Network.enable');
            $cdp->execute('Network.setBlockedURLs', ['urls' => ['*/_pbi19-tiles/*']]);
            try {
                $this->open($browser);
                $browser->waitForTextIn('[data-gov-tile-status]', 'Sebagian peta dasar gagal dimuat', 15)
                    ->assertVisible('[data-gov-tile-retry]')->assertSeeIn('[data-gov-metric="submitted"]', '3');
                $this->capture($browser, 'TC-19-07-tile-gagal');
                $cdp->execute('Network.setBlockedURLs', ['urls' => []]);
                $browser->click('[data-gov-tile-retry]')->waitForTextIn('[data-gov-tile-status]', 'Peta dasar siap', 15)
                    ->assertMissing('[data-gov-tile-retry]')->waitFor('.leaflet-tile-loaded')
                    ->assertScript('document.querySelector(".leaflet-tile-loaded").naturalWidth', 256);
            } finally {
                $cdp->execute('Network.setBlockedURLs', ['urls' => []]);
            }
        });
    }

    public function test_tc_19_08_navigasi_tujuan_belum_tersedia_dan_tampilan_seluler(): void
    {
        $this->browse(function (Browser $browser) {
            $this->open($browser);
            $browser->assertDisabled('[data-gov-report]')->assertDisabled('[data-gov-examination]')
                ->assertSee('Halaman laporan atau pemeriksaan belum tersedia');
            $browser->resize(390, 844)->pause(500);
            $browser->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
                ->assertVisible('[data-drawer-open]')->click('[data-drawer-open]')->waitFor('#sidebar a[aria-current]')
                ->click('#sidebar [data-drawer-close]')->waitUntil('!document.body.classList.contains("drawer-is-open")')->assertSee('Gambaran layanan elektrifikasi');
            $this->capture($browser, 'TC-19-08-dashboard-seluler');
        });
    }

    private function ready(Browser $browser): void
    {
        $browser->waitUntil('document.querySelector("[data-government-dashboard]")?.dataset.state === "ready"', 20);
    }

    private function open(Browser $browser, string $query = ''): void
    {
        $browser->resize(1440, 1050)->loginAs($this->fixture['pemerintah'])->visit('/pemerintah/dashboard'.$query);
        $this->ready($browser);
    }

    private function dashboardData(Browser $browser): array
    {
        $browser->script('window.pbi19Data=null; fetch("/pemerintah/dashboard/data" + window.location.search, {headers:{Accept:"application/json"}}).then(r=>r.json()).then(d=>window.pbi19Data=d)');
        $browser->waitUntil('window.pbi19Data !== null', 20);

        return $browser->script('return window.pbi19Data')[0];
    }

    private function capture(Browser $browser, string $name): void
    {
        $pause = getenv('DUSK_STEP_PAUSE');
        $browser->pause($pause === false ? 2000 : max(0, (int) $pause))->screenshot('PBI19-'.$name);
    }

    private function rebuildFixture(): array
    {
        $this->resetStaffAccessFixture();
        if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== self::$testDatabase) {
            throw new RuntimeException('Fixture PBI-19 hanya boleh memakai SQLite sementara milik tes ini.');
        }
        foreach (['progres_elektrifikasi', 'riwayat_status_pengajuan', 'pengajuan_plts', 'wilayah', 'users'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password');
            $t->string('role');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('wilayah', function (Blueprint $t) {
            $t->id();
            $t->string('kode_referensi')->nullable();
            $t->string('nama_wilayah');
            $t->text('alamat_deskriptif');
            $t->text('latitude')->nullable();
            $t->text('longitude')->nullable();
            $t->integer('jumlah_kk_terdampak')->nullable();
            $t->string('kondisi_listrik')->nullable();
        });
        Schema::create('pengajuan_plts', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('wilayah_id')->nullable();
            $t->string('status_terkini');
            $t->timestamp('status_diperbarui_pada');
        });
        Schema::create('riwayat_status_pengajuan', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('pengajuan_id');
            $t->string('status_baru');
            $t->string('jenis_tindakan');
            $t->timestamp('created_at');
        });
        Schema::create('progres_elektrifikasi', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('wilayah_id');
            $t->unsignedBigInteger('pengajuan_id')->nullable();
            $t->unsignedBigInteger('dicatat_oleh');
            $t->string('tahap_progres');
            $t->text('catatan_progres')->nullable();
            $t->date('tanggal_progres');
        });
        $users = [];
        foreach (['pemerintah', 'masyarakat', 'admin'] as $role) {
            $user = new User(['name' => $role === 'pemerintah' ? 'Petugas ESDM Uji' : 'Akun '.$role,
                'email' => $role.'@pbi19.example.test', 'password' => 'password-khusus-uji']);
            $user->role = $role;
            $user->is_active = true;
            $user->save();
            $users[$role] = $user;
            $this->grantStaffFixture($user);
        }
        $region = DB::table('wilayah')->insertGetId(['nama_wilayah' => 'Mekarjaya', 'alamat_deskriptif' => 'Kabupaten Bandung, Jawa Barat', 'latitude' => '-6.9175', 'longitude' => '107.6191', 'jumlah_kk_terdampak' => 25]);
        $missing = DB::table('wilayah')->insertGetId(['nama_wilayah' => 'Desa Tanpa Lokasi', 'alamat_deskriptif' => 'Kabupaten Bandung, Jawa Barat', 'jumlah_kk_terdampak' => 10]);
        DB::table('wilayah')->insert(['nama_wilayah' => 'Desa Lama', 'alamat_deskriptif' => 'Kabupaten Bandung, Jawa Barat', 'latitude' => '-7.05', 'longitude' => '107.7', 'jumlah_kk_terdampak' => 5]);
        foreach ([['diajukan', $region, '2026-01-01'], ['diperiksa', $region, '2026-11-01'], ['selesai', $missing, '2025-12-31'], ['draft', $region, null]] as [$status, $id, $date]) {
            $submission = DB::table('pengajuan_plts')->insertGetId(['wilayah_id' => $id, 'status_terkini' => $status, 'status_diperbarui_pada' => '2030-01-01 00:00:00']);
            if ($date) {
                DB::table('riwayat_status_pengajuan')->insert(['pengajuan_id' => $submission, 'status_baru' => 'diajukan', 'jenis_tindakan' => 'pengajuan_baru', 'created_at' => $date.' 00:00:00']);
            }
        }

        return $users + compact('region');
    }
}
