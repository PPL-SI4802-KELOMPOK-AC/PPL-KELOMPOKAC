<?php

namespace Tests\Browser\PBI09;

use Laravel\Dusk\Browser;

class TC0909Test extends PBI09TestCase
{
    public function test_tc09_09_data_diperbaiki_dan_pengajuan_berhasil_dikirim_ulang(): void
    {
        $this->draft->ringkasan_pengajuan = '';
        $this->draft->save();
        $this->draft->lokasi()->update(['alamat_deskriptif' => 'Alamat tanpa data wilayah']);
        $ticket = $this->draft->nomor_tiket;

        $this->browse(function (Browser $browser) {
            $this->signIn($browser);
            $browser->waitUntil("document.querySelector('#kabupaten') !== null && !document.querySelector('#kabupaten').disabled && document.querySelector('#kabupaten').options.length > 1");

            $browser->script("const field = document.querySelector('#kabupaten'); field.value = '3205'; field.dispatchEvent(new Event('change', { bubbles: true }));");
            $browser->waitUntil('document.querySelector("#kecamatan").options.length > 1')
                ->waitUntil('document.querySelector("#kecamatan").value === ""');
            $browser->script("const field = document.querySelector('#kecamatan'); field.value = 'Cikelet'; field.dispatchEvent(new Event('change', { bubbles: true }));");
            $browser->waitUntil('document.querySelector("#desa").options.length > 1')
                ->waitUntil('document.querySelector("#desa").value === ""');
            $browser->script("const field = document.querySelector('#desa'); field.value = '32.05.30.2010'; field.dispatchEvent(new Event('change', { bubbles: true }));");
            $browser->type('alamat', 'Kampung Uji, RT 01')
                ->press('Simpan draft')
                ->assertSee('Draft lokasi berhasil disimpan.')
                ->visit('/pengajuan/lokasi/bukti')
                ->clickLink('Lanjut ke ringkasan')
                ->assertSee('Data lokasi dan 1 foto bukti sudah tersimpan.')
                ->check('pernyataan')
                ->press('Kirim pengajuan')
                ->assertPathIs('/pengajuan/lokasi/terkirim/'.$this->draft->id)
                ->assertSee('Pengajuan berhasil dikirim');
        });

        $submitted = $this->draft->fresh();
        $this->assertSame('diajukan', $submitted->status_terkini);
        $this->assertNotSame($ticket, $submitted->nomor_tiket);
    }
}
