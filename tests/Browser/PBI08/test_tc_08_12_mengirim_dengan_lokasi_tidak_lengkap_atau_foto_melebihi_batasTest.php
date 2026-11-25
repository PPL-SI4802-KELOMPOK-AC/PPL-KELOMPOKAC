<?php

namespace Tests\Browser\PBI08;

use App\Models\PengajuanPlts;
use App\Models\User;
use App\Services\Pengajuan\LocationCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\DuskTestCase;

class test_tc_08_12_mengirim_dengan_lokasi_tidak_lengkap_atau_foto_melebihi_batasTest extends DuskTestCase
{
    /**
     * ID TC: TC-08-12
     * Skenario: Mengirim dengan lokasi tidak lengkap atau jumlah foto melebihi batas.
     * Jenis: Exception.
     * Hasil yang diharapkan: Request ditolak; status dan nomor draft tetap,
     * serta riwayat tidak dibuat.
     */
    public function test_tc_08_12_mengirim_dengan_lokasi_tidak_lengkap_atau_foto_melebihi_batas(): void
    {
        sleep(3); // Jeda demo agar durasi test lebih mudah diamati.
        $email = 'dusk-pbi08-'.Str::uuid().'@example.test';

        $user = new User;
        $user->name = 'Pengguna Uji PBI08 TC12';
        $user->email = $email;
        $user->password = Hash::make('password-uji-tc-08-12');
        $user->role = 'masyarakat';
        $user->is_active = true;
        $user->save();

        $draft = new PengajuanPlts;
        $draft->user_id = $user->id;
        $draft->nomor_tiket = 'DRAFT-DUSK-'.Str::upper(Str::random(12));
        $draft->ringkasan_pengajuan = 'Draft pengujian TC-08-12';
        $draft->status_terkini = 'draft';
        $draft->status_diperbarui_pada = now();
        $draft->save();

        $nomorDraft = $draft->nomor_tiket;

        try {
            // Siapkan satu foto valid, tetapi lokasi belum tersimpan.
            $draft->bukti()->create([
                'nama_file_asli' => 'tc-08-12-foto-1.png',
                'path_file' => "bukti-pengajuan/{$draft->id}/tc-08-12-foto-1.png",
                'mime_type' => 'image/png',
                'ukuran_byte' => 1024,
                'keterangan' => null,
            ]);

            $csrfTokenLokasi = Str::random(40);

            $responseLokasi = $this->from('/pengajuan/lokasi/ringkasan')
                ->actingAs($user)
                ->withSession(['_token' => $csrfTokenLokasi])
                ->post('/pengajuan/lokasi/kirim', [
                    '_token' => $csrfTokenLokasi,
                    'draft_id' => $draft->id,
                    'pernyataan' => '1',
                ]);

            $responseLokasi->assertRedirect('/pengajuan/lokasi/ringkasan')
                ->assertSessionHasErrors([
                    'lokasi' => 'Data lokasi belum lengkap. Lengkapi wilayah, alamat, dan pasangan koordinat jika diisi.',
                ]);

            $this->assertSame('draft', $draft->fresh()->status_terkini);
            $this->assertSame($nomorDraft, $draft->fresh()->nomor_tiket);

            // Lengkapi lokasi, lalu siapkan total enam foto pada draft yang sama.
            $address = app(LocationCatalog::class)->address([
                'kabupaten' => '3205',
                'kecamatan' => 'Cikelet',
                'desa' => '32.05.30.2010',
                'alamat' => 'Kampung Uji TC12, RT 01',
            ]);

            $draft->lokasi()->create([
                'alamat_deskriptif' => $address,
                'latitude' => null,
                'longitude' => null,
            ]);

            for ($number = 2; $number <= 6; $number++) {
                $draft->bukti()->create([
                    'nama_file_asli' => "tc-08-12-foto-{$number}.png",
                    'path_file' => "bukti-pengajuan/{$draft->id}/tc-08-12-foto-{$number}.png",
                    'mime_type' => 'image/png',
                    'ukuran_byte' => 1024,
                    'keterangan' => null,
                ]);
            }

            $csrfTokenFoto = Str::random(40);

            $responseFoto = $this->from('/pengajuan/lokasi/ringkasan')
                ->actingAs($user)
                ->withSession(['_token' => $csrfTokenFoto])
                ->post('/pengajuan/lokasi/kirim', [
                    '_token' => $csrfTokenFoto,
                    'draft_id' => $draft->id,
                    'pernyataan' => '1',
                ]);

            $responseFoto->assertRedirect('/pengajuan/lokasi/ringkasan')
                ->assertSessionHasErrors([
                    'photos' => 'Bukti wajib belum lengkap atau format/ukuran foto tidak sesuai. Unggah 1-5 foto JPG, PNG, atau WebP, maksimal 5 MB per foto.',
                ]);

            $this->assertSame('draft', $draft->fresh()->status_terkini);
            $this->assertSame($nomorDraft, $draft->fresh()->nomor_tiket);
            $this->assertSame(6, $draft->bukti()->count());
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