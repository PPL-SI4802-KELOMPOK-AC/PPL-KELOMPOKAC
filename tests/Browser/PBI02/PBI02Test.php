<?php

namespace Tests\Browser\PBI02;

use App\Services\Map\Geometry;
use Facebook\WebDriver\Chrome\ChromeDevToolsDriver;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\DriverCommand;
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
    if (preg_match('~^/_pbi02-tiles/\d+/\d+/\d+\.png$~', $uri)) {
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
 * Jalankan: php artisan dusk --filter=PBI02Test
 * Chrome, server lokal, skema, dan fixture disiapkan di file ini.
 * Seluruh operasi tulis hanya menuju SQLite sementara, bukan MySQL/TiDB.
 */
class PBI02Test extends DuskTestCase
{
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
        self::$testDatabase = $directory.'/pbi02-dusk-'.$token.'.sqlite';
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
                'APP_CONFIG_CACHE' => $directory.'/pbi02-config-'.$token.'.php',
                'APP_ROUTES_CACHE' => $directory.'/pbi02-routes-'.$token.'.php',
                'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => self::$testDatabase, 'DB_URL' => '', 'DATABASE_URL' => '',
                'DB_FOREIGN_KEYS' => 'true', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'cookie',
                // Snapshot peta mempunyai store terpisah. Fixture memakai query builder
                // (tanpa event model), jadi tiap request harus membaca data skenario terbaru.
                'MAP_SNAPSHOT_CACHE_STORE' => 'array',
                'SESSION_COOKIE' => 'pbi02_dusk_'.$token, 'SESSION_SECURE_COOKIE' => 'false', 'SESSION_DOMAIN' => 'null',
                'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array', 'BCRYPT_ROUNDS' => '4',
                'MAP_TILE_URL' => 'http://127.0.0.1:'.$port.'/_pbi02-tiles/{z}/{x}/{y}.png',
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
            static::startChromeDriver(['--port='.self::$driverPort]);
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

    /** Chrome selalu terlihat untuk PBI-02, tanpa mengubah DuskTestCase PBI lain. */
    protected function driver(): RemoteWebDriver
    {
        $options = (new ChromeOptions)->addArguments([
            '--window-size=1920,1080', '--disable-search-engine-choice-screen', '--disable-smooth-scrolling',
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

    /**
     * TC-01-01 | Normal: wilayah berkoordinat valid dimuat tanpa kesalahan.
     * Versi aplikasi memakai area berwarna; koordinat tetap diperiksa pada API asli.
     */
    public function test_tc_01_01_peta_dan_koordinat_valid(): void
    {
        $this->browse(function (Browser $browser) {
            $this->openMap($browser);
            $data = $this->mapData($browser);
            $this->assertSame(1, $data['summary']['total']);
            $this->assertSame(1, $data['summary']['located']);
            $this->assertCount(1, $data['features']);
            $this->assertSame('village', $data['features'][0]['type']);
            $record = $data['features'][0]['record'];
            $this->assertSame('Mekarjaya', $record['name']);
            $this->assertSame($this->fixture['id'], $record['id']);
            $this->assertEquals($this->fixture['coordinates'], $record['location']['coordinates']);
            $this->assertArrayNotHasKey('created_by', $record);
            $browser->assertCount('.district-area', 27)
                ->assertAttribute('.district-area[data-area-code="3205"]', 'fill', '#c53d48')
                ->assertSeeIn('[data-map-rows]', 'Mekarjaya')
                ->assertSeeIn('[data-map-stat="affected_kk"]', '25')
                ->assertPresent('.province-mask');
            $browser->assertScript('document.querySelector(".leaflet-tile-loaded").naturalWidth', 256);
            $this->capture($browser, 'TC-01-01-peta-koordinat-valid');
        });
    }

    /** TC-01-02 | Normal: klik area Mekarjaya dan baca ringkasan wilayah. */
    public function test_tc_01_02_pilih_area_mekarjaya(): void
    {
        $this->browse(function (Browser $browser) {
            $this->openMap($browser);
            $this->clickArea($browser, '.district-area[data-area-code="3205"]');
            $browser->waitFor('.village-area[data-area-code="32.05.21.2003"]', 20)
                ->waitUntil('document.querySelector("[data-map-data-state]").dataset.mapDataState === "ready"', 20)
                ->assertAttribute('.village-area[data-area-code="32.05.21.2003"]', 'fill', '#c53d48');
            $this->clickArea($browser, '.village-area[data-area-code="32.05.21.2003"]');
            $browser->waitForTextIn('#map-detail-title', 'Mekarjaya', 15)
                ->assertPresent('.selected-area')
                ->assertSeeIn('[data-map-detail]', 'SUKARESMI')
                ->assertSeeIn('[data-map-detail]', 'KABUPATEN GARUT')
                ->assertSeeIn('[data-map-detail]', '25 keluarga')
                ->assertSeeIn('[data-map-detail]', '2024')
                ->assertPresent('[data-map-rows] tr.is-selected[data-id="'.$this->fixture['id'].'"]');
            $this->capture($browser, 'TC-01-02-area-mekarjaya-aktif');
        });
    }

    /** TC-01-03 | Pengecualian: database kosong, peta dasar tetap tersedia. */
    public function test_tc_01_03_database_wilayah_kosong(): void
    {
        DB::table('wilayah')->delete();
        $this->assertDatabaseCount('wilayah', 0);
        $this->browse(function (Browser $browser) {
            $this->openMap($browser);
            $data = $this->mapData($browser);
            $this->assertSame(0, $data['summary']['total']);
            $this->assertSame([], $data['features']);
            $browser->assertSeeIn('[data-map-data-status]', 'Tidak ada wilayah yang sesuai')
                ->assertVisible('[data-map-empty]')
                ->assertSeeIn('[data-map-empty]', 'Peta dasar tetap dapat digunakan')
                ->assertCount('[data-map-rows] tr', 0)
                ->assertCount('.district-area', 27)
                ->assertPresent('.leaflet-control-zoom')
                ->assertAttribute('[data-map-panel]', 'data-state', 'ready')
                ->assertMissing('[data-map-data-retry]');
            $this->capture($browser, 'TC-01-03-database-kosong');
        });
    }

    /**
     * TC-01-04 | Pengecualian: koordinat NULL/teks/parsial/di luar batas diabaikan.
     * Record tetap tersedia dalam daftar; tidak menjadi feature titik pada API.
     */
    public function test_tc_01_04_koordinat_tidak_valid_diabaikan(): void
    {
        $invalid = [];
        foreach ([
            ['Desa Koordinat Null', null, null],
            ['Desa Koordinat Teks', 'bukan-lintang', 'bukan-bujur'],
            ['Desa Koordinat Parsial', '-7.2', null],
            ['Desa Koordinat Di Luar Batas', '91', '181'],
        ] as [$name, $latitude, $longitude]) {
            $invalid[] = DB::table('wilayah')->insertGetId([
                'nama_wilayah' => $name, 'tingkat_wilayah' => 'desa_kelurahan',
                'alamat_deskriptif' => $name.', SUKARESMI, KABUPATEN GARUT, JAWA BARAT',
                'latitude' => $latitude, 'longitude' => $longitude, 'jumlah_kk_terdampak' => 5,
            ]);
        }
        $this->assertDatabaseCount('wilayah', 5);
        $this->browse(function (Browser $browser) use ($invalid) {
            $this->openMap($browser);
            $data = $this->mapData($browser);
            $this->assertSame(5, $data['summary']['total']);
            $this->assertSame(1, $data['summary']['located']);
            $this->assertSame(4, $data['summary']['unlocated']);
            $this->assertCount(1, $data['features']);
            $this->assertSame($this->fixture['id'], $data['features'][0]['record']['id']);
            foreach ($data['list']['data'] as $record) {
                if (in_array($record['id'], $invalid, true)) {
                    $this->assertNull($record['location']);
                    $this->assertNotEmpty($record['location_unavailable_reason']);
                    $browser->assertSeeIn('[data-map-rows] tr[data-id="'.$record['id'].'"]', 'Belum tersedia');
                }
            }
            $browser->assertSeeIn('[data-map-data-status]', '4 lokasi belum tersedia')
                ->assertSeeIn('[data-map-rows]', 'Mekarjaya')
                ->assertCount('[data-map-rows] tr', 5)
                ->assertAttribute('[data-map-panel]', 'data-state', 'ready')
                ->assertMissing('[data-map-data-retry]');
            $this->capture($browser, 'TC-01-04-koordinat-rusak-diabaikan');
        });
    }

    /** TC-01-05 | Pengecualian: blokir request tile, pesan gagal muncul, retry pulih. */
    public function test_tc_01_05_tile_gagal_dan_pemulihan(): void
    {
        $this->browse(function (Browser $browser) {
            $devTools = new ChromeDevToolsDriver($browser->driver);
            $devTools->execute('Network.enable');
            $devTools->execute('Network.setBlockedURLs', ['urls' => ['*/_pbi02-tiles/*']]);
            try {
                $this->openMap($browser, false);
                $browser->waitUntil('document.querySelector("[data-map-panel]").dataset.state === "error"', 20)
                    ->assertSeeIn('[data-map-status]', 'peta dasar gagal dimuat')
                    ->assertSeeIn('[data-map-status]', 'Periksa koneksi internet')
                    ->assertVisible('[data-map-retry]')
                    ->assertAttribute('[data-region-map]', 'aria-busy', 'false')
                    ->assertSeeIn('[data-map-rows]', 'Mekarjaya')
                    ->assertPresent('.leaflet-control-zoom');
                $this->capture($browser, 'TC-01-05-tile-gagal');
                // Hanya jaringan tile yang diputus, data wilayah tetap benar-benar dimuat.
                $devTools->execute('Network.setBlockedURLs', ['urls' => []]);
                $browser->scrollIntoView('[data-map-retry]')->pause($this->stepPause())->click('[data-map-retry]')
                    ->waitUntil('document.querySelector("[data-map-panel]").dataset.state === "ready"', 20)
                    ->assertSeeIn('[data-map-status]', 'Peta dasar siap dijelajahi')
                    ->assertMissing('[data-map-retry]');
                $this->capture($browser, 'TC-01-05-tile-pulih-setelah-retry');
            } finally {
                $devTools->execute('Network.setBlockedURLs', ['urls' => []]);
                $devTools->execute('Network.disable');
            }
        });
    }

    private function openMap(Browser $browser, bool $tilesReady = true): void
    {
        $browser->resize(1920, 1400)->visit('/peta-wilayah')
            ->waitUntil('document.querySelector("[data-map-data-state]")?.dataset.mapDataState === "ready"', 20)
            ->assertAttribute('[data-boundary-state]', 'data-boundary-state', 'ready')
            ->assertSee('Peta Wilayah Jawa Barat');
        if ($tilesReady) {
            $browser->waitUntil('document.querySelector("[data-map-panel]").dataset.state === "ready"', 20);
        }
        $browser->scrollIntoView('[data-region-map]')->pause($this->stepPause());
    }

    private function mapData(Browser $browser): array
    {
        $response = $browser->driver->executeAsyncScript(
            'const done = arguments[arguments.length - 1]; fetch("/peta-wilayah/data?zoom=19", {headers:{Accept:"application/json"}}).then(async r => done({status:r.status, body:await r.json()})).catch(e => done({status:0, error:String(e)}));'
        );
        $this->assertSame(200, $response['status']);

        return $response['body'];
    }

    /** Klik titik yang benar-benar berada di dalam polygon, bukan tengah bounding box. */
    private function clickArea(Browser $browser, string $selector): void
    {
        $browser->scrollIntoView('[data-region-map]')->pause(max(500, $this->stepPause()));
        $point = $browser->driver->executeScript(<<<'JS'
            const path = document.querySelector(arguments[0]);
            const box = path.getBBox(), matrix = path.getScreenCTM();
            for (let y = .05; y < 1; y += .05) for (let x = .05; x < 1; x += .05) {
                const p = new DOMPoint(box.x + box.width*x, box.y + box.height*y).matrixTransform(matrix);
                const xPixel = Math.round(p.x), yPixel = Math.round(p.y);
                if (document.elementFromPoint(xPixel, yPixel) === path) return {x: xPixel, y: yPixel};
            }
            return null;
            JS, [$selector]);
        $this->assertNotNull($point, 'Area tidak mempunyai titik klik yang terlihat: '.$selector);
        $browser->driver->execute(DriverCommand::ACTIONS, ['actions' => [[
            'type' => 'pointer', 'id' => 'pbi02-mouse', 'parameters' => ['pointerType' => 'mouse'],
            'actions' => [
                ['type' => 'pointerMove', 'duration' => min(600, $this->stepPause()), 'origin' => 'viewport', 'x' => $point['x'], 'y' => $point['y']],
                ['type' => 'pointerDown', 'button' => 0], ['type' => 'pointerUp', 'button' => 0],
            ],
        ]]]);
        $browser->pause($this->stepPause());
    }

    /** Jeda demo per langkah, dalam milidetik. Isi 0 untuk menjalankan tanpa jeda. */
    private function stepPause(): int
    {
        $value = getenv('DUSK_STEP_PAUSE');

        return $value !== false && ctype_digit($value) ? min((int) $value, 10000) : 2000;
    }

    private function capture(Browser $browser, string $name): void
    {
        $browser->scrollIntoView('[data-region-map]')->pause(max($this->stepPause(), (int) (getenv('PBI02_DEMO_PAUSE') ?: 1000)))
            ->screenshot('PBI02/'.$name);
        $devTools = new ChromeDevToolsDriver($browser->driver);
        $size = $devTools->execute('Page.getLayoutMetrics')['cssContentSize'];
        $image = $devTools->execute('Page.captureScreenshot', [
            'format' => 'png', 'captureBeyondViewport' => true,
            'clip' => ['x' => 0, 'y' => 0, 'width' => $size['width'], 'height' => $size['height'], 'scale' => 1],
        ]);
        file_put_contents(Browser::$storeScreenshotsAt.'/PBI02/'.$name.'-full.png', base64_decode($image['data']));
    }

    private function rebuildFixture(): array
    {
        if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== self::$testDatabase) {
            throw new RuntimeException('Fixture PBI-02 hanya boleh memakai database SQLite milik tes ini.');
        }
        Schema::dropIfExists('wilayah');
        Schema::create('wilayah', function (Blueprint $table) {
            $table->id();
            $table->string('kode_referensi')->nullable();
            $table->string('nama_wilayah');
            $table->string('tingkat_wilayah');
            $table->text('alamat_deskriptif');
            // TEXT sengaja hanya pada fixture untuk menyimulasikan record lama/rusak.
            // Kolom DECIMAL pada database riil biasanya menolak teks nonnumerik.
            $table->text('latitude')->nullable();
            $table->text('longitude')->nullable();
            $table->string('kondisi_listrik')->nullable();
            $table->integer('jumlah_kk_terdampak')->nullable();
            $table->string('sumber_data')->nullable();
            $table->date('data_per_tanggal')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
        });
        $geometry = json_decode(file_get_contents(public_path('data/map-boundaries/villages-3205.geojson')), true, 512, JSON_THROW_ON_ERROR);
        $features = array_values(array_filter($geometry['features'], fn ($feature) => $feature['properties']['code'] === '32.05.21.2003'));
        if (count($features) !== 1) {
            throw new RuntimeException('Batas Mekarjaya, Sukaresmi, Garut tidak ditemukan.');
        }
        $shape = $features[0]['geometry'];
        $ring = $shape['type'] === 'Polygon' ? $shape['coordinates'][0] : $shape['coordinates'][0][0];
        $xs = array_column($ring, 0);
        $ys = array_column($ring, 1);
        $coordinates = null;
        // Pilih titik dummy di dalam batas desa yang asli, tanpa mengubah aset batas.
        for ($x = 1; $x < 10 && $coordinates === null; $x++) {
            for ($y = 1; $y < 10; $y++) {
                $point = [round(min($xs) + (max($xs) - min($xs)) * $x / 10, 7), round(min($ys) + (max($ys) - min($ys)) * $y / 10, 7)];
                if (Geometry::contains($shape, $point)) {
                    $coordinates = $point;
                    break;
                }
            }
        }
        if ($coordinates === null) {
            throw new RuntimeException('Titik dummy dalam batas Mekarjaya tidak ditemukan.');
        }
        $id = DB::table('wilayah')->insertGetId([
            'kode_referensi' => '32.05.21.2003', 'nama_wilayah' => 'Mekarjaya', 'tingkat_wilayah' => 'desa_kelurahan',
            'alamat_deskriptif' => 'MEKARJAYA, SUKARESMI, KABUPATEN GARUT, JAWA BARAT',
            'latitude' => $coordinates[1], 'longitude' => $coordinates[0], 'kondisi_listrik' => 'belum_berlistrik',
            'jumlah_kk_terdampak' => 25, 'sumber_data' => 'Fixture Dusk sintetis tahun 2024', 'data_per_tanggal' => '2024-12-31',
        ]);

        return compact('id', 'coordinates');
    }
}
