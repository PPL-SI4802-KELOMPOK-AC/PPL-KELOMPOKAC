<?php

namespace Tests\Feature;

use App\Models\PengajuanPlts;
use App\Models\User;
use App\Services\Pengajuan\LocationCatalog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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
            'database.connections.sqlite.url' => null,
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

        Schema::create('bukti_pengajuan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->constrained('pengajuan_plts');
            $table->string('nama_file_asli', 255);
            $table->string('path_file', 512)->unique();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('ukuran_byte');
            $table->string('keterangan', 255)->nullable();
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
            ->assertSee(route('pengajuan.riwayat.show', ['pengajuan' => $submitted->id]), false)
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

    public function test_search_ignores_ticket_number_and_non_village_address_components(): void
    {
        $owner = $this->user();
        $other = $this->user();
        $this->pengajuan($owner, 'PLTS-CARI-001', 'diajukan',
            'Jalan CARI-001, RT 03, Awassagara, Cikelet, Kabupaten Garut, Jawa Barat');
        $this->pengajuan($other, 'PLTS-CARI-001-OTHER');

        $this->actingAs($owner)->get(route('pengajuan.riwayat', ['q' => 'CARI-001']))
            ->assertOk()
            ->assertDontSee('PLTS-CARI-001')
            ->assertDontSee('PLTS-CARI-001-OTHER');

        $this->get(route('pengajuan.riwayat', ['q' => 'AWASSAGARA']))->assertSee('PLTS-CARI-001');
        $this->get(route('pengajuan.riwayat', ['q' => 'Cikelet']))->assertDontSee('PLTS-CARI-001');
        $this->get(route('pengajuan.riwayat', ['q' => '%']))->assertDontSee('PLTS-CARI-001');
    }

    public function test_search_matches_village_text_in_saved_address(): void
    {
        $owner = $this->user();
        $this->pengajuan($owner, 'PLTS-DESA-001', 'diajukan', 'Desa Sukamaju, Kecamatan Uji');
        $this->pengajuan($owner, 'PLTS-DESA-002', 'diajukan', 'Desa Mekarsari, Kecamatan Uji');

        $this->pengajuan($this->user(), 'PLTS-DESA-OTHER', 'diajukan', 'Kelurahan Sukamaju');

        $this->actingAs($owner)->get(route('pengajuan.riwayat', ['q' => 'KaMaJ']))
            ->assertOk()
            ->assertSee('PLTS-DESA-001')
            ->assertDontSee('PLTS-DESA-002')
            ->assertDontSee('PLTS-DESA-OTHER');
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

        $this->assertSame([
            'diajukan' => 'Diajukan',
            'diperiksa' => 'Sedang Diperiksa',
            'diverifikasi' => 'Diverifikasi',
            'diproses' => 'Dalam Proses',
            'selesai' => 'Selesai',
            'ditolak' => 'Ditolak',
        ], $response->viewData('statusOptions'));
        $response->assertSee('value="diajukan" selected', false)->assertDontSee('value="draft"', false);
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
            ->assertDontSee('PLTS-PERIODE-MISSING')
            ->assertSee('name="periode" value="2026-09"', false);

        $this->get(route('pengajuan.riwayat', ['tanggal' => '2026-10-02']))
            ->assertOk()
            ->assertDontSee('PLTS-PERIODE-IN')
            ->assertDontSee('PLTS-PERIODE-OUT');

        $this->get(route('pengajuan.riwayat', ['tanggal' => '2026-10-01', 'periode' => '2026-09']))
            ->assertOk()
            ->assertSee('PLTS-PERIODE-OUT')
            ->assertDontSee('PLTS-PERIODE-IN')
            ->assertSee('value="2026-10-01"', false)
            ->assertSee('Reset Filter');

        $this->get(route('pengajuan.riwayat'))
            ->assertOk()
            ->assertSee('PLTS-PERIODE-IN')
            ->assertSee('PLTS-PERIODE-OUT')
            ->assertSee('PLTS-PERIODE-MISSING');
    }

    public function test_combined_filters_paginate_and_keep_filter_parameters(): void
    {
        $owner = $this->user();
        $other = $this->user();

        for ($index = 1; $index <= 12; $index++) {
            $ticket = sprintf('PLTS-PAGE-%02d', $index);
            $application = $this->pengajuan($owner, $ticket, 'diajukan', 'Desa Sukamaju');
            $this->catatanPengajuanBaru($application, $owner, sprintf('2026-09-20 %02d:30:00', $index));
        }

        $unmatched = $this->pengajuan($owner, 'PLTS-PAGE-WRONG-STATUS', 'selesai', 'Desa Sukamaju');
        $this->catatanPengajuanBaru($unmatched, $owner, '2026-09-20 09:30:00');
        $foreign = $this->pengajuan($other, 'PLTS-PAGE-FOREIGN', 'diajukan', 'Desa Sukamaju');
        $this->catatanPengajuanBaru($foreign, $other, '2026-09-20 09:30:00');

        $parameters = ['q' => 'Sukamaju', 'status' => 'diajukan', 'tanggal' => '2026-09-20'];
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
        $this->assertSame('2026-09-20', $query['tanggal'] ?? null);
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

    public function test_detail_displays_the_selected_submissions_data_and_private_photo_links(): void
    {
        $owner = $this->user();
        $address = app(LocationCatalog::class)->address([
            'kabupaten' => '3205',
            'kecamatan' => 'Cikelet',
            'desa' => '32.05.30.2010',
            'alamat' => 'Kampung Uji, RT 01',
        ]);
        $submission = $this->pengajuan($owner, 'PLTS-DETAIL-001', 'diajukan', $address);
        $submission->lokasi()->update(['latitude' => -7.5812345, 'longitude' => 107.6984567]);
        $submission->status_diperbarui_pada = '2026-11-21 10:30:00';
        $submission->save();
        $this->catatanPengajuanBaru($submission, $owner, '2026-11-21 09:15:00');

        $photos = $submission->bukti()->createMany([
            ['nama_file_asli' => 'Rumah.png', 'path_file' => 'internal/rumah.png', 'mime_type' => 'image/png', 'ukuran_byte' => 100],
            ['nama_file_asli' => 'Jalan.png', 'path_file' => 'internal/jalan.png', 'mime_type' => 'image/png', 'ukuran_byte' => 200],
        ]);
        $otherSubmission = $this->pengajuan($owner, 'PLTS-DETAIL-002');
        $otherSubmission->bukti()->create([
            'nama_file_asli' => 'Foto pengajuan lain.png',
            'path_file' => 'internal/lain.png',
            'mime_type' => 'image/png',
            'ukuran_byte' => 100,
        ]);

        $response = $this->actingAs($owner)->get(route('pengajuan.riwayat.show', $submission))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Detail Pengajuan PLTS')
            ->assertSee('PLTS-DETAIL-001')
            ->assertSee($owner->name)
            ->assertSee($address)
            ->assertSee('Awassagara')
            ->assertSee('Cikelet')
            ->assertSee('Kabupaten Garut')
            ->assertSee('Jawa Barat')
            ->assertSee('-7.5812345, 107.6984567')
            ->assertSee($submission->ringkasan_pengajuan)
            ->assertSee('21 Nov 2026, 09:15')
            ->assertSee('21 Nov 2026, 10:30')
            ->assertSee('Diajukan')
            ->assertSeeInOrder(['Rumah.png', 'Jalan.png'])
            ->assertDontSee('internal/')
            ->assertDontSee('Foto pengajuan lain.png')
            ->assertDontSee('Pengajuan berhasil dikirim')
            ->assertSee('href="'.route('pengajuan.riwayat').'"', false)
            ->assertSee('Kembali ke Riwayat')
            ->assertSee('aria-current="location"', false);

        foreach ($photos as $photo) {
            $response->assertSee('src="'.route('pengajuan.bukti.show', $photo->id).'"', false);
        }
    }

    public function test_detail_checks_authentication_ownership_and_submitted_status(): void
    {
        $owner = $this->user();
        $submission = $this->pengajuan($owner, 'PLTS-PRIVATE');
        $draft = $this->pengajuan($owner, 'DRAFT-PRIVATE', 'draft');
        $url = route('pengajuan.riwayat.show', $submission);

        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($this->user('pemerintah'))->get($url)->assertForbidden();
        $this->actingAs($this->user())->get($url)->assertNotFound()->assertDontSee($submission->nomor_tiket);
        $this->actingAs($owner)->get(route('pengajuan.riwayat.show', $draft))->assertNotFound();
        $this->get(route('pengajuan.riwayat.show', 99999))->assertNotFound();
    }

    public function test_detail_reads_latest_status_and_orders_real_history_by_time_and_id(): void
    {
        $owner = $this->user();
        $submission = $this->pengajuan($owner, 'PLTS-TIMELINE');
        $this->catatanPengajuanBaru($submission, $owner, '2026-11-21 08:15:00');
        $url = route('pengajuan.riwayat.show', $submission);

        $this->actingAs($owner)->get($url)->assertOk()
            ->assertSee('data-detail-status="diajukan"', false)
            ->assertDontSee('Sedang Diperiksa')
            ->assertDontSee('Dalam Proses');

        foreach ([
            ['diverifikasi', '2026-11-21 11:30:00'],
            ['diperiksa', '2026-11-21 10:15:00'],
            ['ditolak', '2026-11-21 11:30:00'],
        ] as [$status, $createdAt]) {
            DB::table('riwayat_status_pengajuan')->insert([
                'pengajuan_id' => $submission->id,
                'diubah_oleh' => $owner->id,
                'status_baru' => $status,
                'jenis_tindakan' => 'tindak_lanjut',
                'catatan' => 'Catatan internal petugas',
                'hasil_verifikasi' => 'Hasil internal petugas',
                'created_at' => $createdAt,
            ]);
        }
        $submission->status_terkini = 'ditolak';
        $submission->status_diperbarui_pada = '2026-11-21 11:45:00';
        $submission->save();

        $this->get($url)->assertOk()
            ->assertSee('class="submission-detail__badge" data-detail-status="ditolak">Ditolak', false)
            ->assertSeeInOrder([
                'aria-label="Riwayat perubahan status"',
                '<strong>Diajukan</strong>',
                '21 Nov 2026, 08:15',
                '<strong>Sedang Diperiksa</strong>',
                '21 Nov 2026, 10:15',
                '<strong>Diverifikasi</strong>',
                '<strong>Ditolak</strong>',
            ], false)
            ->assertSee('21 Nov 2026, 11:45')
            ->assertDontSee('Dalam Proses')
            ->assertDontSee('Disetujui')
            ->assertDontSee('Catatan internal petugas')
            ->assertDontSee('Hasil internal petugas');
    }

    public function test_detail_handles_empty_relations_and_legacy_addresses(): void
    {
        $owner = $this->user();
        $submission = $this->pengajuan($owner, 'PLTS-DETAIL-EMPTY', 'diproses');
        DB::table('pengajuan_plts')->where('id', $submission->id)->update([
            'ringkasan_pengajuan' => '',
            'status_diperbarui_pada' => null,
        ]);
        $url = route('pengajuan.riwayat.show', $submission);

        $this->actingAs($owner)->get($url)->assertOk()
            ->assertSee('Tidak tersedia')
            ->assertSee('Dalam Proses')
            ->assertSee('Foto bukti tidak tersedia.')
            ->assertSee('Riwayat perubahan status belum tersedia.');

        $submission->lokasi()->create(['alamat_deskriptif' => 'Kampung Lama, Blok B']);
        $this->get($url)->assertOk()->assertSee('Kampung Lama, Blok B')->assertSee('Tidak tersedia');
    }

    public function test_uploaded_photo_and_submission_receipt_still_work_before_opening_detail(): void
    {
        $owner = $this->user();
        $address = app(LocationCatalog::class)->address([
            'kabupaten' => '3205',
            'kecamatan' => 'Cikelet',
            'desa' => '32.05.30.2010',
            'alamat' => 'Kampung Uji',
        ]);
        $draft = $this->pengajuan($owner, 'DRAFT-REGRESSION', 'draft', $address);
        $disk = Storage::fake('r2');
        $disk->buildTemporaryUrlsUsing(fn ($path) => 'https://photos.example.test/'.$path);
        $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6l1sAAAAASUVORK5CYII=');

        $this->actingAs($owner)->post(route('pengajuan.kirim'), [
            'draft_id' => $draft->id,
            'pernyataan' => '1',
        ])->assertSessionHasErrors('photos');

        $this->post(route('pengajuan.bukti.store'), [
            'draft_id' => $draft->id,
            'photos' => [UploadedFile::fake()->createWithContent('bukti.png', $image)],
        ])->assertSessionHasNoErrors()->assertRedirect(route('pengajuan.bukti'));
        $photo = $draft->bukti()->sole();
        $disk->assertExists($photo->path_file);

        $this->get(route('pengajuan.ringkasan'))->assertOk()->assertSee('Periksa sebelum mengirim');
        $receiptUrl = route('pengajuan.terkirim', $draft);
        $this->post(route('pengajuan.kirim'), [
            'draft_id' => $draft->id,
            'pernyataan' => '1',
        ])->assertRedirect($receiptUrl);
        $this->get($receiptUrl)->assertOk()->assertSee('Pengajuan berhasil dikirim')->assertSee($draft->fresh()->nomor_tiket);

        $this->get(route('pengajuan.riwayat.show', $draft))
            ->assertOk()
            ->assertSee('bukti.png')
            ->assertSee('Diajukan');
        $this->get(route('pengajuan.bukti.show', $photo))->assertRedirect('https://photos.example.test/'.$photo->path_file);
        $this->actingAs($this->user())->get(route('pengajuan.bukti.show', $photo))->assertNotFound();
    }
}
