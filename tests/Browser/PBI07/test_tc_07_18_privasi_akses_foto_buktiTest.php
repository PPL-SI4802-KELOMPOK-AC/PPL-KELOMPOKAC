<?php

namespace Tests\Browser\PBI07;

use App\Models\PengajuanPlts;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use Tests\DuskTestCase;

class test_tc_07_18_privasi_akses_foto_buktiTest extends DuskTestCase
{
    /**
     * ID TC: TC-07-18
     * Skenario: Mengakses foto privat milik pengguna lain.
     * Jenis: Exception.
     * Hasil yang diharapkan: Pemilik memperoleh URL sementara;
     * pengguna lain ditolak dan tidak memperoleh URL foto.
     */
    /**
     * ID: TC-07-18
     * Fokus pengujian: Privasi akses foto bukti.
     * Prasyarat dan data uji: Pengguna A memiliki foto; pengguna B mencoba mengakses ID foto yang sama.
     * Hasil yang diharapkan: A mendapat URL sementara; B mendapat 404 dan tidak menerima URL foto.
     * Jenis: Exception.    */
    public function test_tc_07_18_privasi_akses_foto_bukti(): void
    {
        sleep(3); // Jeda demo agar durasi test mudah diamati.
        $pemilik = new User;
        $pemilik->name = 'Pemilik Foto Uji PBI07 TC18';
        $pemilik->email = 'dusk-pbi07-'.Str::uuid().'@example.test';
        $pemilik->password = Hash::make('password-uji-tc-07-18');
        $pemilik->role = 'masyarakat';
        $pemilik->is_active = true;
        $pemilik->save();

        $penggunaLain = new User;
        $penggunaLain->name = 'Pengguna Lain Uji PBI07 TC18';
        $penggunaLain->email = 'dusk-pbi07-'.Str::uuid().'@example.test';
        $penggunaLain->password = Hash::make('password-uji-tc-07-18');
        $penggunaLain->role = 'masyarakat';
        $penggunaLain->is_active = true;
        $penggunaLain->save();

        $draft = new PengajuanPlts;
        $draft->user_id = $pemilik->id;
        $draft->nomor_tiket = 'DUSK-'.Str::upper(Str::random(16));
        $draft->ringkasan_pengajuan = 'Draft pemilik foto untuk pengujian TC-07-18';
        $draft->status_terkini = 'draft';
        $draft->status_diperbarui_pada = now();
        $draft->save();

        $temporaryUrl = 'https://r2.example.test/foto-private.png?temporary-token=tc-07-18';
        $photoPath = "bukti-pengajuan/{$draft->id}/foto-private.png";

        $foto = $draft->bukti()->create([
            'nama_file_asli' => 'foto-private.png',
            'path_file' => $photoPath,
            'mime_type' => 'image/png',
            'ukuran_byte' => 1024,
            'keterangan' => null,
        ]);

        $diskR2Palsu = Mockery::mock(FilesystemAdapter::class);
        $diskR2Palsu->shouldReceive('temporaryUrl')
            ->once()
            ->andReturn($temporaryUrl);

        Storage::shouldReceive('disk')
            ->once()
            ->with('r2')
            ->andReturn($diskR2Palsu);

        try {
            // Pemilik foto boleh mendapat redirect ke URL sementara.
            $responsePemilik = $this->actingAs($pemilik)
                ->get('/pengajuan/lokasi/bukti/'.$foto->id);

            $responsePemilik->assertRedirect($temporaryUrl)
                ->assertHeader('Cache-Control', 'no-store, private');

            // Pengguna lain tidak boleh mendapat foto atau URL sementara.
            $responsePenggunaLain = $this->actingAs($penggunaLain)
                ->get('/pengajuan/lokasi/bukti/'.$foto->id);

            $responsePenggunaLain->assertNotFound();

            $this->assertFalse($responsePenggunaLain->headers->has('Location'));
            $this->assertStringNotContainsString(
                $temporaryUrl,
                $responsePenggunaLain->getContent()
            );
        } finally {
            $draft->bukti()->delete();
            $draft->delete();
            $pemilik->delete();
            $penggunaLain->delete();
        }
    }
}