<?php

namespace Tests\Browser\PBI01;

use Facebook\WebDriver\Chrome\ChromeDevToolsDriver;
use Illuminate\Support\Facades\DB;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\BeforeClass;
use Tests\DuskTestCase;
use Tests\Support\PBI01\Pbi01Database;

class PBI01Test extends DuskTestCase
{
    private array $fixture;

    #[BeforeClass]
    public static function prepare(): void
    {
        if (getenv('PBI01_DUSK_TESTS') === '1') {
            static::startChromeDriver(['--port='.(getenv('PBI01_DUSK_DRIVER_PORT') ?: '9515')]);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('PBI01_DUSK_TESTS') !== '1') {
            $this->markTestSkipped('Jalankan php tests/Support/PBI01/run-pbi01-dusk.php untuk database uji terisolasi.');
        }
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->fixture = Pbi01Database::rebuild();
    }

    /** TC-17-01 | Normal: tambah desa dengan koordinat dan KK yang valid. */
    public function test_tc_17_01_tambah_wilayah(): void
    {
        $this->browse(function (Browser $browser) {
            $this->openManager($browser);
            $this->fillForm($browser);
            $browser->click('[data-save]')->waitForText('Wilayah berhasil ditambahkan.')
                ->waitForTextIn('[data-rows]', 'Desa Harapan Uji');
            $this->assertDatabaseHas('wilayah', ['nama_wilayah' => 'Desa Harapan Uji',
                'jumlah_kk_terdampak' => 25, 'created_by' => $this->fixture['petugas']->id]);
            $browser->refresh()->waitForTextIn('[data-rows]', 'Desa Harapan Uji');
            $this->capture($browser, 'TC-17-01-tambah-berhasil');
        });
    }

    /** TC-17-02 | Normal: edit KK Mekarjaya; riwayat masyarakat tetap utuh. */
    public function test_tc_17_02_edit_wilayah(): void
    {
        $history = Pbi01Database::history();
        $this->browse(function (Browser $browser) {
            $this->openManager($browser);
            $browser->click('button[aria-label="Edit Mekarjaya"]')
                ->waitUntil('document.querySelector("#nama_wilayah").value === "Mekarjaya" && !document.querySelector("#jumlah_kk_terdampak").disabled')
                ->type('jumlah_kk_terdampak', '42')->click('[data-save]')
                ->waitForText('Perubahan wilayah berhasil disimpan.')
                ->waitForTextIn($this->row('mekarjaya'), '42');
            $browser->refresh()->waitForTextIn($this->row('mekarjaya'), '42');
            $this->capture($browser, 'TC-17-02-edit-kk-berhasil');
        });
        $this->assertDatabaseHas('wilayah', ['id' => $this->fixture['mekarjaya']->id, 'jumlah_kk_terdampak' => 42,
            'created_by' => $this->fixture['petugas']->id]);
        $this->assertSame($history, Pbi01Database::history());
    }

    /** TC-17-03 | Normal: setujui dialog hapus untuk desa tanpa relasi. */
    public function test_tc_17_03_hapus_wilayah(): void
    {
        $history = Pbi01Database::history();
        $this->browse(function (Browser $browser) {
            $this->openManager($browser);
            $browser->click('button[aria-label="Hapus Desa Uji Hapus"]')->waitForDialog()
                ->assertDialogOpened('Hapus wilayah “Desa Uji Hapus”? Tindakan ini tidak dapat dibatalkan.')
                ->acceptDialog()->waitForText('Wilayah berhasil dihapus.')
                ->waitUntilMissing($this->row('hapus'))->assertDontSeeIn('[data-rows]', 'Desa Uji Hapus');
            $browser->refresh()->waitForTextIn('[data-rows]', 'Mekarjaya')->assertMissing($this->row('hapus'));
            $this->capture($browser, 'TC-17-03-hapus-berhasil');
        });
        $this->assertDatabaseMissing('wilayah', ['id' => $this->fixture['hapus']->id]);
        $this->assertSame($history, Pbi01Database::history());
    }

    /** TC-17-04 | Pengecualian: nama desa/alamat yang memuat kecamatan kosong. */
    public function test_tc_17_04_kolom_wajib_kosong(): void
    {
        $this->browse(function (Browser $browser) {
            $this->openManager($browser);
            $this->fillForm($browser);
            $browser->clear('nama_wilayah')->clear('alamat_deskriptif')->click('[data-save]')
                ->waitForTextIn('#error-nama_wilayah', 'Nama wilayah wajib diisi.')
                ->assertSeeIn('#error-alamat_deskriptif', 'Alamat lengkap wajib diisi.')
                ->assertAttribute('#nama_wilayah', 'aria-invalid', 'true');
            $this->capture($browser, 'TC-17-04-validasi-wajib');
            $browser->click('[data-cancel]')->waitForDialog()->acceptDialog();
        });
        $this->assertDatabaseCount('wilayah', 2);
    }

    /** TC-17-05 | Pengecualian: lintang >90 dan bujur >180 ditolak. */
    public function test_tc_17_05_koordinat_tidak_valid(): void
    {
        $this->browse(function (Browser $browser) {
            $this->openManager($browser);
            $this->fillForm($browser);
            $browser->type('latitude', '91')->type('longitude', '181')->click('[data-save]')
                ->waitForTextIn('#error-latitude', 'Masukkan angka antara -90 dan 90')
                ->assertSeeIn('#error-longitude', 'Masukkan angka antara -180 dan 180')
                ->assertAttribute('#longitude', 'aria-invalid', 'true');
            $this->capture($browser, 'TC-17-05-validasi-koordinat');
            $browser->click('[data-cancel]')->waitForDialog()->acceptDialog();
        });
        $this->assertDatabaseCount('wilayah', 2);
    }

    /** TC-17-06 | Pengecualian: batalkan dialog native, data tetap ada. */
    public function test_tc_17_06_batal_hapus(): void
    {
        $history = Pbi01Database::history();
        $this->browse(function (Browser $browser) {
            $this->openManager($browser);
            $browser->click('button[aria-label="Hapus Desa Uji Hapus"]')->waitForDialog()
                ->assertDialogOpened('Hapus wilayah “Desa Uji Hapus”? Tindakan ini tidak dapat dibatalkan.')
                ->dismissDialog()->assertSeeIn($this->row('hapus'), 'Desa Uji Hapus');
            $browser->refresh()->waitForTextIn($this->row('hapus'), 'Desa Uji Hapus');
            $this->capture($browser, 'TC-17-06-batal-hapus-data-utuh');
        });
        $this->assertDatabaseHas('wilayah', ['id' => $this->fixture['hapus']->id]);
        $this->assertSame($history, Pbi01Database::history());
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
            ->assertSee('Kelola data wilayah')->assertSee('Petugas ESDM Uji');
    }

    private function fillForm(Browser $browser): void
    {
        foreach (Pbi01Database::payload() as $name => $value) {
            if ($name === 'kondisi_listrik') {
                $browser->select($name, $value);
            } else {
                $browser->type($name, (string) $value);
            }
        }
    }

    private function row(string $fixture): string
    {
        return '[data-rows] tr[data-id="'.$this->fixture[$fixture]->id.'"]';
    }

    private function capture(Browser $browser, string $name): void
    {
        $browser->script('window.scrollTo(0, 0);');
        $browser->pause((int) (getenv('PBI01_DEMO_PAUSE') ?: 750))
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
}
