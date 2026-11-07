<?php

namespace Tests\Feature;

use App\Models\PengajuanPlts;
use App\Models\User;
use App\Services\Pengajuan\LocationCatalog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PengajuanHistoryTest extends TestCase
{
    private int $userSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'app.key' => 'base64:'.base64_encode(str_repeat('h', 32)),
        ]);
        DB::purge('sqlite');
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->withoutVite();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role');
            $table->timestamps();
        });

        Schema::create('pengajuan_plts', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_tiket', 50)->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->unsignedBigInteger('wilayah_id')->nullable();
            $table->string('ringkasan_pengajuan');
            $table->enum('status_terkini', ['draft', 'diajukan', 'diperiksa', 'diverifikasi', 'diproses', 'selesai', 'ditolak']);
            $table->timestamp('status_diperbarui_pada')->nullable()->useCurrent();
        });

        Schema::create('lokasi_pengajuan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->unique()->constrained('pengajuan_plts');
            $table->text('alamat_deskriptif');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 11, 7)->nullable();
        });

        Schema::create('riwayat_status_pengajuan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->constrained('pengajuan_plts');
            $table->foreignId('diubah_oleh')->constrained('users');
            $statuses = ['diajukan', 'diperiksa', 'diverifikasi', 'diproses', 'selesai', 'ditolak'];
            $table->enum('status_sebelumnya', $statuses)->nullable();
            $table->enum('status_baru', $statuses);
            $table->string('jenis_tindakan', 50);
            $table->string('hasil_verifikasi', 50)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    private function user(string $role = 'masyarakat'): User
    {
        $user = new User([
            'name' => 'Pemohon Uji '.$this->userSequence,
            'email' => 'pemohon-'.$this->userSequence.'@example.test',
            'password' => 'password-uji',
        ]);
        $user->role = $role;
        $user->save();
        $this->userSequence++;

        return $user;
    }

    private function pengajuan(User $owner, string $ticket, string $status = 'diajukan', ?string $address = null): PengajuanPlts
    {
        $pengajuan = new PengajuanPlts;
        $pengajuan->user_id = $owner->id;
        $pengajuan->nomor_tiket = $ticket;
        $pengajuan->ringkasan_pengajuan = 'Lokasi uji';
        $pengajuan->status_terkini = $status;
        $pengajuan->status_diperbarui_pada = '2020-01-01 00:00:00';
        $pengajuan->save();

        if ($address !== null) {
            $pengajuan->lokasi()->create(['alamat_deskriptif' => $address]);
        }

        return $pengajuan;
    }

    private function catatanPengajuanBaru(PengajuanPlts $pengajuan, User $actor, string $createdAt): void
    {
        DB::table('riwayat_status_pengajuan')->insert([
            'pengajuan_id' => $pengajuan->id,
            'diubah_oleh' => $actor->id,
            'status_sebelumnya' => null,
            'status_baru' => 'diajukan',
            'jenis_tindakan' => 'pengajuan_baru',
            'catatan' => 'Pengajuan awal untuk pengujian.',
            'created_at' => $createdAt,
        ]);
    }

    public function test_only_the_logged_in_users_submitted_applications_are_shown(): void
    {
        $owner = $this->user();
        $other = $this->user();
        $submitted = $this->pengajuan($owner, 'PLTS-OWN-001');
        $this->pengajuan($other, 'PLTS-OTHER-001');
        $this->pengajuan($owner, 'DRAFT-OWN-001', 'draft');
        $this->catatanPengajuanBaru($submitted, $owner, '2026-10-28 09:30:00');

        $this->actingAs($owner)->get(route('pengajuan.riwayat'))
            ->assertOk()
            ->assertSee('PLTS-OWN-001')
            ->assertDontSee('PLTS-OTHER-001')
            ->assertDontSee('DRAFT-OWN-001')
            ->assertSee('Diajukan')
            ->assertSee(route('pengajuan.terkirim', ['pengajuan' => $submitted->id]), false)
            ->assertSee('Lihat detail')
            ->assertDontSee('Timeline');
    }

    public function test_history_shows_latest_database_status_for_owned_application_only(): void
    {
        $owner = $this->user();
        $other = $this->user();
        $owned = $this->pengajuan($owner, 'PLTS-OWN-STATUS-001', 'diajukan');
        $foreign = $this->pengajuan($other, 'PLTS-OTHER-STATUS-001', 'diajukan');

        $this->actingAs($owner);

        foreach ([
            'diperiksa' => 'Diperiksa',
            'diverifikasi' => 'Diverifikasi',
            'selesai' => 'Selesai',
        ] as $status => $label) {
            DB::table('pengajuan_plts')
                ->where('id', $owned->id)
                ->update(['status_terkini' => $status]);

            $this->get(route('pengajuan.riwayat'))
                ->assertOk()
                ->assertSee('PLTS-OWN-STATUS-001')
                ->assertSee('data-history-status="'.$status.'"', false)
                ->assertSee($label)
                ->assertDontSee('PLTS-OTHER-STATUS-001');
        }

        $this->assertSame('diajukan', $foreign->fresh()->status_terkini);
    }

    public function test_history_shows_status_updated_timestamp_in_indonesian_date_time_format(): void
    {
        $owner = $this->user();
        $pengajuan = $this->pengajuan($owner, 'PLTS-STATUS-TIME-001');
        $pengajuan->status_diperbarui_pada = '2026-11-07 14:35:00';
        $pengajuan->save();

        $this->actingAs($owner)->get(route('pengajuan.riwayat'))
            ->assertOk()
            ->assertSee('PLTS-STATUS-TIME-001')
            ->assertSee('Diperbarui:')
            ->assertSee('07 Nov 2026, 14:35');
    }

    public function test_history_handles_null_status_updated_timestamp(): void
    {
        $owner = $this->user();
        $pengajuan = $this->pengajuan($owner, 'PLTS-STATUS-TIME-NULL-001');
        DB::table('pengajuan_plts')
            ->where('id', $pengajuan->id)
            ->update(['status_diperbarui_pada' => null]);

        $this->actingAs($owner)->get(route('pengajuan.riwayat'))
            ->assertOk()
            ->assertSee('PLTS-STATUS-TIME-NULL-001')
            ->assertSee('Waktu pembaruan tidak tersedia');
    }

    public function test_submission_date_comes_from_initial_history_and_missing_date_is_last(): void
    {
        $owner = $this->user();
        $latest = $this->pengajuan($owner, 'PLTS-DATE-NEW');
        $earlier = $this->pengajuan($owner, 'PLTS-DATE-OLD');
        $withoutInitialRecord = $this->pengajuan($owner, 'PLTS-DATE-MISSING');
        $this->catatanPengajuanBaru($latest, $owner, '2026-10-28 09:30:00');
        $this->catatanPengajuanBaru($earlier, $owner, '2026-10-20 09:30:00');

        $this->actingAs($owner)->get(route('pengajuan.riwayat'))
            ->assertOk()
            ->assertSeeInOrder(['PLTS-DATE-NEW', 'PLTS-DATE-OLD', 'PLTS-DATE-MISSING'])
            ->assertSee('28 Okt 2026')
            ->assertSee('20 Okt 2026')
            ->assertSee('Tanggal tidak tersedia')
            ->assertSee('2026');
    }

    public function test_location_uses_catalog_names_and_falls_back_to_saved_address(): void
    {
        $owner = $this->user();
        $catalog = app(LocationCatalog::class);
        $validAddress = $catalog->address([
            'kabupaten' => '3205',
            'kecamatan' => 'Cikelet',
            'desa' => '32.05.30.2010',
            'alamat' => 'Kampung Uji, RT 01',
        ]);
        $this->pengajuan($owner, 'PLTS-LOCATION-VALID', 'diajukan', $validAddress);
        $fallbackAddress = 'Lokasi lama dengan format yang tidak dikenal';
        $this->pengajuan($owner, 'PLTS-LOCATION-FALLBACK', 'diajukan', $fallbackAddress);

        $this->actingAs($owner)->get(route('pengajuan.riwayat'))
            ->assertOk()
            ->assertSee('Awassagara')
            ->assertSee('Kabupaten Garut')
            ->assertSee($fallbackAddress)
            ->assertSee('Tidak tersedia');
    }

    public function test_search_matches_ticket_number_without_exposing_other_users(): void
    {
        $owner = $this->user();
        $other = $this->user();
        $this->pengajuan($owner, 'PLTS-CARI-001');
        $this->pengajuan($other, 'PLTS-CARI-001-OTHER');

        $this->actingAs($owner)->get(route('pengajuan.riwayat', ['q' => 'CARI-001']))
            ->assertOk()
            ->assertSee('PLTS-CARI-001')
            ->assertDontSee('PLTS-CARI-001-OTHER');
    }

    public function test_search_matches_village_text_in_saved_address(): void
    {
        $owner = $this->user();
        $this->pengajuan($owner, 'PLTS-DESA-001', 'diajukan', 'Desa Sukamaju, Kecamatan Uji');
        $this->pengajuan($owner, 'PLTS-DESA-002', 'diajukan', 'Desa Mekarsari, Kecamatan Uji');

        $this->actingAs($owner)->get(route('pengajuan.riwayat', ['q' => 'Sukamaju']))
            ->assertOk()
            ->assertSee('PLTS-DESA-001')
            ->assertDontSee('PLTS-DESA-002');
    }

    public function test_status_filter_uses_only_submitted_statuses_for_the_logged_in_user(): void
    {
        $owner = $this->user();
        $other = $this->user();
        $this->pengajuan($owner, 'PLTS-STATUS-SHOW', 'diajukan');
        $this->pengajuan($owner, 'PLTS-STATUS-HIDE', 'selesai');
        $this->pengajuan($owner, 'PLTS-STATUS-DRAFT', 'draft');
        $this->pengajuan($other, 'PLTS-STATUS-OTHER', 'diperiksa');

        $response = $this->actingAs($owner)->get(route('pengajuan.riwayat', ['status' => 'diajukan']))
            ->assertOk()
            ->assertSee('PLTS-STATUS-SHOW')
            ->assertDontSee('PLTS-STATUS-HIDE')
            ->assertDontSee('PLTS-STATUS-DRAFT')
            ->assertDontSee('PLTS-STATUS-OTHER');

        $this->assertSame(['diajukan', 'selesai'], $response->viewData('statusOptions')->all());
    }

    public function test_period_filter_uses_first_submission_record_and_excludes_missing_dates(): void
    {
        $owner = $this->user();
        $inPeriod = $this->pengajuan($owner, 'PLTS-PERIODE-IN');
        $otherPeriod = $this->pengajuan($owner, 'PLTS-PERIODE-OUT');
        $withoutDate = $this->pengajuan($owner, 'PLTS-PERIODE-MISSING');
        $this->catatanPengajuanBaru($inPeriod, $owner, '2026-09-28 09:30:00');
        $this->catatanPengajuanBaru($inPeriod, $owner, '2026-10-02 09:30:00');
        $this->catatanPengajuanBaru($otherPeriod, $owner, '2026-10-01 09:30:00');
        $otherPeriod->status_diperbarui_pada = '2026-09-30 00:00:00';
        $otherPeriod->save();

        $this->actingAs($owner)->get(route('pengajuan.riwayat', ['periode' => '2026-09']))
            ->assertOk()
            ->assertSee('PLTS-PERIODE-IN')
            ->assertDontSee('PLTS-PERIODE-OUT')
            ->assertDontSee('PLTS-PERIODE-MISSING');
    }

    public function test_combined_filters_paginate_and_keep_filter_parameters(): void
    {
        $owner = $this->user();
        $other = $this->user();

        for ($index = 1; $index <= 12; $index++) {
            $ticket = sprintf('PLTS-PAGE-%02d', $index);
            $application = $this->pengajuan($owner, $ticket, 'diajukan', 'Desa Sukamaju');
            $this->catatanPengajuanBaru($application, $owner, sprintf('2026-09-%02d 09:30:00', $index));
        }

        $unmatched = $this->pengajuan($owner, 'PLTS-PAGE-WRONG-STATUS', 'selesai', 'Desa Sukamaju');
        $this->catatanPengajuanBaru($unmatched, $owner, '2026-09-20 09:30:00');
        $foreign = $this->pengajuan($other, 'PLTS-PAGE-FOREIGN', 'diajukan', 'Desa Sukamaju');
        $this->catatanPengajuanBaru($foreign, $other, '2026-09-21 09:30:00');

        $parameters = ['q' => 'Sukamaju', 'status' => 'diajukan', 'periode' => '2026-09'];
        $firstPage = $this->actingAs($owner)->get(route('pengajuan.riwayat', $parameters))
            ->assertOk()
            ->assertSee('PLTS-PAGE-12')
            ->assertDontSee('PLTS-PAGE-02');
        $paginator = $firstPage->viewData('pengajuans');
        $this->assertSame(12, $paginator->total());
        $this->assertCount(10, $paginator->items());

        $nextPageUrl = $paginator->url(2);
        parse_str((string) parse_url($nextPageUrl, PHP_URL_QUERY), $query);
        $this->assertSame('Sukamaju', $query['q'] ?? null);
        $this->assertSame('diajukan', $query['status'] ?? null);
        $this->assertSame('2026-09', $query['periode'] ?? null);
        $this->assertSame('2', (string) ($query['page'] ?? ''));

        $secondPage = $this->get($nextPageUrl)
            ->assertOk()
            ->assertSee('PLTS-PAGE-02')
            ->assertDontSee('PLTS-PAGE-FOREIGN')
            ->assertDontSee('PLTS-PAGE-WRONG-STATUS');
        $this->assertCount(2, $secondPage->viewData('pengajuans')->items());
    }

    public function test_summary_counts_only_the_users_submitted_rows_and_ignores_filters_and_page(): void
    {
        $owner = $this->user();
        $other = $this->user();
        $statuses = ['diajukan', 'diperiksa', 'diverifikasi', 'diproses', 'selesai', 'ditolak'];

        foreach ($statuses as $index => $status) {
            $application = $this->pengajuan($owner, 'PLTS-SUMMARY-'.strtoupper($status), $status);
            $this->catatanPengajuanBaru($application, $owner, sprintf('2026-09-%02d 09:30:00', $index + 1));
        }

        $this->pengajuan($owner, 'PLTS-SUMMARY-DRAFT', 'draft');
        $this->pengajuan($other, 'PLTS-SUMMARY-FOREIGN', 'selesai');

        $response = $this->actingAs($owner)->get(route('pengajuan.riwayat', [
            'q' => 'PLTS-SUMMARY-DIAJUKAN',
            'status' => 'diajukan',
            'periode' => '2026-09',
            'page' => 2,
        ]))->assertOk();

        $this->assertSame([
            'total' => 6,
            'in_follow_up' => 4,
            'completed' => 1,
        ], $response->viewData('summary'));
    }

    public function test_guests_are_redirected_and_non_masyarakat_roles_are_forbidden(): void
    {
        $this->get(route('pengajuan.riwayat'))->assertRedirect(route('login'));

        foreach (['pemerintah', 'admin'] as $role) {
            $this->actingAs($this->user($role))
                ->get(route('pengajuan.riwayat'))
                ->assertForbidden();
        }
    }
}
