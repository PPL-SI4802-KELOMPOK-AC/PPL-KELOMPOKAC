<?php

namespace Tests\Browser\PBI11;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class PBI11Test extends DuskTestCase
{
    /**
     * ID TC: TC-PBI11-01
     * Skenario: Ganti dengan skenario sesuai PBI.
     * Kondisi awal: Jelaskan akun, hak akses, dan data yang dibutuhkan.
     * Langkah: Buka halaman, lakukan tindakan, lalu periksa hasilnya.
     * Hasil yang diharapkan: Ganti dengan hasil sesuai kriteria penerimaan.
     *
     * Gunakan fixture khusus jika membutuhkan data dan bersihkan setelah tes.
     */
    public function test_tc_01_contoh_skenario(): void
    {
        // Hapus baris skip setelah skenario, langkah, dan assertion disesuaikan.
        $this->markTestSkipped('Template PBI11: sesuaikan dengan kriteria penerimaan sebelum dijalankan.');

        $this->browse(function (Browser $browser) {
            // Contoh dasar; ganti URL dan assertion sesuai skenario PBI.
            $browser->visit('/')
                ->assertSee('Kenali kebutuhan listrik desa.');
        });
    }
}
