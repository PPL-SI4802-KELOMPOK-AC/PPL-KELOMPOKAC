<?php

namespace Tests\Browser\PBI08;

use App\Models\PengajuanPlts;
use App\Models\User;
use App\Services\Pengajuan\LocationCatalog;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDOException;
use Tests\DuskTestCase;

class test_tc_08_15_gagal_menyimpan_riwayat_status_saat_pengirimanTest extends DuskTestCase
{
    /**
     * ID TC: TC-08-15
     * Skenario: Gagal menyimpan riwayat status saat pengiriman.
     * Jenis: Exception.
     * Hasil yang diharapkan: Respons 503; transaksi dibatalkan;
     * status dan nomor draft tetap, serta riwayat tidak tercatat.
     */
    public function test_tc_08_15_gagal_menyimpan_riwayat_status_saat_pengiriman(): void
    {
        sleep(3); // Jeda demo agar durasi test lebih mudah diamati.
        $address = app(LocationCatalog::class)->address([
            'kabupaten' => '3205',
            'kecamatan' => 'Cikelet',
            'desa' => '32.05.30.2010',
            'alamat' => 'Kampung Uji TC15, RT 01',
        ]);

        $user = new User;
        $user->name = 'Pengguna Uji PBI08 TC15';
        $user->email = 'dusk-pbi08-'.Str::uuid().'@example.test';
        $user->password = Hash::make('password-uji-tc-08-15');
        $user->role = 'masyarakat';
        $user->is_active = true;
        $user->save();

        $draft = new PengajuanPlts;
        $draft->user_id = $user->id;
        $draft->nomor_tiket = 'DRAFT-DUSK-'.Str::upper(Str::random(12));
        $draft->ringkasan_pengajuan = 'Draft lengkap untuk pengujian TC-08-15';
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
            $draft->bukti()->create([
                'nama_file_asli' => 'tc-08-15-foto.png',
                'path_file' => "bukti-pengajuan/{$draft->id}/tc-08-15-foto.png",
                'mime_type' => 'image/png',
                'ukuran_byte' => 1024,
                'keterangan' => null,
            ]);

            $failureInjected = false;

            // Simulasikan kegagalan saat query insert riwayat dijalankan.
            // Exception terjadi di dalam transaksi agar perubahan status
            // dan nomor pengajuan dapat diuji rollback-nya.
            DB::listen(function (QueryExecuted $query) use (&$failureInjected) {
                if (
                    ! $failureInjected
                    && str_contains(
                        strtolower($query->sql),
                        'riwayat_status_pengajuan'
                    )
                ) {
                    $failureInjected = true;

                    throw new QueryException(
                        $query->connection->getName(),
                        $query->sql,
                        $query->bindings,
                        new PDOException('Simulasi kegagalan insert riwayat')
                    );
                }
            });

            $csrfToken = Str::random(40);

            $response = $this->actingAs($user)
                ->withSession(['_token' => $csrfToken])
                ->post('/pengajuan/lokasi/kirim', [
                    '_token' => $csrfToken,
                    'draft_id' => $draft->id,
                    'pernyataan' => '1',
                ]);

            $response->assertStatus(503)
                ->assertSee('Database belum tersedia')
                ->assertSee('Database belum dapat diakses');

            $this->assertTrue($failureInjected);
            $this->assertSame('draft', $draft->fresh()->status_terkini);
            $this->assertSame($nomorDraft, $draft->fresh()->nomor_tiket);
            $this->assertStringStartsWith('DRAFT-', $draft->fresh()->nomor_tiket);
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