<?php

namespace Tests\Browser\PBI09;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TC0906_TolakJumlahFotoMelebihiBatasTest extends DuskTestCase
{
    use PBI09DuskSupport;

    public function test_lebih_dari_lima_foto_ditolak(): void
    {
        $s = $this->makeDraft();
        $file = tempnam(sys_get_temp_dir(), 'pbi09-');
        file_put_contents($file, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p1sAAAAASUVORK5CYII='));
        try {
            $this->browse(function (Browser $browser) use ($s, $file) {
                $this->signIn($browser, $s['email'], $s['password'])
                    ->visit($this->appUrl('/pengajuan/lokasi/bukti'))
                    ->attach('#photos', implode("\n", array_fill(0, 6, $file)))
                    ->script("document.querySelector('[data-evidence-submit]').disabled=false;");
                $browser->click('[data-evidence-submit]')->waitForText('Maksimal lima foto dapat diunggah dalam satu kali pilihan.')
                    ->assertPathIs('/pengajuan/lokasi/bukti');
            });
            $this->assertSame(0, $s['draft']->bukti()->count());
        } finally {
            @unlink($file);
            $this->removeDraft($s);
        }
    }
}
