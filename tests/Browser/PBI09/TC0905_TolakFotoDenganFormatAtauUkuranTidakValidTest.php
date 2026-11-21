<?php

namespace Tests\Browser\PBI09;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TC0905_TolakFotoDenganFormatAtauUkuranTidakValidTest extends DuskTestCase
{
    use PBI09DuskSupport;

    public function test_foto_dengan_format_tidak_didukung_dan_ukuran_berlebih_ditolak(): void
    {
        $s = $this->makeDraft();
        $invalidFile = tempnam(sys_get_temp_dir(), 'pbi09-');
        $largeFile = tempnam(sys_get_temp_dir(), 'pbi09-');
        file_put_contents($invalidFile, 'bukan gambar');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p1sAAAAASUVORK5CYII=', true);
        file_put_contents($largeFile, $png.str_repeat('x', 5 * 1024 * 1024 + 1 - strlen($png)));
        try {
            $this->browse(function (Browser $browser) use ($s, $invalidFile, $largeFile) {
                $this->signIn($browser, $s['email'], $s['password'])
                    ->visit($this->appUrl('/pengajuan/lokasi/bukti'))
                    ->pause($this->demoPauseDuration())
                    ->attach('#photos', $invalidFile)
                    ->script("document.querySelector('[data-evidence-submit]').disabled=false;");
                $browser->click('[data-evidence-submit]')
                    ->waitForText('The foto bukti field must be a file of type: jpg, jpeg, png, webp.')
                    ->pause($this->demoPauseDuration())
                    ->assertPathIs('/pengajuan/lokasi/bukti')
                    ->attach('#photos', $largeFile)
                    ->script("document.querySelector('[data-evidence-submit]').disabled=false;");
                $browser->click('[data-evidence-submit]')
                    ->waitForText('Ukuran setiap foto maksimal 5 MB.')
                    ->pause($this->demoPauseDuration())
                    ->assertPathIs('/pengajuan/lokasi/bukti');
            });
            $this->assertSame(0, $s['draft']->bukti()->count());
        } finally {
            @unlink($invalidFile);
            @unlink($largeFile);
            $this->removeDraft($s);
        }
    }
}
