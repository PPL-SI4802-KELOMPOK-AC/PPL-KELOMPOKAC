<?php

namespace Tests\Browser\PBI09;

use App\Models\PengajuanPlts;
use App\Models\User;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

abstract class PBI09TestCase extends DuskTestCase
{
    protected const EMAIL = 'pbi09-dusk@example.test';

    protected const PASSWORD = 'pbi09-browser-test';

    protected User $owner;

    protected PengajuanPlts $draft;

    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('PBI09_DUSK_TESTS') !== '1') {
            $this->markTestSkipped('Aktifkan PBI09_DUSK_TESTS=1 dengan server dan database SQLite uji terisolasi.');
        }

        $this->assertSame('testing', app()->environment(), 'Tes PBI-09 hanya boleh berjalan di APP_ENV=testing.');
        $this->assertSame('sqlite', config('database.default'), 'Tes PBI-09 hanya boleh memakai SQLite.');
        $this->assertSame(
            'http://127.0.0.1:8019',
            rtrim((string) config('app.url'), '/'),
            'Tes PBI-09 hanya boleh membuka server uji lokal pada port 8019.'
        );
        $this->assertFileDoesNotExist(base_path('bootstrap/cache/config.php'));

        $databasePath = (string) config('database.connections.sqlite.database');
        $testingDirectory = realpath(storage_path('framework/testing'));
        $this->assertNotFalse($testingDirectory);
        $this->assertSame($testingDirectory, realpath(dirname($databasePath)));
        $this->assertMatchesRegularExpression('/^pbi09-dusk-[a-f0-9-]+\.sqlite$/i', basename($databasePath));
        if (! is_file($databasePath)) {
            $handle = fopen($databasePath, 'x');
            $this->assertNotFalse($handle);
            fclose($handle);
        }

        config(['database.connections.sqlite.url' => null]);
        DB::purge('sqlite');
        $this->assertSame('sqlite', DB::connection()->getDriverName());

        Schema::disableForeignKeyConstraints();
        foreach (['riwayat_status_pengajuan', 'bukti_pengajuan', 'lokasi_pengajuan', 'pengajuan_plts', 'users'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role');
            $table->timestamps();
        });

        Schema::create('pengajuan_plts', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_tiket', 50)->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->unsignedBigInteger('wilayah_id')->nullable();
            $table->string('ringkasan_pengajuan');
            $table->enum('status_terkini', ['draft', 'diajukan', 'diperiksa', 'diverifikasi', 'diproses', 'selesai', 'ditolak']);
            $table->timestamp('status_diperbarui_pada')->useCurrent();
        });

        Schema::create('lokasi_pengajuan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->unique()->constrained('pengajuan_plts');
            $table->text('alamat_deskriptif');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 11, 7)->nullable();
        });

        Schema::create('bukti_pengajuan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->constrained('pengajuan_plts');
            $table->string('nama_file_asli', 255);
            $table->string('path_file', 512)->unique();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('ukuran_byte');
            $table->string('keterangan', 255)->nullable();
        });

        Schema::create('riwayat_status_pengajuan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->constrained('pengajuan_plts');
            $table->foreignId('diubah_oleh')->constrained('users');
            $table->enum('status_sebelumnya', ['diajukan', 'diperiksa', 'diverifikasi', 'diproses', 'selesai', 'ditolak'])->nullable();
            $table->enum('status_baru', ['diajukan', 'diperiksa', 'diverifikasi', 'diproses', 'selesai', 'ditolak']);
            $table->string('jenis_tindakan', 50);
            $table->string('hasil_verifikasi', 50)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        $this->owner = new User([
            'name' => 'Pemohon Dusk PBI-09',
            'email' => self::EMAIL,
            'password' => self::PASSWORD,
        ]);
        $this->owner->role = 'masyarakat';
        $this->owner->save();

        $this->draft = new PengajuanPlts;
        $this->draft->user_id = $this->owner->id;
        $this->draft->nomor_tiket = 'DRAFT-PBI09-'.str()->uuid();
        $this->draft->ringkasan_pengajuan = 'Lokasi Awassagara, Kabupaten Garut';
        $this->draft->status_terkini = 'draft';
        $this->draft->status_diperbarui_pada = now();
        $this->draft->save();
        $this->draft->lokasi()->create([
            'alamat_deskriptif' => 'Kampung Uji, Awassagara, Cikelet, Kabupaten Garut, Jawa Barat',
        ]);

        $this->addEvidence();
    }

    protected function driver(): RemoteWebDriver
    {
        $options = (new ChromeOptions)->addArguments([
            '--disable-gpu',
            '--headless=new',
            '--window-size=1920,1080',
            '--disable-smooth-scrolling',
            '--blink-settings=imagesEnabled=false',
        ]);

        return RemoteWebDriver::create(
            $_ENV['DUSK_DRIVER_URL'] ?? env('DUSK_DRIVER_URL') ?? 'http://localhost:9515',
            DesiredCapabilities::chrome()->setCapability(ChromeOptions::CAPABILITY, $options)
        );
    }

    protected function signIn(Browser $browser): Browser
    {
        return $browser->visit('/pengajuan/lokasi')
            ->assertPathIs('/login')
            ->type('email', self::EMAIL)
            ->type('password', self::PASSWORD)
            ->press('Masuk')
            ->waitForText('Pemohon Dusk PBI-09')
            ->pause(1000);
    }

    protected function addEvidence(): void
    {
        $this->draft->bukti()->create([
            'nama_file_asli' => 'bukti-pbi09.png',
            'path_file' => 'pbi09-dusk-fixture/bukti-pbi09.png',
            'mime_type' => 'image/png',
            'ukuran_byte' => 1024,
        ]);
    }

    protected function clearEvidence(): void
    {
        $this->draft->bukti()->delete();
    }

    protected function selectBrowserFiles(Browser $browser, string $filesScript): void
    {
        $browser->script(
            "const input = document.querySelector('#photos');"
            .'const files = new DataTransfer();'
            .$filesScript
            .'input.files = files.files;'
            ."input.dispatchEvent(new Event('change', { bubbles: true }));"
        );
    }
}
