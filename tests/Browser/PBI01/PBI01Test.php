<?php

namespace Tests\Browser\PBI01;

use App\Models\User;
use App\Models\Wilayah;
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

/**
 * Jalankan: php artisan dusk --filter=PBI01Test
 * Chrome, server lokal, skema, dan fixture disiapkan di file ini.
 * Seluruh operasi tulis hanya menuju SQLite sementara, bukan MySQL/TiDB.
 */
class PBI01Test extends DuskTestCase
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
        self::$testDatabase = $directory.'/pbi01-dusk-'.$token.'.sqlite';
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
                'APP_CONFIG_CACHE' => $directory.'/pbi01-config-'.$token.'.php',
                'APP_ROUTES_CACHE' => $directory.'/pbi01-routes-'.$token.'.php',
                'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => self::$testDatabase, 'DB_URL' => '', 'DATABASE_URL' => '',
                'DB_FOREIGN_KEYS' => 'true', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'cookie',
                'SESSION_COOKIE' => 'pbi01_dusk_'.$token, 'SESSION_SECURE_COOKIE' => 'false', 'SESSION_DOMAIN' => 'null',
                'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array', 'BCRYPT_ROUNDS' => '4',
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
                $root.'/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php',
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

    /** Chrome selalu terlihat untuk PBI-01, tanpa mengubah DuskTestCase PBI lain. */
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

    /** TC-17-01 | Normal: tambah desa dengan koordinat dan KK yang valid. */
    public function test_tc_17_01_tambah_wilayah(): void
    {
        $this->browse(function (Browser $browser) {
            $this->openManager($browser);
            $this->fillForm($browser);
            $browser->click('[data-save]')->waitForText('Wilayah berhasil ditambahkan.')
                ->waitForTextIn('[data-rows]', 'Desa Harapan Uji')->pause($this->stepPause());
            $this->assertDatabaseHas('wilayah', ['nama_wilayah' => 'Desa Harapan Uji',
                'jumlah_kk_terdampak' => 25, 'created_by' => $this->fixture['petugas']->id]);
            $browser->refresh()->waitForTextIn('[data-rows]', 'Desa Harapan Uji');
            $this->capture($browser, 'TC-17-01-tambah-berhasil');
        });
    }

    /** TC-17-02 | Normal: edit KK Mekarjaya; riwayat masyarakat tetap utuh. */
    public function test_tc_17_02_edit_wilayah(): void
    {
        $history = $this->submissionHistory();
        $this->browse(function (Browser $browser) {
            $this->openManager($browser);
            $browser->click('button[aria-label="Edit Mekarjaya"]')
                ->waitUntil('document.querySelector("#nama_wilayah").value === "Mekarjaya" && !document.querySelector("#jumlah_kk_terdampak").disabled')
                ->pause($this->stepPause())
                ->typeSlowly('jumlah_kk_terdampak', '42', $this->typingPause())->pause($this->stepPause())
                ->click('[data-save]')
                ->waitForText('Perubahan wilayah berhasil disimpan.')
                ->waitForTextIn($this->row('mekarjaya'), '42')->pause($this->stepPause());
            $browser->refresh()->waitForTextIn($this->row('mekarjaya'), '42');
            $this->capture($browser, 'TC-17-02-edit-kk-berhasil');
        });
        $this->assertDatabaseHas('wilayah', ['id' => $this->fixture['mekarjaya']->id, 'jumlah_kk_terdampak' => 42,
            'created_by' => $this->fixture['petugas']->id]);
        $this->assertSame($history, $this->submissionHistory());
    }

    /** TC-17-03 | Normal: setujui dialog hapus untuk desa tanpa relasi. */
    public function test_tc_17_03_hapus_wilayah(): void
    {
        $history = $this->submissionHistory();
        $this->browse(function (Browser $browser) {
            $this->openManager($browser);
            $browser->click('button[aria-label="Hapus Desa Uji Hapus"]')->waitForDialog()
                ->assertDialogOpened('Hapus wilayah “Desa Uji Hapus”? Tindakan ini tidak dapat dibatalkan.')
                ->pause($this->stepPause())->acceptDialog()->waitForText('Wilayah berhasil dihapus.')
                ->waitUntilMissing($this->row('hapus'))->assertDontSeeIn('[data-rows]', 'Desa Uji Hapus');
            $browser->refresh()->waitForTextIn('[data-rows]', 'Mekarjaya')->assertMissing($this->row('hapus'));
            $this->capture($browser, 'TC-17-03-hapus-berhasil');
        });
        $this->assertDatabaseMissing('wilayah', ['id' => $this->fixture['hapus']->id]);
        $this->assertSame($history, $this->submissionHistory());
    }

    /** TC-17-04 | Pengecualian: nama desa/alamat yang memuat kecamatan kosong. */
    public function test_tc_17_04_kolom_wajib_kosong(): void
    {
        $this->browse(function (Browser $browser) {
            $this->openManager($browser);
            $this->fillForm($browser);
            $browser->clear('nama_wilayah')->pause($this->stepPause())
                ->clear('alamat_deskriptif')->pause($this->stepPause())->click('[data-save]')
                ->waitForTextIn('#error-nama_wilayah', 'Nama wilayah wajib diisi.')
                ->assertSeeIn('#error-alamat_deskriptif', 'Alamat lengkap wajib diisi.')
                ->assertAttribute('#nama_wilayah', 'aria-invalid', 'true')->pause($this->stepPause());
            $this->capture($browser, 'TC-17-04-validasi-wajib');
            $browser->click('[data-cancel]')->waitForDialog()->pause($this->stepPause())->acceptDialog();
        });
        $this->assertDatabaseCount('wilayah', 2);
    }

    /** TC-17-05 | Pengecualian: lintang >90 dan bujur >180 ditolak. */
    public function test_tc_17_05_koordinat_tidak_valid(): void
    {
        $this->browse(function (Browser $browser) {
            $this->openManager($browser);
            $this->fillForm($browser);
            $browser->typeSlowly('latitude', '91', $this->typingPause())->pause($this->stepPause())
                ->typeSlowly('longitude', '181', $this->typingPause())->pause($this->stepPause())->click('[data-save]')
                ->waitForTextIn('#error-latitude', 'Masukkan angka antara -90 dan 90')
                ->assertSeeIn('#error-longitude', 'Masukkan angka antara -180 dan 180')
                ->assertAttribute('#longitude', 'aria-invalid', 'true')->pause($this->stepPause());
            $this->capture($browser, 'TC-17-05-validasi-koordinat');
            $browser->click('[data-cancel]')->waitForDialog()->pause($this->stepPause())->acceptDialog();
        });
        $this->assertDatabaseCount('wilayah', 2);
    }

    /** TC-17-06 | Pengecualian: batalkan dialog native, data tetap ada. */
    public function test_tc_17_06_batal_hapus(): void
    {
        $history = $this->submissionHistory();
        $this->browse(function (Browser $browser) {
            $this->openManager($browser);
            $browser->click('button[aria-label="Hapus Desa Uji Hapus"]')->waitForDialog()
                ->assertDialogOpened('Hapus wilayah “Desa Uji Hapus”? Tindakan ini tidak dapat dibatalkan.')
                ->pause($this->stepPause())->dismissDialog()->assertSeeIn($this->row('hapus'), 'Desa Uji Hapus');
            $browser->refresh()->waitForTextIn($this->row('hapus'), 'Desa Uji Hapus');
            $this->capture($browser, 'TC-17-06-batal-hapus-data-utuh');
        });
        $this->assertDatabaseHas('wilayah', ['id' => $this->fixture['hapus']->id]);
        $this->assertSame($history, $this->submissionHistory());
    }

    /** TC-17-07 | Pengecualian: akun Masyarakat mendapat HTTP 403. */
    public function test_tc_17_07_akses_masyarakat_ditolak(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->resize(1920, 1400)->loginAs($this->fixture['masyarakat'])->visit('/pemerintah/wilayah')
                ->waitForText('403')->assertSee('HANYA AKUN PEMERINTAH')->assertMissing('[data-region-manager]');
            $status = $browser->driver->executeAsyncScript('const done = arguments[arguments.length - 1]; fetch("/pemerintah/wilayah").then(r => done(r.status)).catch(() => done(0));');
            $this->assertSame(403, $status);
            $this->capture($browser, 'TC-17-07-masyarakat-403');
        });
        $this->assertDatabaseCount('wilayah', 2);
    }

    private function openManager(Browser $browser): void
    {
        $browser->resize(1920, 1400)->loginAs($this->fixture['petugas'])->visit('/pemerintah/wilayah')
            ->waitForTextIn('[data-list-status]', '2 wilayah ditemukan')
            ->assertSee('Kelola data wilayah')->assertSee('Petugas ESDM Uji')->pause($this->stepPause());
    }

    private function fillForm(Browser $browser): void
    {
        foreach ($this->regionPayload() as $name => $value) {
            if ($name === 'kondisi_listrik') {
                $browser->select($name, $value);
            } else {
                $browser->typeSlowly($name, (string) $value, $this->typingPause());
            }
            $browser->pause($this->stepPause());
        }
    }

    /** Jeda demo per langkah, dalam milidetik. Isi 0 untuk menjalankan tanpa jeda. */
    private function stepPause(): int
    {
        $value = getenv('DUSK_STEP_PAUSE');

        return $value !== false && ctype_digit($value) ? min((int) $value, 10000) : 2000;
    }

    private function typingPause(): int
    {
        return min(100, intdiv($this->stepPause(), 40));
    }

    private function row(string $fixture): string
    {
        return '[data-rows] tr[data-id="'.$this->fixture[$fixture]->id.'"]';
    }

    private function capture(Browser $browser, string $name): void
    {
        $browser->script('window.scrollTo(0, 0);');
        $browser->pause(max($this->stepPause(), (int) (getenv('PBI01_DEMO_PAUSE') ?: 750)))
            ->screenshot('PBI01/'.$name);
        // Rekam halaman utuh, termasuk bagian formulir di bawah viewport desktop.
        $devTools = new ChromeDevToolsDriver($browser->driver);
        $size = $devTools->execute('Page.getLayoutMetrics')['cssContentSize'];
        $image = $devTools->execute('Page.captureScreenshot', [
            'format' => 'png', 'captureBeyondViewport' => true,
            'clip' => ['x' => 0, 'y' => 0, 'width' => $size['width'], 'height' => $size['height'], 'scale' => 1],
        ]);
        file_put_contents(Browser::$storeScreenshotsAt.'/PBI01/'.$name.'-full.png', base64_decode($image['data']));
    }

    private function rebuildFixture(): array
    {
        $connection = DB::connection();
        $path = $connection->getDatabaseName();
        $directory = realpath(storage_path('framework/testing'));
        if ($connection->getDriverName() !== 'sqlite'
            || ($path !== ':memory:' && (! $directory || realpath(dirname($path)) !== $directory
                || ! preg_match('/^pbi01-dusk-[a-f0-9]+\.sqlite$/', basename($path))))) {
            throw new RuntimeException('PBI-01 hanya boleh mengatur ulang database SQLite uji yang terisolasi.');
        }
        foreach (['riwayat_status_pengajuan', 'lokasi_pengajuan', 'progres_elektrifikasi', 'pengajuan_plts', 'wilayah', 'users'] as $table) {
            Schema::dropIfExists($table);
        }
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
        Schema::create('lokasi_pengajuan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->unique()->constrained('pengajuan_plts')->restrictOnDelete();
            $table->text('alamat_deskriptif');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 11, 7)->nullable();
        });
        Schema::create('riwayat_status_pengajuan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->constrained('pengajuan_plts')->restrictOnDelete();
            $table->foreignId('diubah_oleh')->constrained('users')->restrictOnDelete();
            $table->string('status_sebelumnya')->nullable();
            $table->string('status_baru');
            $table->string('jenis_tindakan');
            $table->string('hasil_verifikasi')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamp('created_at');
        });
        Schema::create('progres_elektrifikasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wilayah_id')->constrained('wilayah')->restrictOnDelete();
        });
        $petugas = $this->createFixtureUser('Petugas ESDM Uji', 'pemerintah');
        $masyarakat = $this->createFixtureUser('Masyarakat Uji', 'masyarakat');
        $mekarjaya = new Wilayah($this->regionPayload('Mekarjaya'));
        $mekarjaya->created_by = $petugas->id;
        $mekarjaya->save();
        $hapus = new Wilayah($this->regionPayload('Desa Uji Hapus'));
        $hapus->created_by = $petugas->id;
        $hapus->save();
        $pengajuan = DB::table('pengajuan_plts')->insertGetId([
            'nomor_tiket' => 'PLTS-PBI01-001', 'user_id' => $masyarakat->id, 'wilayah_id' => $mekarjaya->id,
            'ringkasan_pengajuan' => 'Pengajuan mandiri sebelum pembaruan master',
            'status_terkini' => 'diajukan', 'status_diperbarui_pada' => '2026-11-01 08:00:00',
        ]);
        DB::table('lokasi_pengajuan')->insert([
            'pengajuan_id' => $pengajuan, 'alamat_deskriptif' => 'Kampung Awal RT 01 RW 02, Mekarjaya',
            'latitude' => -6.92, 'longitude' => 107.62,
        ]);
        DB::table('riwayat_status_pengajuan')->insert([
            'pengajuan_id' => $pengajuan, 'diubah_oleh' => $masyarakat->id, 'status_baru' => 'diajukan',
            'jenis_tindakan' => 'pengajuan_baru', 'catatan' => 'Catatan asli masyarakat', 'created_at' => '2026-11-01 08:00:00',
        ]);

        return compact('petugas', 'masyarakat', 'mekarjaya', 'hapus');
    }

    private function regionPayload(string $nama = 'Desa Harapan Uji'): array
    {
        return [
            'nama_wilayah' => $nama, 'tingkat_wilayah' => 'desa_kelurahan',
            'alamat_deskriptif' => $nama.', Kecamatan Uji, Kabupaten Bandung, Jawa Barat',
            'latitude' => '-6.9175', 'longitude' => '107.6191', 'jumlah_kk_terdampak' => 25,
            'kondisi_listrik' => 'belum_berlistrik',
        ];
    }

    private function submissionHistory(): array
    {
        $snapshot = [];
        foreach (['pengajuan_plts', 'lokasi_pengajuan', 'riwayat_status_pengajuan'] as $table) {
            $snapshot[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        return $snapshot;
    }

    private function createFixtureUser(string $name, string $role): User
    {
        $user = new User(['name' => $name, 'email' => $role.'@pbi01.example.test', 'password' => 'password-khusus-uji']);
        $user->role = $role;
        $user->save();

        return $user;
    }
}
