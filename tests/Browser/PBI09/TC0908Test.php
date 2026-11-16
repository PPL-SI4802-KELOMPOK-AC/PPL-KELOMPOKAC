<?php

namespace Tests\Browser\PBI09;

use Laravel\Dusk\Browser;

class TC0908Test extends PBI09TestCase
{
    public function test_tc09_08_hanya_satu_koordinat_ditolak(): void
    {
        $this->draft->lokasi()->update(['latitude' => -7.5, 'longitude' => null]);

        $this->browse(function (Browser $browser) {
            $this->signIn($browser);

            $browser->visit('/pengajuan/lokasi/ringkasan');

            $this->assertStringEndsWith('/pengajuan/lokasi', $browser->driver->getCurrentURL());
            $formState = $browser->driver->executeScript(
                "return { text: document.body.innerText, latitude: document.querySelector('#latitude').value, longitude: document.querySelector('#longitude').value };"
            );
            $this->assertStringContainsString('Data lokasi belum lengkap.', $formState['text']);
            $this->assertSame('-7.5', $formState['latitude']);
            $this->assertSame('', $formState['longitude']);
        });

        $this->assertSame('draft', $this->draft->fresh()->status_terkini);
    }
}
