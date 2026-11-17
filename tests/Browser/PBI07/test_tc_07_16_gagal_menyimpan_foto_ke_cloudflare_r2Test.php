<?php

namespace Tests\Browser\PBI07;

use App\Models\PengajuanPlts;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\DuskTestCase;

class test_tc_07_16_gagal_menyimpan_foto_ke_cloudflare_r2Test extends DuskTestCase
{
    /**
     * ID TC: TC-07-16
     * Skenario: Gagal menyimpan foto ke Cloudflare R2.
     * Jenis: Exception.
     * Hasil yang diharapkan: Pesan gagal tampil dan metadata tidak tercatat.
     */
    public function test_tc_07_16_gagal_menyimpan_foto_ke_cloudflare_r2(): void
    {
        $photoPath = __DIR__.DIRECTORY_SEPARATOR.'tc-07-16-foto-valid.png';

        $user = new User;
        $user->name = 'Pengguna Uji PBI07 TC16';
        $user->email = 'dusk-pbi07-'.Str::uuid().'@example.test';
        $user->password = Hash::make('password-uji-tc-07-16');
        $user->role = 'masyarakat';
        $user->is_active = true;
        $user->save();

        $draft = new PengajuanPlts;
        $draft->user_id = $user->id;
        $draft->nomor_tiket = 'DUSK-'.Str::upper(Str::random(16));
        $draft->ringkasan_pengajuan = 'Draft lokasi untuk pengujian TC-07-16';
        $draft->status_terkini = 'draft';
        $draft->status_diperbarui_pada = now();
        $draft->save();

        $draft->lokasi()->create([
            'alamat_deskriptif' => 'Desa Uji PBI07 TC16, Kecamatan Uji, Kabupaten Uji',
            'latitude' => -6.914744,
            'longitude' => 107.609810,
        ]);

        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6l1sAAAAASUVORK5CYII=',
            true
        );

        try {
            file_put_contents($photoPath, $png);

            $diskR2Palsu = Mockery::mock(FilesystemAdapter::class);
            $diskR2Palsu->shouldReceive('putFileAs')
                ->once()
                ->andThrow(new RuntimeException('Simulasi kegagalan penyimpanan R2'));

            Storage::shouldReceive('disk')
                ->once()
                ->with('r2')
                ->andReturn($diskR2Palsu);

            $csrfToken = Str::random(40);

            $response = $this->actingAs($user)
                ->withSession(['_token' => $csrfToken])
                ->post('/pengajuan/lokasi/bukti', [
                    '_token' => $csrfToken,
                    'draft_id' => $draft->id,
                    'photos' => [
                        new UploadedFile(
                            $photoPath,
                            'tc-07-16-foto-valid.png',
                            'image/png',
                            null,
                            true
                        ),
                    ],
                ]);

            $response->assertRedirect('/pengajuan/lokasi/bukti')
                ->assertSessionHasErrors([
                    'photos' => 'Foto gagal disimpan. Periksa koneksi dan coba lagi.',
                ]);

            $this->assertSame(0, $draft->bukti()->count());
        } finally {
            $draft->bukti()->delete();
            $draft->lokasi()->delete();
            $draft->delete();
            $user->delete();

            if (file_exists($photoPath)) {
                unlink($photoPath);
            }
        }
    }
}