<?php

namespace Tests\Browser\PBI08;

use App\Models\PengajuanPlts;
use App\Models\User;
use App\Services\Pengajuan\LocationCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class test_tc_08_07_mengirim_pengajuan_dengan_data_lengkapTest extends DuskTestCase
{
    /**
     * ID TC: TC-08-07
     * Skenario: Mengirim pengajuan dengan data lengkap.
     * Jenis: Normal.
     * Hasil yang diharapkan: Draft berubah menjadi diajukan,
     * nomor PLTS diterbitkan, riwayat awal tercatat, dan tanda terima tampil.
     */
    public function test_tc_08_07_mengirim_pengajuan_dengan_data_lengkap(): void
    {
        sleep(3); // Jeda demo agar durasi test lebih mudah diamati.
        $email = 'dusk-pbi08-'.Str::uuid().'@example.test';
        $password = 'password-uji-tc-08-07';

        $address = app(LocationCatalog::class)->address([
            'kabupaten' => '3205',
            'kecamatan' => 'Cikelet',
            'desa' => '32.05.30.2010',
            'alamat' => 'Kampung Uji TC07, RT 01',
        ]);

        $user = new User;
        $user->name = 'Pengguna Uji PBI08 TC07';
        $user->email = $email;
        $user->password = Hash::make($password);
        $user->role = 'masyarakat';
        $user->is_active = true;
        $user->save();

        $draft = new PengajuanPlts;
        $draft->user_id = $user->id;
        $draft->nomor_tiket = 'DRAFT-DUSK-'.Str::upper(Str::random(12));
        $draft->ringkasan_pengajuan = 'Draft lengkap untuk pengujian TC-08-07';
        $draft->status_terkini = 'draft';
        $draft->status_diperbarui_pada = now();
        $draft->save();

        $nomorDraft = $draft->nomor_tiket;

        $draft->lokasi()->create([
            'alamat_deskriptif' => $address,
            'latitude' => null,
            'longitude' => null,
        ]);

        try {
            $draft->bukti()->create([
                'nama_file_asli' => 'tc-08-07-foto.png',
                'path_file' => "bukti-pengajuan/{$draft->id}/tc-08-07-foto.png",
                'mime_type' => 'image/png',
                'ukuran_byte' => 1024,
                'keterangan' => null,
            ]);

            $this->browse(function (Browser $browser) use (
                $email,
                $password,
                $draft
            ) {
                $browser->visit('http://127.0.0.1:8000/login')
                    ->type('email', $email)
                    ->type('password', $password)
                    ->press('Masuk')
                    ->visit('http://127.0.0.1:8000/pengajuan/lokasi/ringkasan')
                    ->assertPathIs('/pengajuan/lokasi/ringkasan')
                    ->assertSee('Draft tersimpan')
                    ->check('#pernyataan')
                    ->press('Kirim pengajuan')
                    ->waitForText('Pengajuan berhasil dikirim')
                    ->assertPathIs('/pengajuan/lokasi/terkirim/'.$draft->id)
                    ->assertSee('Status pengajuan:')
                    ->assertSee('Diajukan');

                $nomorFinal = $draft->fresh()->nomor_tiket;

                $browser->assertSeeIn('.submission-ticket', $nomorFinal);
            });

            $pengajuanTerkirim = $draft->fresh();

            $this->assertSame('diajukan', $pengajuanTerkirim->status_terkini);
            $this->assertStringStartsWith('PLTS-', $pengajuanTerkirim->nomor_tiket);
            $this->assertNotSame($nomorDraft, $pengajuanTerkirim->nomor_tiket);

            $riwayat = DB::table('riwayat_status_pengajuan')
                ->where('pengajuan_id', $draft->id)
                ->where('jenis_tindakan', 'pengajuan_baru')
                ->get();

            $this->assertCount(1, $riwayat);
            $this->assertSame('diajukan', $riwayat->first()->status_baru);
            $this->assertNull($riwayat->first()->status_sebelumnya);
            $this->assertSame($user->id, (int) $riwayat->first()->diubah_oleh);
        } finally {
            DB::table('riwayat_status_pengajuan')
                ->where('pengajuan_id', $draft->id)
                ->delete();

            $draft->bukti()->delete();
            $draft->lokasi()->delete();
            $draft->delete();
            $user->delete();
        }
    }
}