<?php

namespace Tests\Browser\PBI08;

use App\Models\PengajuanPlts;
use App\Models\User;
use App\Services\Pengajuan\LocationCatalog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class test_tc_08_02_memperbaiki_data_sebelum_mengirimTest extends DuskTestCase
{
    /**
     * ID TC: TC-08-02
     * Skenario: Memperbaiki data sebelum mengirim.
     * Jenis: Normal.
     * Langkah: Dari ringkasan, kembali ke lokasi, ubah alamat,
     * simpan, lalu buka ringkasan lagi.
     * Hasil yang diharapkan: Ringkasan menampilkan data terbaru
     * dari draft yang sama dan status tetap draft.
     */
    public function test_tc_08_02_memperbaiki_data_sebelum_mengirim(): void
    {
        $email = 'dusk-pbi08-'.Str::uuid().'@example.test';
        $password = 'password-uji-tc-08-02';
        $userName = 'Pengguna Uji PBI08 TC02';

        $catalog = app(LocationCatalog::class);

        $alamatAwal = 'Kampung Lama, RT 01';
        $alamatBaru = 'Kampung Baru, RT 02';

        $alamatTersimpanAwal = $catalog->address([
            'kabupaten' => '3205',
            'kecamatan' => 'Cikelet',
            'desa' => '32.05.30.2010',
            'alamat' => $alamatAwal,
        ]);

        $alamatTersimpanBaru = $catalog->address([
            'kabupaten' => '3205',
            'kecamatan' => 'Cikelet',
            'desa' => '32.05.30.2010',
            'alamat' => $alamatBaru,
        ]);

        $user = new User;
        $user->name = $userName;
        $user->email = $email;
        $user->password = Hash::make($password);
        $user->role = 'masyarakat';
        $user->is_active = true;
        $user->save();

        $draft = new PengajuanPlts;
        $draft->user_id = $user->id;
        $draft->nomor_tiket = 'DRAFT-DUSK-'.Str::upper(Str::random(12));
        $draft->ringkasan_pengajuan = 'Ringkasan draft PBI08 TC02';
        $draft->status_terkini = 'draft';
        $draft->status_diperbarui_pada = now();
        $draft->save();

        $draft->lokasi()->create([
            'alamat_deskriptif' => $alamatTersimpanAwal,
            'latitude' => null,
            'longitude' => null,
        ]);

        try {
            $draft->bukti()->create([
                'nama_file_asli' => 'tc-08-02-foto.png',
                'path_file' => "bukti-pengajuan/{$draft->id}/tc-08-02-foto.png",
                'mime_type' => 'image/png',
                'ukuran_byte' => 1024,
                'keterangan' => null,
            ]);

            $this->browse(function (Browser $browser) use (
                $email,
                $password,
                $alamatAwal,
                $alamatBaru,
                $draft
            ) {
                $browser->visit('http://127.0.0.1:8000/login')
                    ->type('email', $email)
                    ->type('password', $password)
                    ->press('Masuk')
                    ->visit('http://127.0.0.1:8000/pengajuan/lokasi/ringkasan')
                    ->assertPathIs('/pengajuan/lokasi/ringkasan')
                    ->assertSee($alamatAwal)
                    ->assertSee('Draft tersimpan')
                    ->clickLink('Ubah data lokasi')
                    ->assertPathIs('/pengajuan/lokasi')
                    ->assertInputValue('input[name="draft_id"]', (string) $draft->id)
                    ->assertInputValue('#alamat', $alamatAwal)
                    ->clear('#alamat')
                    ->type('#alamat', $alamatBaru)
                    ->click('button[name="action"][value="save"]')
                    ->waitForText('Draft lokasi berhasil disimpan.')
                    ->assertPathIs('/pengajuan/lokasi')
                    ->assertInputValue('#alamat', $alamatBaru)
                    ->visit('http://127.0.0.1:8000/pengajuan/lokasi/ringkasan')
                    ->assertPathIs('/pengajuan/lokasi/ringkasan')
                    ->assertSee($alamatBaru)
                    ->assertDontSee($alamatAwal)
                    ->assertSeeIn('.review-evidence', 'tc-08-02-foto.png')
                    ->assertSee('Draft tersimpan');
            });

            $draft->refresh();

            $this->assertSame('draft', $draft->status_terkini);
            $this->assertStringStartsWith('DRAFT-', $draft->nomor_tiket);
            $this->assertSame($alamatTersimpanBaru, $draft->lokasi()->first()->alamat_deskriptif);
            $this->assertSame(
                1,
                PengajuanPlts::where('user_id', $user->id)
                    ->where('status_terkini', 'draft')
                    ->count()
            );
            $this->assertSame(1, $draft->bukti()->count());
        } finally {
            $draft->bukti()->delete();
            $draft->lokasi()->delete();
            $draft->delete();
            $user->delete();
        }
    }
}