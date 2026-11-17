<?php

namespace Tests\Browser\PBI03;

use App\Services\Map\Geometry;
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

// Tile dummy hanya disediakan server uji lokal, tanpa meminta tile dari layanan publik.
if (PHP_SAPI === 'cli-server') {
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';
    if (preg_match('~^/_pbi03-tiles/\d+/\d+/\d+\.png$~', $uri)) {
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
 * Laravel Dusk untuk PBI-03: menampilkan lokasi/area wilayah pada peta.
 * Jalankan: php artisan dusk --filter=PBI03Test
 * Seluruh fixture dan operasi tulis hanya menggunakan SQLite sementara.
 */
class PBI03Test extends DuskTestCase
{
    private array $fixture;

    private array $extraFixtures = [];

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
        self::$testDatabase = $directory.'/pbi03-dusk-'.$token.'.sqlite';
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
                'APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_URL' => 'http://127.0.0.1:'.$port,
                'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
                'APP_CONFIG_CACHE' => $directory.'/pbi03-config-'.$token.'.php',
                'APP_ROUTES_CACHE' => $directory.'/pbi03-routes-'.$token.'.php',
                'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => self::$testDatabase, 'DB_URL' => '', 'DATABASE_URL' => '',
                'DB_FOREIGN_KEYS' => 'true', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'cookie',
                'SESSION_COOKIE' => 'pbi03_dusk_'.$token, 'SESSION_SECURE_COOKIE' => 'false', 'SESSION_DOMAIN' => 'null',
                'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array', 'BCRYPT_ROUNDS' => '4',
                'MAP_TILE_URL' => 'http://127.0.0.1:'.$port.'/_pbi03-tiles/{z}/{x}/{y}.png',
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
                PHP_BINARY, '-S', '127.0.0.1:'.$port, '-t', $root.'/public', __FILE__,
            ], $root.'/public', $environment);
            self::$testServer->setTimeout(null)->disableOutput();
            self::$testServer->start();
            self::waitForPort($port, self::$testServer, 'Server Laravel uji');

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
        $this->extraFixtures = [];
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

    /** TC-03-01 | Normal: lima wilayah berkoordinat valid tersedia pada peta. */
    public function test_tc_03_01_lima_lokasi_valid_ditampilkan(): void
    {
        $fixtures = [$this->fixture];
        foreach (['3201', '3202', '3203', '3204'] as $district) {
            $fixtures[] = $this->addSampleRegion($district, 10);
        }

        $this->browse(function (Browser $browser) use ($fixtures) {
            $this->openMap($browser);
            $data = $this->mapData($browser);
            $this->assertSame(5, $data['summary']['total']);
            $this->assertSame(5, $data['summary']['located']);
            $this->assertSame(0, $data['summary']['unlocated']);
            $this->assertSame(5, $this->representedLocations($data['features']));
            foreach ($fixtures as $fixture) {
                $record = collect($data['list']['data'])->firstWhere('id', $fixture['id']);
                $this->assertNotNull($record);
                $this->assertEquals($fixture['coordinates'], $record['location']['coordinates']);
                $this->assertCount(2, $record['location']['coordinates']);
            }
            // UI saat ini menggambar poligon administrasi; field features menyediakan titik API.
            $browser->assertCount('.district-area', 27)
                ->assertSeeIn('[data-map-data-status]', '5 wilayah sesuai filter')
                ->assertSeeIn('[data-map-data-status]', '5 lokasi tersedia')
                ->assertCount('[data-map-rows] tr', 5)
                ->assertAttribute('[data-map-panel]', 'data-state', 'ready')
                ->assertPresent('.province-mask');
        });
    }

    /** TC-03-02 | Normal: warna wilayah dan legenda mengikuti kondisi jumlah KK. */
    public function test_tc_03_02_warna_area_mengikuti_status_data(): void
    {
        $this->addSampleRegion('3201', 0);
        $this->addSampleRegion('3202', null);

        $this->browse(function (Browser $browser) {
            $this->openMap($browser);
            $browser->assertAttribute('.district-area[data-area-code="3205"]', 'fill', '#c53d48')
                ->assertAttribute('.district-area[data-area-code="3201"]', 'fill', '#2f8b63')
                ->assertAttribute('.district-area[data-area-code="3202"]', 'fill', '#b8c4ce')
                ->assertSeeIn('[data-area-legend]', '0%')
                ->assertSeeIn('[data-area-legend]', '>50%')
                ->assertSeeIn('[data-area-legend]', 'Belum ada angka');
        });
    }

    /** TC-03-03 | Negatif: database kosong tetap menampilkan peta tanpa crash. */
    public function test_tc_03_03_data_kosong_menampilkan_pesan(): void
    {
        DB::table('wilayah')->delete();

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
                ->assertAttribute('[data-map-panel]', 'data-state', 'ready')
                ->assertMissing('[data-map-data-retry]');
        });
    }

    /** TC-03-04 | Negatif: satu koordinat kosong tidak menggagalkan empat lokasi lain. */
    public function test_tc_03_04_koordinat_kosong_tidak_menghentikan_peta(): void
    {
        foreach (['3201', '3202', '3203'] as $district) {
            $this->addSampleRegion($district, 10);
        }
        $missing = $this->addSampleRegion('3204', 5, null, true);

        $this->browse(function (Browser $browser) use ($missing) {
            $this->openMap($browser);
            $data = $this->mapData($browser);
            $this->assertSame(5, $data['summary']['total']);
            $this->assertSame(4, $data['summary']['located']);
            $this->assertSame(1, $data['summary']['unlocated']);
            $this->assertSame(4, $this->representedLocations($data['features']));
            $missingRecord = collect($data['list']['data'])->firstWhere('id', $missing['id']);
            $this->assertNotNull($missingRecord);
            $this->assertNull($missingRecord['location']);
            $this->assertNotEmpty($missingRecord['location_unavailable_reason']);
            $browser->assertSeeIn('[data-map-data-status]', '1 lokasi belum tersedia')
                ->assertCount('[data-map-rows] tr', 5)
                ->assertSeeIn('[data-map-rows] tr[data-id="'.$missing['id'].'"]', 'Belum tersedia')
                ->assertMissing('[data-map-data-retry]');
        });
    }

    /** TC-03-05 | Negatif: koordinat di luar rentang tidak menjadi lokasi peta. */
    public function test_tc_03_05_koordinat_di_luar_rentang_diabaikan(): void
    {
        $invalid = $this->addSampleRegion('3201', 7, [106.8, 200]);

        $this->browse(function (Browser $browser) use ($invalid) {
            $this->openMap($browser);
            $data = $this->mapData($browser);
            $this->assertSame(2, $data['summary']['total']);
            $this->assertSame(1, $data['summary']['located']);
            $this->assertSame(1, $data['summary']['unlocated']);
            $this->assertSame(1, $this->representedLocations($data['features']));
            $invalidRecord = collect($data['list']['data'])->firstWhere('id', $invalid['id']);
            $this->assertNotNull($invalidRecord);
            $this->assertNull($invalidRecord['location']);
            $this->assertStringContainsString('tidak lengkap, tidak valid, atau berada di luar batas', $invalidRecord['location_unavailable_reason']);
            $browser->assertSeeIn('[data-map-data-status]', '1 lokasi belum tersedia')
                ->assertAttribute('[data-map-panel]', 'data-state', 'ready');
        });
    }

    /** TC-03-06 | Normal: memilih kabupaten dan desa menampilkan lokasi terpilih. */
    public function test_tc_03_06_pilih_kabupaten_membuka_area_desa(): void
    {
        $selected = $this->addSampleRegion('3201', 10);

        $this->browse(function (Browser $browser) use ($selected) {
            $this->openMap($browser);
            $this->clickArea($browser, '.district-area[data-area-code="3201"]');
            $browser->waitUntil('document.querySelector("[data-region-map]").dataset.areaLevel === "village"', 20)
                ->waitUntil('document.querySelector("[data-map-data-state]").dataset.mapDataState === "ready"', 20)
                ->assertAttribute('[data-region-map]', 'data-area-level', 'village')
                ->assertCount('.district-area', 0)
                ->assertAttribute('[data-map-panel]', 'data-state', 'ready');
            $this->assertGreaterThan(0, (int) $browser->attribute('[data-region-map]', 'data-area-count'));

            $this->clickArea($browser, '.village-area[data-area-code="'.$selected['code'].'"]');
            $browser->waitFor('.selected-area', 15)
                ->waitForTextIn('#map-detail-title', $selected['name'], 15)
                ->assertPresent('[data-map-rows] tr.is-selected[data-id="'.$selected['id'].'"]')
                ->assertAttribute('[data-region-map]', 'data-area-level', 'village');
        });
    }

    /** TC-03-07 | Negatif: filter tanpa hasil menampilkan keadaan kosong dengan jelas. */
    public function test_tc_03_07_pencarian_tanpa_hasil_tidak_crash(): void
    {
        $this->browse(function (Browser $browser) {
            $this->openMap($browser);
            $browser->type('input[name="q"]', 'WILAYAH-TIDAK-ADA-PBI03')
                ->waitUntil('document.querySelector("[data-map-data-state]").dataset.mapDataState === "ready" && document.querySelector("[data-map-empty]").hidden === false', 20)
                ->assertSeeIn('[data-map-data-status]', 'Tidak ada wilayah yang sesuai')
                ->assertVisible('[data-map-empty]')
                ->assertCount('[data-map-rows] tr', 0)
                ->assertCount('.district-area', 27)
                ->assertAttribute('[data-map-panel]', 'data-state', 'ready')
                ->assertMissing('[data-map-data-retry]');
        });
    }

    /** TC-03-08 | Negatif: kegagalan API menampilkan retry, lalu pulih tanpa crash. */
    public function test_tc_03_08_api_gagal_dapat_dicoba_ulang(): void
    {
        $this->browse(function (Browser $browser) {
            $this->openMap($browser);
            $browser->script(<<<'JS'
                window.__pbi03OriginalFetch = window.fetch.bind(window);
                window.fetch = (input, init) => String(input).includes('/peta-wilayah/data')
                    ? Promise.resolve(new Response(JSON.stringify({message: 'Layanan data sedang tidak tersedia.'}), {
                        status: 503, headers: {'Content-Type': 'application/json'}
                    }))
                    : window.__pbi03OriginalFetch(input, init);
                JS);
            $browser->type('input[name="q"]', 'SIMULASI-GAGAL-PBI03')
                ->waitUntil('document.querySelector("[data-map-data-state]").dataset.mapDataState === "error"', 15)
                ->assertSeeIn('[data-map-data-status]', 'Gagal mengambil data wilayah')
                ->assertVisible('[data-map-data-retry]')
                ->assertAttribute('[data-region-map]', 'aria-busy', 'false')
                ->assertPresent('.leaflet-control-zoom');

            $browser->script('window.fetch = window.__pbi03OriginalFetch; delete window.__pbi03OriginalFetch;');
            $browser->click('[data-map-data-retry]')
                ->waitUntil('document.querySelector("[data-map-data-state]").dataset.mapDataState === "ready"', 20)
                ->assertMissing('[data-map-data-retry]')
                ->assertAttribute('[data-map-panel]', 'data-state', 'ready');
        });
    }

    private function openMap(Browser $browser): void
    {
        $browser->resize(1920, 1400)->visit('/peta-wilayah')
            ->waitUntil('document.querySelector("[data-map-data-state]")?.dataset.mapDataState === "ready"', 20)
            ->waitUntil('document.querySelector("[data-boundary-state]")?.dataset.boundaryState === "ready"', 20)
            ->waitUntil('document.querySelector("[data-map-panel]").dataset.state === "ready"', 20)
            ->assertSee('Peta Wilayah Jawa Barat');
    }

    private function mapData(Browser $browser): array
    {
        $response = $browser->driver->executeAsyncScript(
            'const done = arguments[arguments.length - 1]; fetch("/peta-wilayah/data?zoom=19", {headers:{Accept:"application/json"}}).then(async r => done({status:r.status, body:await r.json()})).catch(e => done({status:0, error:String(e)}));'
        );
        $this->assertSame(200, $response['status']);

        return $response['body'];
    }

    private function representedLocations(array $features): int
    {
        return array_sum(array_map(fn ($feature) => $feature['type'] === 'cluster' ? $feature['count'] : 1, $features));
    }

    private function clickArea(Browser $browser, string $selector): void
    {
        $browser->scrollIntoView('[data-region-map]')->pause(300);
        $point = $browser->driver->executeScript(<<<'JS'
            const path = document.querySelector(arguments[0]);
            if (!path) return null;
            const box = path.getBBox(), matrix = path.getScreenCTM();
            for (let y=.05; y<1; y+=.05) for (let x=.05; x<1; x+=.05) {
                const p = new DOMPoint(box.x+box.width*x, box.y+box.height*y).matrixTransform(matrix);
                const px=Math.round(p.x), py=Math.round(p.y);
                if (document.elementFromPoint(px, py) === path) return {x:px,y:py};
            }
            return null;
            JS, [$selector]);
        $this->assertNotNull($point, 'Area tidak mempunyai titik klik yang terlihat: '.$selector);
        $browser->driver->execute(DriverCommand::ACTIONS, ['actions' => [[
            'type' => 'pointer', 'id' => 'pbi03-mouse', 'parameters' => ['pointerType' => 'mouse'],
            'actions' => [
                ['type' => 'pointerMove', 'duration' => 100, 'origin' => 'viewport', 'x' => $point['x'], 'y' => $point['y']],
                ['type' => 'pointerDown', 'button' => 0], ['type' => 'pointerUp', 'button' => 0],
            ],
        ]]]);
    }

    private function addSampleRegion(string $district, ?int $families, ?array $coordinates = null, bool $withoutCoordinates = false): array
    {
        $collection = json_decode(file_get_contents(public_path('data/map-boundaries/villages-'.$district.'.geojson')), true, 512, JSON_THROW_ON_ERROR);
        $province = json_decode(file_get_contents(public_path('data/jawa-barat.geojson')), true, 512, JSON_THROW_ON_ERROR)['geometry'];
        foreach ($collection['features'] as $feature) {
            $properties = $feature['properties'];
            $point = $withoutCoordinates ? null : ($coordinates ?? $this->pointInGeometry($feature['geometry'], $province));
            if ($point === null && ! $withoutCoordinates) continue;

            $districtName = mb_strtoupper($properties['district']);
            if (! preg_match('/^(KABUPATEN|KOTA)\s+/u', $districtName)) {
                $districtName = 'KABUPATEN '.$districtName;
            }
            $id = DB::table('wilayah')->insertGetId([
                'kode_referensi' => $properties['code'], 'nama_wilayah' => $properties['name'],
                'tingkat_wilayah' => 'desa_kelurahan',
                'alamat_deskriptif' => mb_strtoupper($properties['name']).', '.mb_strtoupper($properties['subdistrict']).', '.$districtName.', JAWA BARAT',
                'latitude' => $point[1] ?? null, 'longitude' => $point[0] ?? null, 'kondisi_listrik' => 'belum_berlistrik',
                'jumlah_kk_terdampak' => $families, 'sumber_data' => 'Fixture Dusk PBI-03 tahun 2024',
                'data_per_tanggal' => '2024-12-31',
            ]);

            $fixture = ['id' => $id, 'code' => $properties['code'], 'name' => $properties['name'], 'coordinates' => $point];
            $this->extraFixtures[] = $fixture;

            return $fixture;
        }

        throw new RuntimeException('Tidak dapat membuat fixture lokasi valid untuk kabupaten '.$district.'.');
    }

    private function pointInGeometry(array $geometry, array $province): ?array
    {
        $polygons = match ($geometry['type']) {
            'Polygon' => [$geometry['coordinates']],
            'MultiPolygon' => $geometry['coordinates'],
            default => [],
        };
        foreach ($polygons as $polygon) {
            $ring = $polygon[0] ?? [];
            if (count($ring) < 4) continue;
            $longitudes = array_column($ring, 0);
            $latitudes = array_column($ring, 1);
            for ($x = 1; $x < 20; $x++) {
                for ($y = 1; $y < 20; $y++) {
                    $point = [
                        min($longitudes) + (max($longitudes) - min($longitudes)) * $x / 20,
                        min($latitudes) + (max($latitudes) - min($latitudes)) * $y / 20,
                    ];
                    if (Geometry::contains($geometry, $point) && Geometry::contains($province, $point)) {
                        return [round($point[0], 7), round($point[1], 7)];
                    }
                }
            }
        }

        return null;
    }

    private function rebuildFixture(): array
    {
        if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== self::$testDatabase) {
            throw new RuntimeException('Fixture PBI-03 hanya boleh memakai database SQLite milik tes ini.');
        }
        Schema::dropIfExists('wilayah');
        Schema::create('wilayah', function (Blueprint $table) {
            $table->id();
            $table->string('kode_referensi')->nullable();
            $table->string('nama_wilayah');
            $table->string('tingkat_wilayah');
            $table->text('alamat_deskriptif');
            // TEXT memungkinkan skenario koordinat rusak tanpa bergantung batasan DECIMAL MySQL.
            $table->text('latitude')->nullable();
            $table->text('longitude')->nullable();
            $table->string('kondisi_listrik')->nullable();
            $table->integer('jumlah_kk_terdampak')->nullable();
            $table->string('sumber_data')->nullable();
            $table->date('data_per_tanggal')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
        });

        $collection = json_decode(file_get_contents(public_path('data/map-boundaries/villages-3205.geojson')), true, 512, JSON_THROW_ON_ERROR);
        foreach ($collection['features'] as $feature) {
            if ($feature['properties']['code'] === '32.05.21.2003') {
                $properties = $feature['properties'];
                $point = $this->pointInGeometry($feature['geometry'], json_decode(file_get_contents(public_path('data/jawa-barat.geojson')), true, 512, JSON_THROW_ON_ERROR)['geometry']);
                if ($point === null) break;
                $fixture = $this->insertRegion($properties, $point, 25);

                return $fixture;
            }
        }

        throw new RuntimeException('Batas Mekarjaya, Sukaresmi, Garut tidak ditemukan atau tidak memiliki titik dalam batas.');
    }

    private function insertRegion(array $properties, ?array $point, ?int $families): array
    {
        $districtName = mb_strtoupper($properties['district']);
        if (! preg_match('/^(KABUPATEN|KOTA)\s+/u', $districtName)) {
            $districtName = 'KABUPATEN '.$districtName;
        }
        $id = DB::table('wilayah')->insertGetId([
            'kode_referensi' => $properties['code'], 'nama_wilayah' => $properties['name'],
            'tingkat_wilayah' => 'desa_kelurahan',
            'alamat_deskriptif' => mb_strtoupper($properties['name']).', '.mb_strtoupper($properties['subdistrict']).', '.$districtName.', JAWA BARAT',
            'latitude' => $point[1] ?? null, 'longitude' => $point[0] ?? null,
            'kondisi_listrik' => 'belum_berlistrik', 'jumlah_kk_terdampak' => $families,
            'sumber_data' => 'Fixture Dusk PBI-03 tahun 2024', 'data_per_tanggal' => '2024-12-31',
        ]);

        return ['id' => $id, 'code' => $properties['code'], 'name' => $properties['name'], 'coordinates' => $point];
    }

    private static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        if ($socket === false) throw new RuntimeException('Port uji tidak tersedia: '.$error);
        $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        return $port;
    }

    private static function waitForPort(int $port, Process $process, string $label): void
    {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            if (! $process->isRunning()) throw new RuntimeException($label.' gagal dimulai.');
            $socket = @fsockopen('127.0.0.1', $port, $errno, $error, 0.1);
            if ($socket !== false) { fclose($socket); return; }
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
                if (is_file($file)) unlink($file);
            }
            self::$testDatabase = null;
        }
        foreach (self::$originalEnvironment as $key => $original) {
            if ($original['env'] === null) unset($_ENV[$key]); else $_ENV[$key] = $original['env'];
            if ($original['server'] === null) unset($_SERVER[$key]); else $_SERVER[$key] = $original['server'];
            putenv($original['process'] === false ? $key : $key.'='.$original['process']);
        }
        self::$originalEnvironment = [];
    }
}
