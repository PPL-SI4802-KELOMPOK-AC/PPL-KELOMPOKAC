<?php

namespace Tests\Browser\PBI07;

use App\Models\PengajuanPlts;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\DuskTestCase;

class test_tc_07_17_konsistensi_saat_pencatatan_metadata_gagalTest extends DuskTestCase
{
    /**
     * ID TC: TC-07-17
     * Skenario: Gagal mencatat metadata setelah foto diunggah ke R2.
     * Jenis: Exception.
     * Hasil yang diharapkan: Foto baru dibersihkan, metadata baru tidak tercatat,
     * dan objek foto lama tetap aman.
     */
    /**
     * ID: TC-07-17
     * Fokus pengujian: Konsistensi saat pencatatan metadata gagal.
     * Prasyarat dan data uji: Foto baru berhasil disimpan ke R2; insert metadata gagal; terdapat foto lama.
     * Hasil yang diharapkan: Transaksi dibatalkan; objek baru dibersihkan; foto lama aman; metadata baru tidak tercatat.
     * Jenis: Exception.    */
    public function test_tc_07_17_konsistensi_saat_pencatatan_metadata_gagal(): void
    {
        sleep(3); // Jeda demo agar durasi test mudah diamati.
        $photoPath = __DIR__.DIRECTORY_SEPARATOR.'tc-07-17-foto-valid.png';

        $user = new User;
        $user->name = 'Pengguna Uji PBI07 TC17';
        $user->email = 'dusk-pbi07-'.Str::uuid().'@example.test';
        $user->password = Hash::make('password-uji-tc-07-17');
        $user->role = 'masyarakat';
        $user->is_active = true;
        $user->save();

        $draft = new PengajuanPlts;
        $draft->user_id = $user->id;
        $draft->nomor_tiket = 'DUSK-'.Str::upper(Str::random(16));
        $draft->ringkasan_pengajuan = 'Draft lokasi untuk pengujian TC-07-17';
        $draft->status_terkini = 'draft';
        $draft->status_diperbarui_pada = now();
        $draft->save();

        $draft->lokasi()->create([
            'alamat_deskriptif' => 'Desa Uji PBI07 TC17, Kecamatan Uji, Kabupaten Uji',
            'latitude' => -6.914744,
            'longitude' => 107.609810,
        ]);

        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6l1sAAAAASUVORK5CYII=',
            true
        );

        $oldObjectPath = "bukti-pengajuan/{$draft->id}/foto-lama.png";
        $newObjectPath = "bukti-pengajuan/{$draft->id}/foto-baru.png";

        // Mewakili satu metadata dan satu objek R2 lama yang harus tetap aman.
        $draft->bukti()->create([
            'nama_file_asli' => 'foto-lama.png',
            'path_file' => $oldObjectPath,
            'mime_type' => 'image/png',
            'ukuran_byte' => strlen($png),
            'keterangan' => null,
        ]);

        $objectsOnR2Tiruan = [$oldObjectPath => true];

        try {
            file_put_contents($photoPath, $png);

            // Simulasikan upload ke R2 berhasil, lalu pastikan objek baru dihapus
            // ketika pencatatan metadata gagal.
            $diskR2Palsu = Mockery::mock(FilesystemAdapter::class);

            $diskR2Palsu->shouldReceive('putFileAs')
                ->once()
                ->andReturnUsing(function (...$arguments) use (&$objectsOnR2Tiruan, $newObjectPath) {
                    $objectsOnR2Tiruan[$newObjectPath] = true;

                    return $newObjectPath;
                });

            $diskR2Palsu->shouldReceive('delete')
                ->once()
                ->with($newObjectPath)
                ->andReturnUsing(function ($path) use (&$objectsOnR2Tiruan) {
                    unset($objectsOnR2Tiruan[$path]);

                    return true;
                });

            Storage::shouldReceive('disk')
                ->once()
                ->with('r2')
                ->andReturn($diskR2Palsu);

            // Biarkan callback transaksi berjalan, tetapi gagalkan insert metadata.
            $metadataTable = Mockery::mock();
            $metadataTable->shouldReceive('insert')
                ->once()
                ->andThrow(new RuntimeException('Simulasi kegagalan insert metadata'));

            DB::shouldReceive('transaction')
                ->once()
                ->andReturnUsing(function ($callback) {
                    return $callback();
                });

            DB::shouldReceive('table')
                ->once()
                ->with('bukti_pengajuan')
                ->andReturn($metadataTable);

            $csrfToken = Str::random(40);

            $response = $this->actingAs($user)
                ->withSession(['_token' => $csrfToken])
                ->post('/pengajuan/lokasi/bukti', [
                    '_token' => $csrfToken,
                    'draft_id' => $draft->id,
                    'photos' => [
                        new UploadedFile(
                            $photoPath,
                            'tc-07-17-foto-valid.png',
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

            // Metadata lama tetap ada; metadata untuk foto baru tidak tercatat.
            $this->assertSame(1, $draft->bukti()->count());
            $this->assertSame(
                ['foto-lama.png'],
                $draft->bukti()->pluck('nama_file_asli')->all()
            );

            // Foto baru sudah dibersihkan dan objek lama tetap ada.
            $this->assertSame(
                [$oldObjectPath => true],
                $objectsOnR2Tiruan
            );
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