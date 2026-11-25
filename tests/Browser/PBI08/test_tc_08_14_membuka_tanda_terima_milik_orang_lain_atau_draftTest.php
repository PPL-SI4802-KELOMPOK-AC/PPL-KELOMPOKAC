<?php

namespace Tests\Browser\PBI08;

use App\Models\PengajuanPlts;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\DuskTestCase;

class test_tc_08_14_membuka_tanda_terima_milik_orang_lain_atau_draftTest extends DuskTestCase
{
    /**
     * ID TC: TC-08-14
     * Skenario: Membuka tanda terima milik orang lain atau draft yang belum terkirim.
     * Jenis: Exception.
     * Hasil yang diharapkan: Akses ditolak dan informasi pengajuan tidak ditampilkan.
     */
    public function test_tc_08_14_membuka_tanda_terima_milik_orang_lain_atau_draft(): void
    {
        sleep(3); // Jeda demo agar durasi test lebih mudah diamati.
        $pemilik = new User;
        $pemilik->name = 'Pemilik Pengajuan Uji PBI08 TC14';
        $pemilik->email = 'dusk-pbi08-'.Str::uuid().'@example.test';
        $pemilik->password = Hash::make('password-uji-tc-08-14');
        $pemilik->role = 'masyarakat';
        $pemilik->is_active = true;
        $pemilik->save();

        $penggunaLain = new User;
        $penggunaLain->name = 'Pengguna Lain Uji PBI08 TC14';
        $penggunaLain->email = 'dusk-pbi08-'.Str::uuid().'@example.test';
        $penggunaLain->password = Hash::make('password-uji-tc-08-14');
        $penggunaLain->role = 'masyarakat';
        $penggunaLain->is_active = true;
        $penggunaLain->save();

        $terkirim = new PengajuanPlts;
        $terkirim->user_id = $pemilik->id;
        $terkirim->nomor_tiket = 'PLTS-'.now()->format('Ymd').'-'.Str::ulid();
        $terkirim->ringkasan_pengajuan = 'Informasi privat pengajuan terkirim TC-08-14';
        $terkirim->status_terkini = 'diajukan';
        $terkirim->status_diperbarui_pada = now();
        $terkirim->save();

        $draft = new PengajuanPlts;
        $draft->user_id = $pemilik->id;
        $draft->nomor_tiket = 'DRAFT-DUSK-'.Str::upper(Str::random(12));
        $draft->ringkasan_pengajuan = 'Informasi privat draft belum terkirim TC-08-14';
        $draft->status_terkini = 'draft';
        $draft->status_diperbarui_pada = now();
        $draft->save();

        try {
            // Pengguna lain tidak boleh membuka tanda terima milik pemilik.
            $responsePenggunaLain = $this->actingAs($penggunaLain)
                ->get('/pengajuan/lokasi/terkirim/'.$terkirim->id);

            $responsePenggunaLain->assertNotFound()
                ->assertDontSee($terkirim->nomor_tiket)
                ->assertDontSee($terkirim->ringkasan_pengajuan);

            // Pemilik juga tidak boleh membuka tanda terima untuk draft.
            $responseDraft = $this->actingAs($pemilik)
                ->get('/pengajuan/lokasi/terkirim/'.$draft->id);

            $responseDraft->assertNotFound()
                ->assertDontSee($draft->nomor_tiket)
                ->assertDontSee($draft->ringkasan_pengajuan);

            $this->assertSame('diajukan', $terkirim->fresh()->status_terkini);
            $this->assertSame('draft', $draft->fresh()->status_terkini);
        } finally {
            $terkirim->bukti()->delete();
            $terkirim->lokasi()->delete();
            $terkirim->delete();

            $draft->bukti()->delete();
            $draft->lokasi()->delete();
            $draft->delete();

            $pemilik->delete();
            $penggunaLain->delete();
        }
    }
}