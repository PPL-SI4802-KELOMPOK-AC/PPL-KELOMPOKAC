<?php

namespace Tests\Browser\PBI08;

use App\Models\PengajuanPlts;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\DuskTestCase;

class test_tc_08_10_mengakses_fitur_dengan_role_selain_masyarakatTest extends DuskTestCase
{
    /**
     * ID TC: TC-08-10
     * Skenario: Mengakses fitur dengan role selain masyarakat.
     * Jenis: Exception.
     * Hasil yang diharapkan: Admin dan pemerintah ditolak saat membuka
     * ringkasan, mengirim pengajuan, atau membuka tanda terima.
     */
    public function test_tc_08_10_mengakses_fitur_dengan_role_selain_masyarakat(): void
    {
        sleep(3); // Jeda demo agar durasi test lebih mudah diamati.
        $roles = ['admin', 'pemerintah'];
        $createdRecords = [];

        try {
            foreach ($roles as $role) {
                $user = new User;
                $user->name = 'Pengguna Uji PBI08 TC10 '.ucfirst($role);
                $user->email = 'dusk-pbi08-'.Str::uuid().'@example.test';
                $user->password = Hash::make('password-uji-tc-08-10');
                $user->role = $role;
                $user->is_active = true;
                $user->save();

                $draft = new PengajuanPlts;
                $draft->user_id = $user->id;
                $draft->nomor_tiket = 'DRAFT-DUSK-'.Str::upper(Str::random(12));
                $draft->ringkasan_pengajuan = 'Draft untuk pengujian TC-08-10';
                $draft->status_terkini = 'draft';
                $draft->status_diperbarui_pada = now();
                $draft->save();

                $terkirim = new PengajuanPlts;
                $terkirim->user_id = $user->id;
                $terkirim->nomor_tiket = 'PLTS-'.now()->format('Ymd').'-'.Str::ulid();
                $terkirim->ringkasan_pengajuan = 'Pengajuan terkirim untuk pengujian TC-08-10';
                $terkirim->status_terkini = 'diajukan';
                $terkirim->status_diperbarui_pada = now();
                $terkirim->save();

                $createdRecords[] = [$user, $draft, $terkirim];

                // Role non-masyarakat tidak boleh membuka ringkasan.
                $this->actingAs($user)
                    ->get('/pengajuan/lokasi/ringkasan')
                    ->assertForbidden();

                // Role non-masyarakat tidak boleh mengirim pengajuan.
                $csrfToken = Str::random(40);

                $this->actingAs($user)
                    ->withSession(['_token' => $csrfToken])
                    ->post('/pengajuan/lokasi/kirim', [
                        '_token' => $csrfToken,
                        'draft_id' => $draft->id,
                        'pernyataan' => '1',
                    ])
                    ->assertForbidden();

                // Role non-masyarakat tidak boleh membuka tanda terima.
                $this->actingAs($user)
                    ->get('/pengajuan/lokasi/terkirim/'.$terkirim->id)
                    ->assertForbidden();

                $this->assertSame('draft', $draft->fresh()->status_terkini);
                $this->assertSame('diajukan', $terkirim->fresh()->status_terkini);
                $this->assertSame(
                    0,
                    DB::table('riwayat_status_pengajuan')
                        ->whereIn('pengajuan_id', [$draft->id, $terkirim->id])
                        ->count()
                );
            }
        } finally {
            foreach ($createdRecords as [$user, $draft, $terkirim]) {
                DB::table('riwayat_status_pengajuan')
                    ->whereIn('pengajuan_id', [$draft->id, $terkirim->id])
                    ->delete();

                $draft->bukti()->delete();
                $draft->lokasi()->delete();
                $draft->delete();

                $terkirim->bukti()->delete();
                $terkirim->lokasi()->delete();
                $terkirim->delete();

                $user->delete();
            }
        }
    }
}