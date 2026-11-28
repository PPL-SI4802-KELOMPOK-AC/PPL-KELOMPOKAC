<?php

namespace Tests\Browser\PBI19;

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
use Tests\Support\StaffAccessFixture;
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
    use StaffAccessFixture;

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
                ->assertSeeIn('[data-gov-metric="submitted"]', '—')
                ->assertSeeIn('[data-gov-help="served_kk"]', 'Data sasaran belum dapat dimuat.');
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
                ->assertSee('Halaman laporan atau pemeriksaan belum tersedia')
                ->assertScript('getComputedStyle(document.querySelector(".gov-metrics")).gridTemplateColumns.split(" ").length', 4)
                ->assertScript(<<<'JS'
                    ['.gov-intro', '.gov-metrics', '.gov-filter-panel', '.gov-map-panel', '[data-gov-saved-views]']
                        .map(selector => document.querySelector(selector).getBoundingClientRect().top)
                        .every((top, index, tops) => index === 0 || top > tops[index - 1])
                    JS, true)
                ->assertPresent('.gov-filter-panel [data-saved-create]');
            $cdp = new ChromeDevToolsDriver($browser->driver);
            try {
                $cdp->execute('Emulation.setDeviceMetricsOverride', ['width' => 820, 'height' => 900, 'deviceScaleFactor' => 1, 'mobile' => true]);
                $browser->waitUntil('document.documentElement.clientWidth === 820')
                    ->assertScript('getComputedStyle(document.querySelector(".gov-metrics")).gridTemplateColumns.split(" ").length', 2);
                $cdp->execute('Emulation.setDeviceMetricsOverride', ['width' => 390, 'height' => 844, 'deviceScaleFactor' => 1, 'mobile' => true]);
                $browser->waitUntil('document.documentElement.clientWidth === 390')
                    ->assertScript('document.documentElement.scrollWidth <= 390', true)
                    ->assertScript('getComputedStyle(document.querySelector(".gov-metrics")).gridTemplateColumns.split(" ").length', 1)
                    ->assertScript('document.querySelector("[data-gov-map] .leaflet-control-zoom-in").offsetWidth', 44)
                    ->assertScript('getComputedStyle(document.querySelector(".gov-filter-fields")).gridTemplateColumns.split(" ").length', 2)
                    ->assertScript('document.querySelector(".gov-filter-actions button").offsetTop === document.querySelector("[data-gov-reset]").offsetTop', true)
                    ->assertVisible('[data-drawer-open]')->click('[data-drawer-open]')->waitFor('#sidebar a[aria-current]')
                    ->click('#sidebar [data-drawer-close]')->waitUntil('!document.body.classList.contains("drawer-is-open")')->assertSee('Gambaran layanan elektrifikasi');
                $this->capture($browser, 'TC-19-08-dashboard-seluler');

                $cdp->execute('Emulation.setDeviceMetricsOverride', ['width' => 320, 'height' => 720, 'deviceScaleFactor' => 1, 'mobile' => true]);
                $browser->waitUntil('document.documentElement.clientWidth === 320')
                    ->assertScript('document.documentElement.scrollWidth <= 320', true)
                    ->assertScript('document.querySelector(".gov-filter-actions button").offsetTop === document.querySelector("[data-gov-reset]").offsetTop', true);
            } finally {
                $cdp->execute('Emulation.clearDeviceMetricsOverride');
            }
        });
    }

    public function test_filter_wilayah_bertingkat_dan_kondisi_memperbarui_seluruh_dashboard(): void
    {
        DB::table('wilayah')->where('id', $this->fixture['region'])->update([
            'kode_referensi' => '32.78.01.1004', 'kondisi_listrik' => 'akses_terbatas',
        ]);

        $this->browse(function (Browser $browser) {
            $this->open($browser);
            $defaultSummary = 'Semua periode • Semua kabupaten/kota • Semua kecamatan • Semua desa/kelurahan • Semua kondisi listrik';
            $browser->script(<<<'JS'
                const originalFetch = window.fetch.bind(window);
                window.pbi19DashboardRequests = 0;
                window.fetch = (url, options) => {
                    if (new URL(url, location.origin).pathname !== '/pemerintah/dashboard/data') return originalFetch(url, options);
                    window.pbi19DashboardRequests++;
                    return new Promise(resolve => { window.pbi19ResumeDashboard = resolve; }).then(() => originalFetch(url, options));
                };
                JS);
            $browser->assertSeeIn('.gov-filter-panel', 'Filter Dashboard')
                ->assertScript('getComputedStyle(document.querySelector(".gov-filter-fields")).gridTemplateColumns.split(" ").length', 5)
                ->assertDisabled('#dashboard-subdistrict')->assertDisabled('#dashboard-village')
                ->select('kabupaten', '3278')
                ->waitUntil('!document.querySelector("#dashboard-subdistrict").disabled');
            $browser->select('kecamatan', '32.78.01')->select('desa', '32.78.01.1004')
                ->select('year', '2026')->select('kondisi', 'akses_terbatas')
                ->assertVisible('[data-gov-draft-status][data-pending]')->assertQueryStringMissing('kabupaten')
                ->assertSeeIn('[data-gov-applied-summary]', $defaultSummary)
                ->assertSeeIn('[data-gov-area]', 'Jawa Barat')->assertSeeIn('[data-gov-period]', 'Semua periode')
                ->assertSeeIn('[data-gov-chart-total]', '3')->assertCount('[data-gov-village] option', 3)
                ->assertScript('window.pbi19DashboardRequests', 0);

            $browser->press('Terapkan')->waitUntil('typeof window.pbi19ResumeDashboard === "function"')
                ->assertVisible('.gov-loading-spinner')->assertAttribute('[data-gov-results]', 'aria-busy', 'true')
                ->assertSeeIn('[data-gov-area]', 'Kota Tasikmalaya › Kecamatan Cihideung › Argasari')
                ->assertSeeIn('[data-gov-period]', 'Tahun 2026')->assertSeeIn('[data-gov-chart-total]', '—')
                ->assertCount('[data-gov-village] option', 1);
            $browser->script('window.pbi19ResumeDashboard();');
            $this->ready($browser);
            $browser->assertQueryStringHas('desa', '32.78.01.1004')->assertQueryStringHas('year', '2026')
                ->assertSeeIn('[data-gov-applied-summary]', 'Tahun 2026 • Kota Tasikmalaya • Kecamatan Cihideung • Argasari • Akses listrik terbatas')
                ->assertSeeIn('[data-gov-map-scope]', 'Argasari')->assertMissing('[data-gov-draft-status][data-pending]')
                ->assertSeeIn('[data-gov-metric="submitted"]', '2')->assertSeeIn('[data-gov-metric="mapped"]', '1')
                ->assertSeeIn('[data-gov-chart-total]', '2')->assertSeeIn('[data-gov-follow="waiting"]', '1')
                ->assertCount('[data-gov-village] option', 2)
                ->assertSeeIn('[data-gov-help="served_kk"]', 'Sasaran: 25 KK');
            $this->capture($browser, 'filter-aktif-laptop');

            $browser->refresh();
            $this->ready($browser);
            $browser->assertSelected('kecamatan', '32.78.01')->assertSelected('desa', '32.78.01.1004')
                ->assertSelected('kondisi', 'akses_terbatas')->assertSelected('year', '2026')
                ->select('kecamatan', '32.78.02')->assertSelected('desa', '')
                ->assertNotPresent('#dashboard-village option[value="32.78.01.1004"]')
                ->select('kabupaten', '3279')->assertDisabled('#dashboard-village')
                ->waitUntil('!document.querySelector("#dashboard-subdistrict").disabled')
                ->assertSeeIn('[data-gov-area]', 'Argasari')->assertSeeIn('[data-gov-chart-total]', '2');
            $browser->assertSelected('kecamatan', '')->assertSelected('desa', '')->press('Terapkan');
            $this->ready($browser);
            $browser->assertQueryStringMissing('desa')->assertQueryStringMissing('kecamatan')
                ->assertVisible('[data-gov-empty]')->assertSeeIn('[data-gov-chart-total]', '0')
                ->assertSeeIn('[data-gov-empty]', 'Tidak ada hasil untuk filter aktif')
                ->assertSeeIn('[data-gov-applied-summary]', 'Kota Banjar • Semua kecamatan • Semua desa/kelurahan')
                ->assertCount('[data-gov-village] option', 1)->assertSeeIn('[data-gov-metric="served_kk"]', 'Belum tersedia');

            $browser->press('Reset');
            $this->ready($browser);
            $browser->assertQueryStringMissing('year')->assertQueryStringMissing('kabupaten')->assertQueryStringMissing('kondisi')
                ->assertSelected('year', '')->assertSelected('kabupaten', '')->assertSelected('kondisi', '')
                ->assertDisabled('#dashboard-subdistrict')->assertDisabled('#dashboard-village')
                ->assertSeeIn('[data-gov-period]', 'Semua periode')->assertSeeIn('[data-gov-area]', 'Jawa Barat')
                ->assertSeeIn('[data-gov-applied-summary]', $defaultSummary)->assertMissing('[data-gov-empty]')
                ->assertSeeIn('[data-gov-chart-total]', '3')->assertCount('[data-gov-village] option', 3)
                ->assertSeeIn('[data-gov-follow="waiting"]', '1')->assertSeeIn('[data-gov-help="served_kk"]', 'Sasaran: 40 KK');

            $browser->visit('/pemerintah/dashboard?kabupaten=3299')->assertSee('Kabupaten/kota tidak tersedia di katalog wilayah.')
                ->assertAttribute('[data-government-dashboard]', 'data-state', 'error');
        });
    }

    public function test_navigasi_peta_dashboard_bertahan_setelah_filter_resize_dan_pemulihan_halaman(): void
    {
        $this->browse(function (Browser $browser) {
            $cdp = new ChromeDevToolsDriver($browser->driver);
            $hook = $cdp->execute('Page.addScriptToEvaluateOnNewDocument', ['source' => <<<'JS'
                const fetchNormally = window.fetch.bind(window);
                window.fetch = (url, options) => {
                    if (new URL(url, location.origin).pathname !== '/data/jawa-barat.geojson') return fetchNormally(url, options);
                    return new Promise(resolve => { window.releaseMapBoundary = resolve; }).then(() => fetchNormally(url, options));
                };
                JS]);
            try {
                $this->open($browser);
            } finally {
                $cdp->execute('Page.removeScriptToEvaluateOnNewDocument', ['identifier' => $hook['identifier']]);
            }
            $scale = 'Number(document.querySelector("[data-gov-map]").dataset.zoom)';
            $floor = 'Number(document.querySelector("[data-gov-map]").dataset.minZoom)';
            $browser->assertScript($scale.' === '.$floor, true)
                ->assertAttribute('.leaflet-control-zoom-out', 'aria-disabled', 'true');
            $browser->script('window.initialScale = '.$scale.';');
            $browser->click('.leaflet-control-zoom-in')->waitUntil($scale.' === window.initialScale + 0.25');
            $browser->script('window.beforeBoundaryScale = '.$scale.'; window.releaseMapBoundary();');
            $browser->waitForTextIn('[data-gov-tile-status]', 'Peta dasar siap', 15)
                ->assertScript($scale.' === window.beforeBoundaryScale', true)
                ->assertPresent('.leaflet-government-province-mask-pane path')
                ->assertAttribute('.leaflet-government-province-mask-pane path', 'fill-rule', 'evenodd');
            $browser->click('.leaflet-control-zoom-out')->waitUntil($scale.' === window.beforeBoundaryScale - 0.25');
            $browser->script('window.initialScale = '.$scale.';');

            // A wheel event is ignored until the map receives focus.
            $wheel = <<<'JS'
                const element = document.querySelector('[data-gov-map]');
                const bounds = element.getBoundingClientRect();
                element.dispatchEvent(new WheelEvent('wheel', {
                    bubbles: true, cancelable: true, deltaY: -120,
                    clientX: bounds.left + bounds.width / 2, clientY: bounds.top + bounds.height / 2,
                }));
                JS;
            $browser->script('document.querySelector("button[data-gov-map-home]").focus(); '.$wheel);
            $browser->pause(200)->assertScript($scale.' === window.initialScale', true);
            $browser->script('document.querySelector("[data-gov-map]").focus(); '.$wheel);
            $browser->waitUntil($scale.' === window.initialScale + 0.25');
            $browser->script('window.filteredScale = '.$scale.';');

            $browser->select('year', '2026')->press('Terapkan');
            $this->ready($browser);
            $browser->assertScript($scale.' === window.filteredScale', true)
                ->select('kabupaten', '3278')->press('Terapkan');
            $this->ready($browser);
            $browser->waitUntil('document.querySelector("[data-gov-map]").dataset.areaLevel === "kecamatan"')
                ->assertScript($scale.' === window.filteredScale', true)->press('Reset');
            $this->ready($browser);
            $browser->assertScript($scale.' === window.filteredScale', true);

            for ($step = 1; $step <= 4; $step++) {
                $browser->click('.leaflet-control-zoom-in')->waitUntil($scale.' === window.filteredScale + '.($step / 4));
            }
            $pane = 'document.querySelector(".leaflet-map-pane").style.transform';
            $browser->script('window.beforePan = '.$pane.'; document.querySelector("[data-gov-map]").focus();');
            $cdp->execute('Input.dispatchKeyEvent', ['type' => 'keyDown', 'key' => 'ArrowRight', 'code' => 'ArrowRight', 'windowsVirtualKeyCode' => 39]);
            $cdp->execute('Input.dispatchKeyEvent', ['type' => 'keyUp', 'key' => 'ArrowRight', 'code' => 'ArrowRight', 'windowsVirtualKeyCode' => 39]);
            $browser->waitUntil($pane.' !== window.beforePan');
            $browser->script(<<<'JS'
                const element = document.querySelector('[data-gov-map]');
                element.focus();
                const end = element.getBoundingClientRect();
                window.beforeDrag = element.dataset.center;
                const down = new MouseEvent('mousedown', { bubbles: true, button: 0, clientX: end.x + end.width / 2, clientY: end.y + end.height / 2 });
                Object.defineProperty(down, 'which', { value: 1 });
                element.dispatchEvent(down);
                element.dispatchEvent(new MouseEvent('mousemove', { bubbles: true, buttons: 1, clientX: end.x - 3000, clientY: end.y + 3000 }));
                element.dispatchEvent(new MouseEvent('mouseup', { bubbles: true, button: 0, clientX: end.x - 3000, clientY: end.y + 3000 }));
                JS);
            $browser->waitUntil('document.querySelector("[data-gov-map]").dataset.center !== window.beforeDrag')
                ->assertScript(<<<'JS'
                (() => {
                    const [lat, lng] = document.querySelector('[data-gov-map]').dataset.center.split(',').map(Number);
                    return lat >= -7.92 && lat <= -5.81 && lng >= 106.24 && lng <= 109.02;
                })()
                JS, true);

            $browser->resize(1160, 850);
            $browser->script('window.dispatchEvent(new PageTransitionEvent("pagehide", {persisted:true})); window.dispatchEvent(new PageTransitionEvent("pageshow", {persisted:true})); window.restoredScale = '.$scale.';');
            $browser->click('.leaflet-control-zoom-in')->waitUntil($scale.' !== window.restoredScale');
            $browser->press('Lihat Jawa Barat');
            $browser->script('window.homeScale = '.$scale.';');
            $browser->click('.leaflet-control-zoom-in')->waitUntil($scale.' !== window.homeScale');
            $browser->press('Lihat Jawa Barat')->waitUntil($scale.' === window.homeScale');
            $browser->assertScript($scale.' === '.$floor, true);
            $browser->assertScript(<<<'JS'
                (() => {
                    const [lat, lng] = document.querySelector('[data-gov-map]').dataset.center.split(',').map(Number);
                    return Math.abs(lat + 6.868) < 0.025 && Math.abs(lng - 107.629) < 0.025;
                })()
                JS, true);

            $browser->visit('/')->back();
            $this->ready($browser);
            $browser->script('window.backScale = '.$scale.';');
            $browser->click('.leaflet-control-zoom-in')->waitUntil($scale.' !== window.backScale');
            $browser->visit('/')->visit('/pemerintah/dashboard')->back()->forward();
            $this->ready($browser);
            $browser->script('window.forwardScale = '.$scale.';');
            $browser->click('.leaflet-control-zoom-in')->waitUntil($scale.' !== window.forwardScale');
        });
    }

    public function test_dashboard_refreshes_open_polygon_on_poll_visibility_and_restored_page(): void
    {
        $code = '32.78.01.1004';
        $region = DB::table('wilayah')->insertGetId([
            'kode_referensi' => $code, 'nama_wilayah' => 'Argasari', 'alamat_deskriptif' => 'Argasari, Jawa Barat',
            'jumlah_kk_terdampak' => 5, 'kondisi_listrik' => 'akses_terbatas',
        ]);
        $addSubmission = static function (?array $point = null) use ($region): string {
            $id = DB::table('pengajuan_plts')->insertGetId([
                'wilayah_id' => $region, 'status_terkini' => 'diajukan', 'status_diperbarui_pada' => '2026-06-01 00:00:00',
            ]);
            DB::table('riwayat_status_pengajuan')->insert([
                'pengajuan_id' => $id, 'jenis_tindakan' => 'pengajuan_baru', 'status_baru' => 'diajukan',
                'created_at' => '2026-06-01 00:00:00',
            ]);
            if ($point !== null) {
                DB::table('lokasi_pengajuan')->insert([
                    'pengajuan_id' => $id, 'latitude' => $point[0], 'longitude' => $point[1],
                    'alamat_deskriptif' => 'Alamat privat, Argasari, Cihideung, Kota Tasikmalaya, Jawa Barat',
                ]);
            }

            return (string) $id;
        };

        $this->browse(function (Browser $browser) use ($code, $addSubmission) {
            $cdp = new ChromeDevToolsDriver($browser->driver);
            $hook = $cdp->execute('Page.addScriptToEvaluateOnNewDocument', ['source' => <<<'JS'
                const timers = new Map();
                const test = window.pollTest = { started: 0, details: 0, active: 0, maxActive: 0, queries: [], strokes: [] };
                const set = window.setTimeout.bind(window), clear = window.clearTimeout.bind(window);
                let timerId = 0;
                window.setTimeout = (callback, delay, ...args) => {
                    if (delay !== 30000) return set(callback, delay, ...args);
                    timers.set(--timerId, () => callback(...args));
                    return timerId;
                };
                window.clearTimeout = id => timers.delete(id) || clear(id);
                test.tick = () => {
                    for (const [id, callback] of [...timers]) {
                        timers.delete(id);
                        callback();
                    }
                };
                test.timerCount = () => timers.size;
                test.visibility = state => {
                    Object.defineProperty(document, 'visibilityState', { configurable: true, value: state });
                    document.dispatchEvent(new Event('visibilitychange'));
                };
                const fetchNormally = window.fetch.bind(window);
                window.fetch = async (url, options) => {
                    const parsed = new URL(url, location.origin);
                    if (parsed.pathname === '/pemerintah/dashboard/wilayah') test.details++;
                    if (test.holdStatus && parsed.searchParams.has('status_pengajuan')) {
                        test.statusSignal = options.signal;
                        await new Promise(resolve => { test.releaseStatus = resolve; });
                    }
                    if (parsed.pathname !== '/pemerintah/dashboard/data') return fetchNormally(url, options);
                    test.started++;
                    test.queries.push(parsed.search);
                    test.maxActive = Math.max(test.maxActive, ++test.active);
                    try {
                        if (test.hold) await new Promise(resolve => { test.release = resolve; });
                        return await fetchNormally(url, options);
                    } finally {
                        test.active--;
                    }
                };
                const stroke = CanvasRenderingContext2D.prototype.stroke;
                CanvasRenderingContext2D.prototype.stroke = function (...args) {
                    if (this.canvas.closest('[data-gov-map]') && this.getLineDash().join(',') === '5,4') {
                        test.strokes.push(this.fillStyle);
                    }
                    return stroke.apply(this, args);
                };
                JS]);
            try {
                $query = '?year=2026&kabupaten=3278&kecamatan=32.78.01&desa='.$code.'&kondisi=akses_terbatas';
                $this->open($browser, $query);
                $browser->waitForTextIn('[data-gov-area-detail]', '0 pengajuan terhubung')
                    ->assertScript('pollTest.timerCount()', 1)
                    ->assertScript('pollTest.strokes.length', 0);
                $browser->script('pollTest.initialZoom = document.querySelector("[data-gov-map]").dataset.zoom;');
                $browser->click('.leaflet-control-zoom-in')
                    ->waitUntil('document.querySelector("[data-gov-map]").dataset.zoom !== pollTest.initialZoom');
                $browser->script(<<<'JS'
                    const mapElement = document.querySelector('[data-gov-map]');
                    pollTest.viewport = [mapElement.dataset.zoom, mapElement.dataset.center].join('|');
                    pollTest.pane = document.querySelector('.leaflet-overlay-pane canvas');
                    JS);

                $first = $addSubmission([-7.326, 108.1975]);
                $browser->script('pollTest.hold = true; pollTest.tick();');
                $browser->waitUntil('typeof pollTest.release === "function"')
                    ->assertSeeIn('[data-gov-area-detail]', '0 pengajuan terhubung');
                $browser->script('pollTest.tick(); pollTest.tick();');
                $browser->assertScript('pollTest.started', 2)->assertScript('pollTest.maxActive', 1);
                $browser->script('pollTest.hold = false; pollTest.release();');
                $browser->waitForTextIn('[data-gov-area-detail]', '1 pengajuan terhubung')
                    ->waitUntil('pollTest.strokes.includes("#ec9d06")')
                    ->assertScript('pollTest.pane === document.querySelector(".leaflet-overlay-pane canvas")', true)
                    ->assertScript('Array.from(["zoom","center"], key => document.querySelector("[data-gov-map]").dataset[key]).join("|") === pollTest.viewport', true)
                    ->assertSelected('#dashboard-year', '2026')
                    ->assertSelected('#dashboard-condition', 'akses_terbatas');
                $browser->assertScript('document.querySelectorAll(".gov-submission-marker").length', 1);
                $browser->script('pollTest.detailBeforeClick = pollTest.details;');
                $browser->click('.gov-submission-marker')->waitForTextIn('.gov-submission-popup', 'ID pengajuan: '.$first)
                    ->assertSeeIn('.gov-submission-popup', 'PENGAJUAN PLTS')
                    ->assertSeeIn('.gov-submission-popup', 'Argasari')
                    ->assertSeeIn('.gov-submission-popup', 'Tanggal pengajuan: 01/06/2026')
                    ->assertSeeIn('.gov-submission-popup', 'Status: Diajukan')
                    ->assertScript('pollTest.detailBeforeClick === pollTest.details', true);
                $browser->assertMissing('.gov-submission-records')
                    ->press('Diajukan: 1')->waitForTextIn('.gov-submission-records', 'ID pengajuan: '.$first)
                    ->assertSeeIn('.gov-submission-records', 'Argasari · Cihideung · Kota Tasikmalaya')
                    ->assertDontSeeIn('.gov-submission-records', 'Alamat privat');

                $browser->script('pollTest.visibility("hidden"); pollTest.tick();');
                $browser->assertScript('pollTest.timerCount()', 0)->assertScript('pollTest.started', 2);
                $second = $addSubmission([-7.326, 108.1975]);
                $browser->script('pollTest.visibility("visible");');
                $browser->waitForTextIn('[data-gov-area-detail]', '2 pengajuan terhubung')
                    ->waitForTextIn('.gov-submission-records', 'ID pengajuan: '.$second)
                    ->assertScript('pollTest.started', 3)->assertScript('pollTest.timerCount()', 1)
                    ->assertScript('document.querySelectorAll(".gov-submission-marker").length', 1)
                    ->assertSeeIn('.gov-submission-marker', '2')
                    ->assertSeeIn('.gov-submission-popup', 'ID pengajuan: '.$first)
                    ->click('details[data-submission-id="'.$second.'"] summary')
                    ->assertSeeIn('details[data-submission-id="'.$second.'"]', 'Status: Diajukan');

                $browser->script('window.dispatchEvent(new PageTransitionEvent("pagehide", {persisted:true})); pollTest.visibility("hidden");');
                $addSubmission([-7.3255, 108.1985]);
                $browser->script(<<<'JS'
                    pollTest.visibility('visible');
                    window.dispatchEvent(new PageTransitionEvent('pageshow', {persisted:true}));
                    document.dispatchEvent(new Event('visibilitychange'));
                    JS);
                $browser->waitForTextIn('[data-gov-area-detail]', '3 pengajuan terhubung')
                    ->assertScript('pollTest.started', 4)->assertScript('pollTest.maxActive', 1)
                    ->assertScript('document.querySelectorAll(".gov-submission-marker").length', 2)
                    ->assertVisible('details[data-submission-id="'.$second.'"][open]')
                    ->assertScript('pollTest.queries.every(query => query === pollTest.queries[0])', true)
                    ->assertScript('Array.from(["zoom","center"], key => document.querySelector("[data-gov-map]").dataset[key]).join("|") === pollTest.viewport', true);
                $browser->press('Kembali ke ringkasan status')->waitForTextIn('[data-gov-area-detail]', 'Diajukan: 3')
                    ->assertMissing('.gov-submission-records');

                $browser->visit('/');
                $addSubmission();
                $browser->back()
                    ->waitForTextIn('[data-gov-area-detail]', '4 pengajuan terhubung')
                    ->assertSelected('#dashboard-condition', 'akses_terbatas');
                $browser->visit('/')->visit('/pemerintah/dashboard'.$query)
                    ->waitForTextIn('[data-gov-area-detail]', '4 pengajuan terhubung')->back();
                $withoutLocation = $addSubmission();
                $browser->forward()->waitForTextIn('[data-gov-area-detail]', '5 pengajuan terhubung')
                    ->press('Diajukan: 5')->waitForTextIn('.gov-submission-records', 'ID pengajuan: '.$withoutLocation)
                    ->assertSeeIn('.gov-submission-records', 'Lokasi: Belum tersedia');
                $browser->resize(390, 850)->assertScript(<<<'JS'
                    (() => {
                        const panel = document.querySelector('[data-gov-area-detail]');
                        return panel.scrollWidth <= panel.clientWidth;
                    })()
                    JS, true)->resize(1440, 1050);
                $browser->select('#dashboard-year', '2025')->press('Terapkan')
                    ->waitForTextIn('[data-gov-area-detail]', '0 pengajuan terhubung')->assertMissing('.gov-submission-marker')
                    ->select('#dashboard-year', '2026')->select('#dashboard-condition', 'sudah_berlistrik')->press('Terapkan')
                    ->waitForTextIn('[data-gov-area-detail]', 'Tidak cocok dengan filter aktif')->assertMissing('.gov-submission-marker')
                    ->select('#dashboard-condition', 'akses_terbatas')->press('Terapkan')
                    ->waitForTextIn('[data-gov-area-detail]', '5 pengajuan terhubung')->waitFor('.gov-submission-marker');
                $browser->script('pollTest.holdStatus = true;');
                $browser->press('Diajukan: 5')->waitUntil('typeof pollTest.releaseStatus === "function"')
                    ->type('#gov-area-search', 'Cilembang')
                    ->select('[data-gov-area-options]', '32.78.01.1003')
                    ->waitForTextIn('[data-gov-area-detail]', 'Master wilayah belum tersedia')
                    ->assertScript('pollTest.statusSignal.aborted', true)
                    ->assertMissing('.gov-submission-marker');
                $browser->script('pollTest.holdStatus = false; pollTest.releaseStatus();');
                $browser->assertMissing('.gov-submission-records')
                    ->type('#gov-area-search', 'Argasari')->select('[data-gov-area-options]', $code)
                    ->waitFor('.gov-submission-marker')->press('Kembali satu tingkat')
                    ->waitUntil('document.querySelector("[data-gov-map]").dataset.areaLevel === "kecamatan"')
                    ->assertMissing('.gov-submission-marker');
            } finally {
                $cdp->execute('Page.removeScriptToEvaluateOnNewDocument', ['identifier' => $hook['identifier']]);
            }
        });
    }

    public function test_peta_poligon_memulihkan_cakupan_dan_detail_sesuai_filter(): void
    {
        DB::table('wilayah')->where('id', $this->fixture['region'])->update([
            'kode_referensi' => '32.78.01.1004', 'kondisi_listrik' => 'akses_terbatas',
        ]);
        $this->browse(function (Browser $browser) {
            $this->open($browser);
            $browser->script(<<<'JS'
                window.submissionOutlines = [];
                window.submissionPolygon = null;
                const originalResetStyle = window.L.GeoJSON.prototype.resetStyle;
                window.L.GeoJSON.prototype.resetStyle = function (layer) {
                    if (layer?.feature?.properties?.code === '32.78.01.1004') {
                        window.submissionPolygon = layer;
                    }
                    return originalResetStyle.call(this, layer);
                };
                const originalStroke = CanvasRenderingContext2D.prototype.stroke;
                CanvasRenderingContext2D.prototype.stroke = function (...args) {
                    if (this.canvas.closest('[data-gov-map]') && this.getLineDash().join(',') === '5,4') {
                        window.submissionOutlines.push(this.fillStyle);
                    }
                    return originalStroke.apply(this, args);
                };
                JS);
            $browser->waitUntil('document.querySelector("[data-gov-map]").dataset.areaLevel === "kabupaten"')
                ->assertSeeIn('.gov-legend', 'Master wilayah belum tersedia')
                ->assertSeeIn('.gov-legend', 'Tidak cocok dengan filter aktif')
                ->assertSeeIn('.gov-legend', 'Ada pengajuan terhubung pada periode aktif')
                ->select('[data-gov-area-options]', '3278')
                ->waitUntil('document.querySelector("[data-gov-map]").dataset.areaLevel === "kecamatan"')
                ->select('[data-gov-area-options]', '32.78.01')
                ->waitUntil('document.querySelector("[data-gov-map]").dataset.areaLevel === "desa"')
                ->select('[data-gov-area-options]', '32.78.01.1004')
                ->waitForTextIn('[data-gov-area-detail]', 'Pengajuan terkait')
                ->assertSeeIn('[data-gov-area-detail]', 'Akses listrik terbatas')
                ->assertSeeIn('[data-gov-area-detail]', 'Data master terkini')
                ->assertSeeIn('[data-gov-area-detail]', '2 dari 2 kolom terisi')
                ->assertSeeIn('[data-gov-area-detail]', 'Diajukan: 1')
                ->waitUntil('window.submissionOutlines.includes("#ec9d06")')
                ->assertScript('window.submissionPolygon.options.weight', 3)
                ->assertScript('window.submissionPolygon.options.color', '#0d3f5b');
            $browser->script(<<<'JS'
                window.submissionOutlines = [];
                window.submissionPolygon.fire('mouseover');
                window.submissionPolygon.fire('mouseout');
                JS);
            $browser->waitUntil('window.submissionOutlines.includes("#ec9d06")')
                ->assertScript('window.submissionPolygon.options.dashArray', '5 4')
                ->assertScript('window.submissionPolygon.options.weight', 3)
                ->press('Kembali satu tingkat')
                ->waitUntil('document.querySelector("[data-gov-map]").dataset.areaLevel === "kecamatan"')
                ->press('Lihat Jawa Barat')
                ->waitUntil('document.querySelector("[data-gov-map]").dataset.areaLevel === "kabupaten"')
                ->type('#gov-area-search', 'Argasari')
                ->select('[data-gov-area-options]', '32.78.01.1004')
                ->waitForTextIn('[data-gov-area-detail]', 'Progres elektrifikasi')
                ->assertDontSeeIn('[data-gov-area-detail]', 'Indikator belum tersedia');
            $browser->select('year', '2025')->press('Terapkan');
            $this->ready($browser);
            $browser->waitForTextIn('[data-gov-area-detail]', '0 pengajuan terhubung')
                ->assertSeeIn('[data-gov-area-detail]', 'Akses listrik terbatas')
                ->assertSeeIn('[data-gov-area-detail]', 'Belum ada catatan progres');
            $browser->script('window.submissionOutlines = [];');
            $browser->press('Kembali satu tingkat')
                ->waitUntil('document.querySelector("[data-gov-map]").dataset.areaLevel === "kecamatan"')
                ->select('[data-gov-area-options]', '32.78.01.1004')
                ->waitForTextIn('[data-gov-area-detail]', '0 pengajuan terhubung')
                ->assertScript('window.submissionPolygon.options.dashArray', null)
                ->assertScript('window.submissionOutlines.length', 0);
            $browser->select('kondisi', 'sudah_berlistrik')->press('Terapkan');
            $this->ready($browser);
            $browser->waitUntil('document.querySelector("[data-gov-map]").dataset.areaLevel === "desa"')
                ->waitForTextIn('[data-gov-area-detail]', 'Tidak cocok dengan filter aktif');
            $browser->type('#gov-area-search', 'Cilembang')
                ->select('[data-gov-area-options]', '32.78.01.1003')
                ->waitForTextIn('[data-gov-area-detail]', 'Master wilayah belum tersedia')
                ->assertSeeIn('[data-gov-area-detail]', 'Data wilayah dan aktivitas: Belum tersedia');
        });
    }

    public function test_zoom_cepat_tetap_di_peta_tanpa_memuat_ulang_data_atau_mengubah_layout(): void
    {
        $this->browse(function (Browser $browser) {
            $this->open($browser);
            $browser->waitUntil('document.querySelector("[data-gov-map]").dataset.areaLevel === "kabupaten"')
                ->assertNotChecked('[data-gov-area-points]')
                ->select('[data-gov-area-options]', '3205')
                ->waitUntil('Number(document.querySelector("[data-gov-map]").dataset.areaPolygonCount) === 442');
            $browser->script(<<<'JS'
                window.mapFetches = 0;
                const fetchOriginal = window.fetch.bind(window);
                window.fetch = (...args) => {
                    window.mapFetches++;
                    return fetchOriginal(...args);
                };
                window.measureDashboard = () => ['#sidebar', '[data-gov-period-form]', '.gov-intro h1', '[data-gov-area-detail]', '.gov-legend', '.leaflet-control-zoom'].map(selector => {
                    const box = document.querySelector(selector).getBoundingClientRect();
                    return [box.x, box.y, box.width, box.height];
                }).concat(window.innerWidth, window.devicePixelRatio, window.scrollY);
                window.zoomBefore = Number(document.querySelector('[data-gov-map]').dataset.zoom);
                window.beforeLayout = JSON.stringify(window.measureDashboard());
                for (let step = 0; step < 3; step++) document.querySelector('.leaflet-control-zoom-in').click();
                JS);
            $zoom = 'Number(document.querySelector("[data-gov-map]").dataset.zoom)';
            $browser->waitUntil($zoom.' === window.zoomBefore + 0.75')
                ->assertScript('window.mapFetches', 0)
                ->assertScript('JSON.stringify(window.measureDashboard()) === window.beforeLayout', true);
            $browser->script(<<<'JS'
                const map = document.querySelector('[data-gov-map]');
                const event = new WheelEvent('wheel', { bubbles:true, cancelable:true, ctrlKey:true, deltaY:-120 });
                window.mapWheelConsumed = !map.dispatchEvent(event);
                for (let step = 0; step < 3; step++) document.querySelector('.leaflet-control-zoom-out').click();
                JS);
            $browser->waitUntil($zoom.' === window.zoomBefore')
                ->assertScript('window.mapWheelConsumed', true)->assertScript('window.mapFetches', 0);
            $browser->press('Lihat Jawa Barat')
                ->waitUntil('document.querySelector("[data-gov-map]").dataset.areaLevel === "kabupaten"')
                ->type('#gov-area-search', 'Argasari')->select('[data-gov-area-options]', '32.78.01.1004')
                ->waitForTextIn('[data-gov-area-detail]', 'Master wilayah belum tersedia');
            $browser->script(<<<'JS'
                window.zoomBefore = Number(document.querySelector('[data-gov-map]').dataset.zoom);
                window.detailBefore = document.querySelector('[data-gov-area-detail]').textContent;
                window.mapFetches = 0;
                document.querySelector('.leaflet-control-zoom-in').click();
                document.querySelector('.leaflet-control-zoom-in').click();
                JS);
            $browser->waitUntil($zoom.' === window.zoomBefore + 0.5')
                ->assertScript('window.mapFetches', 0)
                ->assertScript('document.querySelector("[data-gov-area-detail]").textContent === window.detailBefore', true);
        });
    }

    private function ready(Browser $browser): void
    {
        $browser->waitUntil('document.querySelector("[data-government-dashboard]")?.dataset.state === "ready"', 20);
    }

    public function test_saved_dashboard_views_use_applied_filters_and_are_private(): void
    {
        DB::table('wilayah')->where('id', $this->fixture['region'])->update(['kode_referensi' => '32.78.01.1004']);
        $filters = ['year' => '2026', 'kabupaten' => '3278', 'kecamatan' => '32.78.01', 'desa' => '32.78.01.1004', 'kondisi' => 'unknown'];
        $this->browse(function (Browser $browser) use ($filters) {
            $this->open($browser, '?'.http_build_query($filters));
            $browser->select('#dashboard-year', '2025')
                ->press('Simpan Tampilan')->waitFor('[data-saved-form]')
                ->assertSeeIn('[data-saved-scope]', 'Tahun 2026')
                ->assertScript('getComputedStyle(document.querySelector("[data-saved-filters]")).display', 'none')
                ->type('#gov-saved-name', 'Ulasan Tasikmalaya')->press('[data-saved-form] [type=submit]')
                ->waitForTextIn('[data-saved-items]', 'Ulasan Tasikmalaya')
                ->assertSeeIn('.gov-saved-filter-summary', 'Tahun 2026 · Kota Tasikmalaya · Cihideung · Argasari · Data belum tersedia')
                ->assertSeeIn('[data-saved-status]', 'Tampilan disimpan.');
            $saved = DB::table('government_dashboard_views')->sole();
            foreach ($filters as $field => $value) {
                $this->assertSame($value, (string) $saved->{$field});
            }

            $browser->press('Simpan Tampilan')->type('#gov-saved-name', 'ulasan  tasikmalaya')
                ->press('[data-saved-form] [type=submit]')->waitForTextIn('[data-saved-error]', 'Nama tampilan sudah digunakan.')
                ->press('[data-saved-cancel]');
            $submission = DB::table('pengajuan_plts')->insertGetId([
                'wilayah_id' => $this->fixture['region'], 'status_terkini' => 'diajukan',
                'status_diperbarui_pada' => '2026-11-02 00:00:00',
            ]);
            DB::table('riwayat_status_pengajuan')->insert([
                'pengajuan_id' => $submission, 'jenis_tindakan' => 'pengajuan_baru',
                'status_baru' => 'diajukan', 'created_at' => '2026-11-02 00:00:00',
            ]);
            $browser->refresh();
            $this->ready($browser);
            $browser->click('[data-saved-list] summary')->waitFor('[aria-label="Buka Ulasan Tasikmalaya"]');
            $browser->script('window.beforeOpeningSavedView = true');
            $browser->press('[aria-label="Buka Ulasan Tasikmalaya"]')
                ->waitUntil('typeof window.beforeOpeningSavedView === "undefined"')->assertPathIs('/pemerintah/dashboard');
            $this->ready($browser);
            foreach ($filters as $field => $value) {
                $browser->assertSelected('[data-gov-period-form] [name="'.$field.'"]', $value);
            }
            $browser->waitUntil('document.querySelector("[data-gov-map]").dataset.areaLevel === "desa"')
                ->waitForTextIn('[data-gov-area-detail]', '3 pengajuan terhubung')
                ->assertSeeIn('[data-gov-metric="submitted"]', '3')
                ->click('[data-saved-list] summary')->waitFor('[aria-label="Ubah Ulasan Tasikmalaya"]')
                ->press('[aria-label="Ubah Ulasan Tasikmalaya"]')->waitFor('[data-saved-filters]')
                ->waitUntil('!document.querySelector("#gov-saved-village").disabled')
                ->assertSelected('#gov-saved-village', '32.78.01.1004')
                ->type('#gov-saved-name', 'Hasil terkini')->type('#gov-saved-year', '2025')
                ->select('#gov-saved-district', '')->select('#gov-saved-condition', '')
                ->press('[data-saved-form] [type=submit]')
                ->waitForTextIn('[data-saved-items]', 'Hasil terkini')
                ->assertSeeIn('.gov-saved-filter-summary', 'Tahun 2025 · Semua kabupaten/kota · Semua kecamatan · Semua desa/kelurahan · Semua kondisi listrik')
                ->assertSeeIn('[data-saved-status]', 'Tampilan diperbarui.');
            $this->assertDatabaseHas('government_dashboard_views', [
                'id' => $saved->id, 'name' => 'Hasil terkini', 'year' => 2025,
                'kabupaten' => null, 'kecamatan' => null, 'desa' => null, 'kondisi' => null,
            ]);
            $browser->press('[aria-label="Buka Hasil terkini"]')
                ->waitUntil('new URLSearchParams(location.search).get("year") === "2025"');
            $this->ready($browser);
            $browser->assertSelected('#dashboard-year', '2025')->assertSelected('#dashboard-district', '');

            $other = new User(['name' => 'Petugas Kedua', 'email' => 'second@pbi19.example.test', 'password' => 'password-khusus-uji']);
            $other->role = 'pemerintah';
            $other->is_active = true;
            $other->save();
            $this->grantStaffFixture($other);
            $browser->loginAs($other)->visit('/pemerintah/dashboard')->click('[data-saved-list] summary')
                ->waitForTextIn('[data-saved-items]', 'Belum ada tampilan tersimpan.');
            $browser->script('fetch("/pemerintah/dashboard/tampilan/'.(int) $saved->id.'", {headers:{Accept:"application/json"}}).then(r => window.savedStatus = r.status)');
            $browser->waitUntil('window.savedStatus === 404');
            $this->open($browser);
            $browser->click('[data-saved-list] summary')->waitFor('[aria-label="Hapus Hasil terkini"]')
                ->press('[aria-label="Hapus Hasil terkini"]')->waitFor('[data-saved-confirm][open]')
                ->assertSeeIn('[data-saved-confirm]', 'Hasil terkini')
                ->press('[data-saved-confirm] [value="cancel"]')
                ->waitUntil('!document.querySelector("[data-saved-confirm]").open')
                ->assertSeeIn('[data-saved-items]', 'Hasil terkini')
                ->press('[aria-label="Hapus Hasil terkini"]')->waitFor('[data-saved-confirm][open]')
                ->press('[data-saved-confirm] [value="delete"]')
                ->waitForTextIn('[data-saved-items]', 'Belum ada tampilan tersimpan.')
                ->assertSeeIn('[data-saved-status]', 'Tampilan dihapus.');
            $this->assertDatabaseMissing('government_dashboard_views', ['id' => $saved->id]);
        });
    }

    public function test_stale_saved_dashboard_view_opens_editor_instead_of_widening_scope(): void
    {
        $this->fixture['pemerintah']->governmentDashboardViews()->create([
            'name' => 'Wilayah lama', 'normalized_name' => 'wilayah lama', 'kabupaten' => '3299',
        ]);
        $this->browse(function (Browser $browser) {
            $this->open($browser);
            $browser->click('[data-saved-list] summary')->waitFor('[aria-label="Buka Wilayah lama"]')
                ->press('[aria-label="Buka Wilayah lama"]')
                ->waitForTextIn('[data-saved-warning]', 'Konfigurasi tersimpan sudah kedaluwarsa.')
                ->assertSelected('#gov-saved-district', '3299')
                ->select('#gov-saved-district', '')->press('[data-saved-form] [type=submit]')
                ->waitForTextIn('[data-saved-status]', 'Tampilan diperbarui.');
            $this->assertDatabaseHas('government_dashboard_views', ['normalized_name' => 'wilayah lama', 'kabupaten' => null]);
        });
    }

    public function test_saved_district_view_preserves_zero_on_an_empty_filtered_dashboard(): void
    {
        $this->browse(function (Browser $browser) {
            $this->open($browser, '?kabupaten=3279');
            $browser->press('Simpan Tampilan')->type('#gov-saved-name', 'Kabupaten tanpa data')
                ->press('[data-saved-form] [type=submit]')
                ->waitFor('[aria-label="Buka Kabupaten tanpa data"]');
            $this->assertDatabaseHas('government_dashboard_views', [
                'name' => 'Kabupaten tanpa data', 'kabupaten' => '3279', 'year' => null,
                'kecamatan' => null, 'desa' => null, 'kondisi' => null,
            ]);
            $browser->press('Reset');
            $this->ready($browser);
            $browser->press('[aria-label="Buka Kabupaten tanpa data"]')
                ->waitUntil('new URLSearchParams(location.search).get("kabupaten") === "3279"');
            $this->ready($browser);
            $browser->assertSelected('#dashboard-district', '3279')
                ->assertSeeIn('[data-gov-metric="submitted"]', '0')->assertVisible('[data-gov-empty]');
        });
    }

    public function test_both_saved_view_actions_focus_each_administrative_level(): void
    {
        DB::table('wilayah')->where('id', $this->fixture['region'])->update(['kode_referensi' => '32.78.01.1004']);
        $scopes = [
            'Kabupaten' => ['kecamatan', 'Kota Tasikmalaya', null, null],
            'Kecamatan' => ['desa', 'Cihideung', '32.78.01', null],
            'Desa' => ['desa', 'Cihideung', '32.78.01', '32.78.01.1004'],
        ];
        foreach ($scopes as $name => [, , $subdistrict, $village]) {
            $this->fixture['pemerintah']->governmentDashboardViews()->create([
                'name' => $name, 'normalized_name' => strtolower($name), 'year' => 2026,
                'kabupaten' => '3278', 'kecamatan' => $subdistrict, 'desa' => $village, 'kondisi' => 'unknown',
            ]);
        }

        $this->browse(function (Browser $browser) use ($scopes) {
            $map = 'document.querySelector("[data-gov-map]").dataset';
            foreach (['Hanya Terapkan', 'Buka'] as $action) {
                $zooms = [];
                foreach ($scopes as $name => [$level, $heading, $subdistrict, $village]) {
                    $this->open($browser);
                    $browser->waitUntil($map.'.areaLevel === "kabupaten"')
                        ->click('[data-saved-list] summary')->waitFor('[aria-label="'.$action.' '.$name.'"]');
                    $browser->script('window.savedViewDocument = true');
                    $browser->press('[aria-label="'.$action.' '.$name.'"]')
                        ->waitUntil($map.'.areaLevel === "'.$level.'" && Number('.$map.'.zoom) > Number('.$map.'.minZoom)')
                        ->assertScript('window.savedViewDocument === true', $action === 'Hanya Terapkan')
                        ->assertScript('Number('.$map.'.areaPolygonCount) > 0', true)
                        ->assertSelected('#dashboard-year', '2026')
                        ->assertSelected('#dashboard-district', '3278')
                        ->assertSelected('[data-gov-period-form] [name="kecamatan"]', $subdistrict ?? '')
                        ->assertSelected('[data-gov-period-form] [name="desa"]', $village ?? '')
                        ->assertSelected('[data-gov-period-form] [name="kondisi"]', 'unknown')
                        ->assertSee($heading)
                        ->assertSeeIn('[data-gov-metric="submitted"]', '2');
                    $zooms[] = $browser->script('return Number('.$map.'.zoom)')[0];
                    if ($village) {
                        $browser->waitForTextIn('[data-gov-area-detail]', 'Diajukan: 1')
                            ->assertSeeIn('[data-gov-area-detail]', 'Argasari');
                    }
                }
                $this->assertGreaterThan($zooms[0], $zooms[1]);
                $this->assertGreaterThanOrEqual($zooms[1], $zooms[2]);
            }
        });
    }

    public function test_saved_views_explain_missing_storage_without_breaking_dashboard(): void
    {
        Schema::drop('government_dashboard_views');
        $this->browse(function (Browser $browser) {
            $this->open($browser);
            $message = 'Penyimpanan tampilan belum tersedia karena tabel database belum disiapkan.';
            $browser->press('Simpan Tampilan')->type('#gov-saved-name', 'Bandung')
                ->press('[data-saved-form] [type=submit]')->waitForTextIn('[data-saved-error]', $message)
                ->assertDontSeeIn('[data-saved-error]', 'SQLSTATE')
                ->press('[data-saved-cancel]')->click('[data-saved-list] summary')
                ->waitForTextIn('[data-saved-items]', $message)
                ->assertSeeIn('[data-gov-metric="submitted"]', '3')
                ->waitUntil('document.querySelector("[data-gov-map]").dataset.areaLevel === "kabupaten"');
        });
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
        if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== self::$testDatabase) {
            throw new RuntimeException('Fixture PBI-19 hanya boleh memakai SQLite sementara milik tes ini.');
        }
        $this->resetStaffAccessFixture();
        foreach (['government_dashboard_views', 'progres_elektrifikasi', 'riwayat_status_pengajuan', 'lokasi_pengajuan', 'pengajuan_plts', 'wilayah', 'users'] as $table) {
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
        (require database_path('migrations/2026_11_28_000001_create_government_dashboard_views_table.php'))->up();
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
        Schema::create('lokasi_pengajuan', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('pengajuan_id')->unique();
            $t->text('alamat_deskriptif')->nullable();
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 11, 7)->nullable();
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
            $this->grantStaffFixture($user);
            $users[$role] = $user;
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
