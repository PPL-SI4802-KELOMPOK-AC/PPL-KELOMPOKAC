<?php

namespace Tests\Browser\PBI07;

use App\Models\PengajuanPlts;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\DuskTestCase;

class test_tc_07_15_pembatasan_upload_berdasarkan_status_pengajuanTest extends DuskTestCase
{
    /**
     * ID TC: TC-07-15
     * Skenario: Mengunggah foto ke pengajuan yang bukan draft.
     * Jenis: Exception.
     * Input: Pemilik mencoba upload ke pengajuan berstatus diajukan.
     * Hasil yang diharapkan: Upload ditolak; foto dan metadata tidak ditambahkan.
     */
    /**
     * ID: TC-07-15
     * Fokus pengujian: Pembatasan upload berdasarkan status pengajuan.
     * Prasyarat dan data uji: Pemilik mengirim foto ke pengajuan berstatus `diajukan`.
     * Hasil yang diharapkan: Request ditolak; status, nomor pengajuan, dan metadata tidak berubah.
     * Jenis: Exception.    */
    public function test_tc_07_15_pembatasan_upload_berdasarkan_status_pengajuan(): void
    {
        sleep(3); // Jeda demo agar durasi test mudah diamati.
        Storage::fake('r2');

        $photoPath = __DIR__.DIRECTORY_SEPARATOR.'tc-07-15-foto-valid.png';

        $user = new User;
        $user->name = 'Pengguna Uji PBI07 TC15';
        $user->email = 'dusk-pbi07-'.Str::uuid().'@example.test';
        $user->password = Hash::make('password-uji-tc-07-15');
        $user->role = 'masyarakat';
        $user->is_active = true;
        $user->save();

        $pengajuan = new PengajuanPlts;
        $pengajuan->user_id = $user->id;
        $pengajuan->nomor_tiket = 'DUSK-'.Str::upper(Str::random(16));
        $pengajuan->ringkasan_pengajuan = 'Pengajuan non-draft untuk TC-07-15';
        $pengajuan->status_terkini = 'diajukan';
        $pengajuan->status_diperbarui_pada = now();
        $pengajuan->save();

        $pengajuan->lokasi()->create([
            'alamat_deskriptif' => 'Desa Uji PBI07 TC15, Kecamatan Uji, Kabupaten Uji',
            'latitude' => -6.914744,
            'longitude' => 107.609810,
        ]);

        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6l1sAAAAASUVORK5CYII=',
            true
        );

        try {
            file_put_contents($photoPath, $png);

            $csrfToken = Str::random(40);

            $response = $this->actingAs($user)
                ->withSession(['_token' => $csrfToken])
                ->post('/pengajuan/lokasi/bukti', [
                    '_token' => $csrfToken,
                    'draft_id' => $pengajuan->id,
                    'photos' => [
                        new UploadedFile(
                            $photoPath,
                            'tc-07-15-foto-valid.png',
                            'image/png',
                            null,
                            true
                        ),
                    ],
                ]);

            $response->assertStatus(409);
            $this->assertSame('diajukan', $pengajuan->fresh()->status_terkini);
            $this->assertSame(0, $pengajuan->bukti()->count());
            $this->assertSame([], Storage::disk('r2')->allFiles());
        } finally {
            $pengajuan->bukti()->delete();
            $pengajuan->lokasi()->delete();
            $pengajuan->delete();
            $user->delete();

            if (file_exists($photoPath)) {
                unlink($photoPath);
            }
        }
    }
}