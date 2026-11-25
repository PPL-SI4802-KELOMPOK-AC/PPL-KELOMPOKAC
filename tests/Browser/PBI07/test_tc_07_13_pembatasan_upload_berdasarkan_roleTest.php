<?php

namespace Tests\Browser\PBI07;

use App\Models\PengajuanPlts;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\DuskTestCase;

class test_tc_07_13_pembatasan_upload_berdasarkan_roleTest extends DuskTestCase
{
    /**
     * ID TC: TC-07-13
     * Skenario: Mengunggah sebagai role selain masyarakat.
     * Jenis: Exception.
     * Input: Role admin dan pemerintah mengirim request upload.
     * Hasil yang diharapkan: Akses ditolak; foto dan metadata tidak tersimpan.
     */
    /**
     * ID: TC-07-13
     * Fokus pengujian: Pembatasan upload berdasarkan role.
     * Prasyarat dan data uji: Akun ber-role `admin` dan `pemerintah` mengirim request upload.
     * Hasil yang diharapkan: Kedua request mendapat status 403; foto dan metadata tidak tersimpan.
     * Jenis: Exception.    */
    public function test_tc_07_13_pembatasan_upload_berdasarkan_role(): void
    {
        sleep(3); // Jeda demo agar durasi test mudah diamati.
        Storage::fake('r2');

        $roles = ['admin', 'pemerintah'];
        $createdRecords = [];

        try {
            foreach ($roles as $role) {
                $user = new User;
                $user->name = 'Pengguna Uji PBI07 TC13 '.ucfirst($role);
                $user->email = 'dusk-pbi07-'.Str::uuid().'@example.test';
                $user->password = Hash::make('password-uji-tc-07-13');
                $user->role = $role;
                $user->is_active = true;
                $user->save();

                $draft = new PengajuanPlts;
                $draft->user_id = $user->id;
                $draft->nomor_tiket = 'DUSK-'.Str::upper(Str::random(16));
                $draft->ringkasan_pengajuan = 'Draft pengujian TC-07-13 role '.$role;
                $draft->status_terkini = 'draft';
                $draft->status_diperbarui_pada = now();
                $draft->save();

                $createdRecords[] = [$user, $draft];

                $csrfToken = Str::random(40);

                $response = $this->actingAs($user)
                    ->withSession(['_token' => $csrfToken])
                    ->post('/pengajuan/lokasi/bukti', [
                        '_token' => $csrfToken,
                        'draft_id' => $draft->id,
                        'photos' => [
                            UploadedFile::fake()->create('foto-uji.png', 1, 'image/png'),
                        ],
                    ]);

                $response->assertForbidden();
                $this->assertSame(0, $draft->bukti()->count());
            }

            $this->assertSame([], Storage::disk('r2')->allFiles());
        } finally {
            foreach ($createdRecords as [$user, $draft]) {
                $draft->bukti()->delete();
                $draft->lokasi()->delete();
                $draft->delete();
                $user->delete();
            }
        }
    }
}