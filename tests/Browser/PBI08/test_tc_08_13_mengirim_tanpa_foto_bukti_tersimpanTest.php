<?php

namespace Tests\Browser\PBI08;

use App\Models\PengajuanPlts;
use App\Models\User;
use App\Services\Pengajuan\LocationCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\DuskTestCase;

class test_tc_08_13_mengirim_tanpa_foto_bukti_tersimpanTest extends DuskTestCase
{
    /**
     * ID TC: TC-08-13
     * Skenario: Mengirim tanpa foto bukti tersimpan.
     * Jenis: Exception.
     * Hasil yang diharapkan: Request ditolak; status dan nomor draft tetap,
     * serta riwayat tidak dibuat.
     */
    public function test_tc_08_13_mengirim_tanpa_foto_bukti_tersimpan(): void
    {
        sleep(3); // Jeda demo agar durasi test lebih mudah diamati.
        $address = app(LocationCatalog::class)->address([
            'kabupaten' => '3205',
            'kecamatan' => 'Cikelet',
            'desa' => '32.05.30.2010',
            'alamat' => 'Kampung Uji TC13, RT 01',
        ]);

        $user = new User;
        $user->name = 'Pengguna Uji PBI08 TC13';
        $user->email = 'dusk-pbi08-'.Str::uuid().'@example.test';
        $user->password = Hash::make('password-uji-tc-08-13');
        $user->role = 'masyarakat';
        $user->is_active = true;
        $user->save();

        $draft = new PengajuanPlts;
        $draft->user_id = $user->id;
        $draft->nomor_tiket = 'DRAFT-DUSK-'.Str::upper(Str::random(12));
        $draft->ringkasan_pengajuan = 'Draft tanpa foto untuk pengujian TC-08-13';
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
            $csrfToken = Str::random(40);

            $response = $this->from('/pengajuan/lokasi/ringkasan')
                ->actingAs($user)
                ->withSession(['_token' => $csrfToken])
                ->post('/pengajuan/lokasi/kirim', [
                    '_token' => $csrfToken,
                    'draft_id' => $draft->id,
                    'pernyataan' => '1',
                ]);

            $response->assertRedirect('/pengajuan/lokasi/ringkasan')
                ->assertSessionHasErrors([
                    'photos' => 'Bukti wajib belum lengkap atau format/ukuran foto tidak sesuai. Unggah 1-5 foto JPG, PNG, atau WebP, maksimal 5 MB per foto.',
                ]);

            $this->assertSame('draft', $draft->fresh()->status_terkini);
            $this->assertSame($nomorDraft, $draft->fresh()->nomor_tiket);
            $this->assertSame(0, $draft->bukti()->count());
            $this->assertSame(
                0,
                DB::table('riwayat_status_pengajuan')
                    ->where('pengajuan_id', $draft->id)
                    ->count()
            );
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