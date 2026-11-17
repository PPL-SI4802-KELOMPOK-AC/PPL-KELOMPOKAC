<?php

namespace Tests\Browser\PBI07;

use App\Models\PengajuanPlts;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\DuskTestCase;

class test_tc_07_14_mengunggah_foto_ke_draft_pengguna_lainTest extends DuskTestCase
{
    /**
     * ID TC: TC-07-14
     * Skenario: Mengunggah foto ke draft pengguna lain.
     * Jenis: Exception.
     * Input: Pengguna mencoba upload menggunakan ID draft milik pengguna lain.
     * Hasil yang diharapkan: Akses ditolak dan foto tidak ditambahkan ke draft.
     */
    public function test_tc_07_14_mengunggah_foto_ke_draft_pengguna_lain(): void
    {
        Storage::fake('r2');

        $password = 'password-uji-tc-07-14';
        $photoPath = __DIR__.DIRECTORY_SEPARATOR.'tc-07-14-foto-valid.png';

        $pemilikDraft = new User;
        $pemilikDraft->name = 'Pemilik Draft Uji PBI07 TC14';
        $pemilikDraft->email = 'dusk-pbi07-'.Str::uuid().'@example.test';
        $pemilikDraft->password = Hash::make($password);
        $pemilikDraft->role = 'masyarakat';
        $pemilikDraft->is_active = true;
        $pemilikDraft->save();

        $penggunaLain = new User;
        $penggunaLain->name = 'Pengguna Lain Uji PBI07 TC14';
        $penggunaLain->email = 'dusk-pbi07-'.Str::uuid().'@example.test';
        $penggunaLain->password = Hash::make($password);
        $penggunaLain->role = 'masyarakat';
        $penggunaLain->is_active = true;
        $penggunaLain->save();

        $draft = new PengajuanPlts;
        $draft->user_id = $pemilikDraft->id;
        $draft->nomor_tiket = 'DUSK-'.Str::upper(Str::random(16));
        $draft->ringkasan_pengajuan = 'Draft milik pengguna lain untuk TC-07-14';
        $draft->status_terkini = 'draft';
        $draft->status_diperbarui_pada = now();
        $draft->save();

        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6l1sAAAAASUVORK5CYII=',
            true
        );

        try {
            file_put_contents($photoPath, $png);

            $csrfToken = Str::random(40);

            $response = $this->actingAs($penggunaLain)
                ->withSession(['_token' => $csrfToken])
                ->post('/pengajuan/lokasi/bukti', [
                    '_token' => $csrfToken,
                    'draft_id' => $draft->id,
                    'photos' => [
                        new UploadedFile(
                            $photoPath,
                            'tc-07-14-foto-valid.png',
                            'image/png',
                            null,
                            true
                        ),
                    ],
                ]);

            $response->assertNotFound();
            $this->assertSame(0, $draft->bukti()->count());
            $this->assertSame([], Storage::disk('r2')->allFiles());
        } finally {
            $draft->bukti()->delete();
            $draft->delete();
            $pemilikDraft->delete();
            $penggunaLain->delete();

            if (file_exists($photoPath)) {
                unlink($photoPath);
            }
        }
    }
}