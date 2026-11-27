<?php

namespace Tests\Browser\PBI24;

use App\Models\StaffAccessProfile;
use App\Services\StaffProfileManager;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Support\Facades\DB;
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
    if ($uri === '/_pbi24-data-fail') {
        http_response_code(503);
        header('Content-Type: application/json');
        echo json_encode(['message' => 'Data dashboard belum dapat dimuat. Periksa koneksi lalu coba kembali.']);

        return;
    }
    if (preg_match('~^/_pbi24-tiles/\d+/\d+/\d+\.png$~', $uri)) {
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
 * Jalankan: php artisan dusk --filter=PBI24Test
 * Chrome, server lokal, skema, dan fixture disiapkan di file ini.
 * Seluruh operasi tulis hanya menuju SQLite sementara, bukan MySQL/TiDB.
 */
class PBI24Test extends DuskTestCase
{
    use PBI24Fixture;

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
        self::$testDatabase = $directory.'/pbi24-dusk-'.$token.'.sqlite';
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
                'APP_CONFIG_CACHE' => $directory.'/pbi24-config-'.$token.'.php',
                'APP_ROUTES_CACHE' => $directory.'/pbi24-routes-'.$token.'.php',
                'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => self::$testDatabase, 'DB_URL' => '', 'DATABASE_URL' => '',
                'DB_FOREIGN_KEYS' => 'true', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'cookie',
                // Snapshot peta mempunyai store terpisah. Fixture memakai query builder
                // (tanpa event model), jadi tiap request harus membaca data skenario terbaru.
                'MAP_SNAPSHOT_CACHE_STORE' => 'array',
                'SESSION_COOKIE' => 'pbi24_dusk_'.$token, 'SESSION_SECURE_COOKIE' => 'false', 'SESSION_DOMAIN' => 'null',
                'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array', 'BCRYPT_ROUNDS' => '4',
                'MAP_TILE_URL' => 'http://127.0.0.1:'.$port.'/_pbi24-tiles/{z}/{x}/{y}.png',
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
            static::startChromeDriver(['--port='.self::$driverPort, '--log-path='.$directory.'/pbi24-chrome.log']);
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

    /** Chrome selalu terlihat untuk PBI-24, tanpa mengubah DuskTestCase PBI lain. */
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

    public function test_tc_24_01_buka_cari_dan_rincian_profil(): void
    {
        $profile = $this->accessProfile();
        $this->browse(function (Browser $b) {
            $this->admin($b, '/admin/pengguna');
            $b->clickLink('Kelola hak akses')->waitForText('Kelola hak akses petugas')
                ->type('q', 'Verifikasi')->press('Cari')->waitForText('Petugas Verifikasi')
                ->clickLink('Rincian')->waitForText('Rincian izin')->assertSee('Melihat daftar dan rincian wilayah')
                ->assertSee('Belum ada petugas yang menggunakan profil ini.')
                ->assertAttribute('#sidebar a[aria-current]', 'href', url('/admin/pengguna'));
            $this->capture($b, 'TC-24-01-rincian-profil');
        });
    }

    public function test_tc_24_02_tambah_petugas_verifikasi_dan_audit(): void
    {
        $this->browse(function (Browser $b) {
            $this->admin($b);
            $this->createViaGui($b, 'Petugas Verifikasi');
            $profile = StaffAccessProfile::where('name', 'Petugas Verifikasi')->firstOrFail();
            $this->assertSame(['wilayah.view'], $profile->permissionCodes());
            $this->assertDatabaseHas('admin_activity_logs', ['target_id' => $profile->id, 'action' => 'access.profile.created']);
            $this->capture($b, 'TC-24-02-tambah-profil');
        });
    }

    public function test_tc_24_03_ubah_deskripsi_tambah_dan_cabut_izin(): void
    {
        $profile = $this->accessProfile();
        $this->browse(function (Browser $b) use ($profile) {
            $this->admin($b, '/admin/hak-akses/'.$profile->id.'/edit');
            $b->type('description', 'Tugas dashboard terbaru')->uncheck('permissions[]', 'wilayah.view')->check('permissions[]', 'dashboard.view')->press('Tinjau dampak')
                ->waitForText('Tinjau perubahan profil')->assertSee('Izin sebelumnya')->assertSee('Izin setelah disimpan');
            $this->confirmProfile($b);
            $this->assertSame(['dashboard.view'], $profile->permissionCodes());
            $this->assertSame('Tugas dashboard terbaru', $profile->fresh()->description);
            $this->capture($b, 'TC-24-03-ubah-izin');
        });
    }

    public function test_tc_24_04_tetapkan_satu_profil_ke_petugas(): void
    {
        $profile = $this->accessProfile();
        $this->browse(function (Browser $b) use ($profile) {
            $this->admin($b, '/admin/hak-akses/petugas/'.$this->fixture['staff']->id);
            $this->assignViaGui($b, $profile);
            $this->assertDatabaseHas('staff_profile_assignments', ['user_id' => $this->fixture['staff']->id, 'profile_id' => $profile->id]);
            $this->assertSame(1, DB::table('staff_profile_assignments')->where('user_id', $this->fixture['staff']->id)->count());
            $this->capture($b, 'TC-24-04-penetapan-profil');
        });
    }

    public function test_tc_24_05_petugas_memakai_modul_dan_tindakan_diizinkan(): void
    {
        $profile = $this->accessProfile('Petugas Input', ['wilayah.view', 'wilayah.create']);
        $this->bind($profile);
        $this->browse(function (Browser $b) {
            $b->resize(1440, 1050)->loginAs($this->fixture['staff'])->visit('/pemerintah/wilayah')->waitForTextIn('[data-rows]', 'Mekarjaya')
                ->type('nama_wilayah', 'Desa Izin Uji')->type('tingkat_wilayah', 'desa_kelurahan')
                ->type('alamat_deskriptif', 'Kabupaten Bandung, Jawa Barat')->click('[data-save]')
                ->waitForText('Wilayah berhasil ditambahkan.')->waitForTextIn('[data-rows]', 'Desa Izin Uji');
            $this->assertDatabaseHas('wilayah', ['nama_wilayah' => 'Desa Izin Uji', 'created_by' => $this->fixture['staff']->id]);
            $this->capture($b, 'TC-24-05-tindakan-diizinkan');
        });
    }

    public function test_tc_24_06_cabut_izin_pada_sesi_petugas_terbuka(): void
    {
        $profile = $this->accessProfile();
        $this->bind($profile);
        $this->browse(function (Browser $admin, Browser $staff) use ($profile) {
            $staff->loginAs($this->fixture['staff'])->visit('/pemerintah/wilayah')->waitForText('Kelola data wilayah');
            $this->admin($admin, '/admin/hak-akses/'.$profile->id.'/edit');
            $admin->uncheck('permissions[]', 'wilayah.view')->press('Tinjau dampak')->waitForText('1 petugas terkait');
            $this->confirmProfile($admin);
            $staff->refresh()->assertSee('403');
            $this->capture($staff, 'TC-24-06-sesi-izin-dicabut');
        });
    }

    public function test_tc_24_07_nonaktifkan_profil_dan_pertahankan_riwayat(): void
    {
        $profile = $this->accessProfile();
        $this->bind($profile);
        $users = DB::table('users')->get()->toJson();
        $this->browse(function (Browser $b) use ($profile, $users) {
            $this->admin($b, '/admin/hak-akses/'.$profile->id.'/edit');
            $b->select('is_active', '0')->press('Tinjau dampak')->waitForText('Profil berhenti memberikan seluruh izinnya');
            $this->capture($b, 'TC-24-07-dampak-penonaktifan');
            $this->confirmProfile($b);
            $this->assertFalse($profile->fresh()->is_active);
            $this->assertSame($users, DB::table('users')->get()->toJson());
            $this->assertDatabaseHas('staff_profile_assignments', ['profile_id' => $profile->id]);
            $b->loginAs($this->fixture['staff'])->visit('/pemerintah/wilayah')->assertSee('403');
        });
    }

    public function test_tc_24_08_hapus_profil_yang_belum_pernah_ditetapkan(): void
    {
        $this->browse(function (Browser $b) {
            $this->admin($b);
            $this->createViaGui($b, 'Profil Hapus Uji');
            $profile = StaffAccessProfile::where('name', 'Profil Hapus Uji')->firstOrFail();
            $b->visit('/admin/hak-akses/'.$profile->id.'/hapus')->check('confirmed')->press('Hapus profil')
                ->waitForLocation('/admin/hak-akses')->assertSee('Profil hak akses berhasil dihapus.');
            $this->assertDatabaseMissing('staff_access_profiles', ['id' => $profile->id]);
            $this->assertDatabaseHas('admin_activity_logs', ['target_id' => $profile->id, 'subject_name' => 'Profil Hapus Uji', 'action' => 'access.profile.deleted']);
            $this->capture($b, 'TC-24-08-hapus-profil');
        });
    }

    public function test_tc_24_09_validasi_nama_dan_izin_tidak_dikenal(): void
    {
        $this->browse(function (Browser $b) {
            $this->admin($b, '/admin/hak-akses/tambah');
            $b->script('document.querySelector("[data-access-submit]").noValidate=true');
            $b->press('Tinjau dampak')->waitForText('Nama profil wajib diisi.')
                ->type('name', '  PETUGAS   PEMERINTAH ')->press('Tinjau dampak')->waitForText('Nama profil sudah digunakan');
            $b->type('name', 'Profil Permintaan Tidak Sah')->check('permissions[]', 'wilayah.view');
            $b->script('document.querySelector("input[value=\"wilayah.view\"]").value="izin.tidak.dikenal"');
            $b->press('Tinjau dampak')->waitForText('Kode izin tidak dikenal.');
            $this->assertDatabaseMissing('staff_access_profiles', ['name' => 'Profil Permintaan Tidak Sah']);
            $this->capture($b, 'TC-24-09-validasi-formulir');
        });
    }

    public function test_tc_24_10_tolak_penetapan_peran_tidak_sesuai(): void
    {
        $profile = $this->accessProfile();
        $this->browse(function (Browser $b) use ($profile) {
            $this->admin($b, '/admin/hak-akses/petugas/'.$this->fixture['society']->id);
            $b->assertSee('Profil hanya dapat ditetapkan kepada akun Pemerintah yang aktif.');
            $this->assertSame(422, $this->request($b, 'POST', '/admin/hak-akses/penetapan', $this->assignmentInput($this->fixture['society'], $profile))['status']);
            $this->assertDatabaseMissing('staff_profile_assignments', ['user_id' => $this->fixture['society']->id]);
            $this->capture($b, 'TC-24-10-peran-tidak-sesuai');
        });
    }

    public function test_tc_24_11_tolak_hapus_profil_yang_digunakan(): void
    {
        $profile = $this->accessProfile();
        $this->bind($profile);
        $this->browse(function (Browser $b) use ($profile) {
            $this->admin($b, '/admin/hak-akses/'.$profile->id.'/hapus');
            $b->assertSee('Profil tidak dapat dihapus.')->assertMissing('button.access-danger');
            $this->assertSame(422, $this->request($b, 'DELETE', '/admin/hak-akses/'.$profile->id, $this->profileInput($profile))['status']);
            $this->assertDatabaseHas('staff_access_profiles', ['id' => $profile->id]);
            $this->capture($b, 'TC-24-11-profil-masih-digunakan');
        });
    }

    public function test_tc_24_12_tolak_non_admin_dan_eskalasi_sendiri(): void
    {
        $profile = $this->accessProfile();
        $this->browse(function (Browser $b) use ($profile) {
            foreach (['staff', 'society'] as $role) {
                $b->loginAs($this->fixture[$role])->visit('/admin/hak-akses')->assertSee('403')->visit('/');
                $this->assertSame(403, $this->request($b, 'POST', '/admin/hak-akses/penetapan', $this->assignmentInput($this->fixture['staff'], $profile))['status']);
            }
            $b->visit('/admin/hak-akses')->assertSee('403');
            $this->capture($b, 'TC-24-12-akses-admin-ditolak');
        });
    }

    public function test_tc_24_13_url_dan_tindakan_tanpa_izin_ditolak(): void
    {
        $profile = $this->accessProfile();
        $this->bind($profile);
        $this->browse(function (Browser $b) {
            $b->loginAs($this->fixture['staff'])->visit('/pemerintah/wilayah')->waitForTextIn('[data-rows]', 'Mekarjaya')
                ->assertMissing('[data-add]')->assertMissing('.form-panel')->assertSee('Hanya baca');
            $this->assertSame(403, $this->request($b, 'POST', '/pemerintah/wilayah', [])['status']);
            $b->visit('/pemerintah/dashboard')->assertSee('403');
            $this->capture($b, 'TC-24-13-tindakan-tanpa-izin');
        });
    }

    public function test_tc_24_14_batal_perubahan_dan_penghapusan(): void
    {
        $profile = $this->accessProfile();
        $before = DB::table('admin_activity_logs')->count();
        $this->browse(function (Browser $b) use ($profile, $before) {
            $this->admin($b, '/admin/hak-akses/'.$profile->id.'/edit');
            $b->type('name', 'Nama Tidak Disimpan')->press('Tinjau dampak')->waitForText('Tinjau perubahan profil')
                ->clickLink('Batalkan perubahan')->waitForText('Rincian izin');
            $b->visit('/admin/hak-akses/'.$profile->id.'/hapus')->clickLink('Batal')->waitForText('Rincian izin');
            $this->assertSame('Petugas Verifikasi', $profile->fresh()->name);
            $this->assertSame($before, DB::table('admin_activity_logs')->count());
            $this->capture($b, 'TC-24-14-pembatalan');
        });
    }

    public function test_tc_24_15_penetapan_baru_sebelum_konfirmasi_hapus_tetap_utuh(): void
    {
        $profile = $this->accessProfile();
        $this->browse(function (Browser $first, Browser $second) use ($profile) {
            $this->admin($first, '/admin/hak-akses/'.$profile->id.'/hapus');
            $first->check('confirmed');
            $this->admin($second, '/admin/hak-akses/petugas/'.$this->fixture['staff']->id);
            $this->assignViaGui($second, $profile);
            $first->press('Hapus profil')->waitForText('Profil atau penetapan petugas sudah berubah.');
            $this->assertDatabaseHas('staff_profile_assignments', ['user_id' => $this->fixture['staff']->id, 'profile_id' => $profile->id]);
            $this->assertDatabaseHas('staff_access_profiles', ['id' => $profile->id]);
            $this->capture($first, 'TC-24-15-penetapan-baru-dipertahankan');
        });
    }

    public function test_tc_24_16_audit_gagal_rollback_dan_isian_dipertahankan(): void
    {
        DB::unprepared("CREATE TRIGGER pbi24_audit_failure BEFORE INSERT ON admin_activity_logs BEGIN SELECT RAISE(ABORT, 'audit fixture unavailable'); END");
        try {
            $this->browse(function (Browser $b) {
                $this->admin($b, '/admin/hak-akses/tambah');
                $b->type('name', 'Profil Audit Gagal')->type('description', 'Isian tetap dipertahankan')->check('permissions[]', 'wilayah.view')->press('Tinjau dampak');
                $b->waitForText('Tinjau perubahan profil')->check('confirmed')->press('Konfirmasi dan simpan')
                    ->waitForText('Perubahan belum dapat disimpan.')->assertInputValue('name', 'Profil Audit Gagal')
                    ->assertInputValue('description', 'Isian tetap dipertahankan');
                $this->assertDatabaseMissing('staff_access_profiles', ['name' => 'Profil Audit Gagal']);
                $this->capture($b, 'TC-24-16-audit-gagal-rollback');
            });
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS pbi24_audit_failure');
        }
    }

    public function test_kp_24_13_tampilan_seluler_dan_keadaan_kosong(): void
    {
        DB::table('staff_profile_assignments')->delete();
        DB::table('staff_profile_permissions')->delete();
        DB::table('staff_access_profiles')->delete();
        $this->browse(function (Browser $b) {
            $this->admin($b);
            $b->assertSee('Belum ada profil hak akses.')->resize(390, 844)->pause(400)
                ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
                ->click('[data-drawer-open]')->waitFor('#sidebar a[aria-current]')
                ->click('#sidebar [data-drawer-close]')->clickLink('Tambah profil')->waitForText('Tambah profil hak akses')
                ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
            $this->capture($b, 'KP-24-13-seluler-dan-kosong');
        });
    }

    private function admin(Browser $b, string $path = '/admin/hak-akses'): void
    {
        $b->resize(1440, 1050)->loginAs($this->fixture['admin'])->visit($path)->waitFor('h1');
    }

    private function createViaGui(Browser $b, string $name): void
    {
        $b->visit('/admin/hak-akses/tambah')->type('name', $name)->type('description', 'Profil tugas pendataan')
            ->check('permissions[]', 'wilayah.view')->press('Tinjau dampak')->waitForText('Tinjau perubahan profil');
        $this->confirmProfile($b);
        $b->assertSee($name);
    }

    private function confirmProfile(Browser $b): void
    {
        $b->check('confirmed')->press('Konfirmasi dan simpan')->waitForLocation('/admin/hak-akses');
    }

    private function assignViaGui(Browser $b, StaffAccessProfile $profile): void
    {
        $b->select('profile_id', (string) $profile->id)->press('Tinjau penetapan')->waitForText('Tinjau penetapan profil')
            ->check('confirmed')->press('Konfirmasi penetapan')->waitForLocation('/admin/hak-akses')->assertSee('Profil petugas berhasil ditetapkan.');
    }

    private function bind(StaffAccessProfile $profile): void
    {
        $data = $this->assignmentInput($this->fixture['staff'], $profile);
        app(StaffProfileManager::class)->assign($this->fixture['admin'], $data['user_id'], $data['profile_id'], $data['expected_profile_id'], $data['expected_version']);
    }

    private function request(Browser $b, string $method, string $url, array $data): array
    {
        $script = 'window.pbi24Result=null; fetch('.json_encode($url).',{method:'.json_encode($method).',headers:{Accept:"application/json","Content-Type":"application/json","X-CSRF-TOKEN":document.querySelector("input[name=_token]")?.value || ""},body:JSON.stringify('.json_encode($data).')}).then(async r=>window.pbi24Result={status:r.status,body:await r.json()})';
        $b->script($script);
        $b->waitUntil('window.pbi24Result !== null', 15);

        return $b->script('return window.pbi24Result')[0];
    }

    private function capture(Browser $b, string $name): void
    {
        $pause = getenv('DUSK_STEP_PAUSE');
        $b->pause($pause === false ? 2000 : max(0, (int) $pause))->screenshot('PBI24-'.$name);
    }
}
