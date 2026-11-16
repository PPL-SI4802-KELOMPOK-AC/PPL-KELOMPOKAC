<?php

namespace Tests\Feature;

use App\Models\PengajuanPlts;
use App\Models\User;
use App\Services\Pengajuan\LocationCatalog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Pbi10SkenarioTest extends TestCase
{
    private int $userSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'app.key' => 'base64:'.base64_encode(str_repeat('r', 32)),
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
            $table->timestamp('status_diperbarui_pada')->useCurrent();
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
            $table->enum('status_sebelumnya', ['diajukan', 'diperiksa', 'diverifikasi', 'diproses', 'selesai', 'ditolak'])->nullable();
            $table->enum('status_baru', ['diajukan', 'diperiksa', 'diverifikasi', 'diproses', 'selesai', 'ditolak']);
            $table->string('jenis_tindakan', 50);
            $table->string('hasil_verifikasi', 50)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    private function user(string $role = 'masyarakat'): User
    {
        $user = new User([
            'name' => 'Pemohon PBI-10 '.$this->userSequence,
            'email' => 'pbi10-'.$this->userSequence.'@example.test',
            'password' => 'password-uji',
        ]);
        $user->role = $role;
        $user->save();
        $this->userSequence++;

        return $user;
    }

    private function pengajuan(
        User $owner,
        string $ticket,
        string $status = 'diajukan',
        ?string $address = null,
    ): PengajuanPlts {
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

    private function alamatValid(): string
    {
        return app(LocationCatalog::class)->address([
            'kabupaten' => '3205',
            'kecamatan' => 'Cikelet',
            'desa' => '32.05.30.2010',
            'alamat' => 'Kampung Uji, RT 01',
        ]);
    }

    private function catatanPengajuanBaru(PengajuanPlts $pengajuan, User $actor, string $createdAt): void
    {
        DB::table('riwayat_status_pengajuan')->insert([
            'pengajuan_id' => $pengajuan->id,
            'diubah_oleh' => $actor->id,
            'status_sebelumnya' => null,
            'status_baru' => 'diajukan',
            'jenis_tindakan' => 'pengajuan_baru',
            'catatan' => 'Catatan awal uji PBI-10.',
            'created_at' => $createdAt,
        ]);
    }

    public function test_tc10_01_masyarakat_dapat_melihat_riwayat_pengajuan_terkirimnya(): void
    {
        $owner = $this->user();
        $this->pengajuan($owner, 'PLTS-PBI10-01');

        $response = $this->actingAs($owner)->get(route('pengajuan.riwayat'))
            ->assertOk()
            ->assertSee('PLTS-PBI10-01')
            ->assertSee('Riwayat Pengajuan Anda');

        $this->assertSame(1, $response->viewData('summary')['total']);
    }

    public function test_tc10_02_pengajuan_pengguna_lain_tidak_ditampilkan(): void
    {
        $owner = $this->user();
        $other = $this->user();
        $this->pengajuan($owner, 'PLTS-PBI10-MILIK-SAYA');
        $this->pengajuan($other, 'PLTS-PBI10-MILIK-ORANG-LAIN');

        $this->actingAs($owner)->get(route('pengajuan.riwayat'))
            ->assertOk()
            ->assertSee('PLTS-PBI10-MILIK-SAYA')
            ->assertDontSee('PLTS-PBI10-MILIK-ORANG-LAIN');
    }

    public function test_tc10_03_draft_tidak_muncul_dan_tidak_dihitung(): void
    {
        $owner = $this->user();
        $submitted = $this->pengajuan($owner, 'PLTS-PBI10-TERKIRIM');
        $this->pengajuan($owner, 'DRAFT-PBI10-01', 'draft');
        $this->catatanPengajuanBaru($submitted, $owner, '2026-10-28 09:30:00');

        $response = $this->actingAs($owner)->get(route('pengajuan.riwayat'))
            ->assertOk()
            ->assertSee('PLTS-PBI10-TERKIRIM')
            ->assertDontSee('DRAFT-PBI10-01');

        $this->assertSame(1, $response->viewData('summary')['total']);
    }

    public function test_tc10_04_tanggal_diambil_dari_catatan_awal_pengajuan(): void
    {
        $owner = $this->user();
        $pengajuan = $this->pengajuan($owner, 'PLTS-PBI10-TANGGAL');
        $this->catatanPengajuanBaru($pengajuan, $owner, '2026-10-20 09:30:00');
        $this->catatanPengajuanBaru($pengajuan, $owner, '2026-10-28 09:30:00');

        $this->actingAs($owner)->get(route('pengajuan.riwayat'))
            ->assertOk()
            ->assertSee('20 Okt 2026')
            ->assertDontSee('28 Okt 2026')
            ->assertDontSee('2020');
    }

    public function test_tc10_05_pengajuan_tanpa_catatan_awal_memakai_teks_tanggal_tidak_tersedia(): void
    {
        $owner = $this->user();
        $dated = $this->pengajuan($owner, 'PLTS-PBI10-ADA-TANGGAL');
        $this->pengajuan($owner, 'PLTS-PBI10-TANPA-TANGGAL');
        $this->catatanPengajuanBaru($dated, $owner, '2026-10-20 09:30:00');

        $this->actingAs($owner)->get(route('pengajuan.riwayat'))
            ->assertOk()
            ->assertSeeInOrder(['PLTS-PBI10-ADA-TANGGAL', 'PLTS-PBI10-TANPA-TANGGAL', 'Tanggal tidak tersedia']);
    }

    public function test_tc10_06_lokasi_valid_menampilkan_nama_desa_dan_kabupaten(): void
    {
        $owner = $this->user();
        $this->pengajuan($owner, 'PLTS-PBI10-LOKASI-VALID', 'diajukan', $this->alamatValid());

        $this->actingAs($owner)->get(route('pengajuan.riwayat'))
            ->assertOk()
            ->assertSee('Awassagara')
            ->assertSee('Kabupaten Garut');
    }

    public function test_tc10_07_lokasi_yang_tidak_dapat_diuraikan_menampilkan_alamat_tersimpan(): void
    {
        $owner = $this->user();
        $address = 'Alamat lama dengan format wilayah yang tidak dikenal';
        $this->pengajuan($owner, 'PLTS-PBI10-LOKASI-FALLBACK', 'diajukan', $address);

        $this->actingAs($owner)->get(route('pengajuan.riwayat'))
            ->assertOk()
            ->assertSee($address)
            ->assertSee('Tidak tersedia');
    }

    public function test_tc10_08_pencarian_tiket_dan_alamat_hanya_mencocokkan_pengajuan_pemiliknya(): void
    {
        $owner = $this->user();
        $other = $this->user();
        $this->pengajuan($owner, 'PLTS-PBI10-CARI-TIKET');
        $this->pengajuan($other, 'PLTS-PBI10-CARI-TIKET-ORANG-LAIN');

        $this->actingAs($owner)->get(route('pengajuan.riwayat', ['q' => 'CARI-TIKET']))
            ->assertOk()
            ->assertSee('PLTS-PBI10-CARI-TIKET')
            ->assertDontSee('PLTS-PBI10-CARI-TIKET-ORANG-LAIN');

        $this->pengajuan($owner, 'PLTS-PBI10-DESA-COCOK', 'diajukan', 'Desa Sukamaju, Kecamatan Uji');
        $this->pengajuan($owner, 'PLTS-PBI10-DESA-LAIN', 'diajukan', 'Desa Mekarsari, Kecamatan Uji');
        $this->pengajuan($other, 'PLTS-PBI10-DESA-ORANG-LAIN', 'diajukan', 'Desa Sukamaju, Kecamatan Uji');

        $this->get(route('pengajuan.riwayat', ['q' => 'Sukamaju']))
            ->assertOk()
            ->assertSee('PLTS-PBI10-DESA-COCOK')
            ->assertDontSee('PLTS-PBI10-DESA-LAIN')
            ->assertDontSee('PLTS-PBI10-DESA-ORANG-LAIN');
    }

    public function test_tc10_09_filter_status_hanya_menampilkan_status_yang_dipilih(): void
    {
        $owner = $this->user();
        $other = $this->user();
        $this->pengajuan($owner, 'PLTS-PBI10-STATUS-DIAJUKAN', 'diajukan');
        $this->pengajuan($owner, 'PLTS-PBI10-STATUS-SELESAI', 'selesai');
        $this->pengajuan($other, 'PLTS-PBI10-STATUS-ORANG-LAIN', 'diajukan');
        $this->pengajuan($owner, 'DRAFT-PBI10-STATUS', 'draft');

        $this->actingAs($owner)->get(route('pengajuan.riwayat', ['status' => 'diajukan']))
            ->assertOk()
            ->assertSee('PLTS-PBI10-STATUS-DIAJUKAN')
            ->assertDontSee('PLTS-PBI10-STATUS-SELESAI')
            ->assertDontSee('PLTS-PBI10-STATUS-ORANG-LAIN')
            ->assertDontSee('DRAFT-PBI10-STATUS');
    }

    public function test_tc10_10_filter_periode_memakai_tanggal_awal_dan_mengabaikan_pengajuan_tanpa_tanggal(): void
    {
        $owner = $this->user();
        $inPeriod = $this->pengajuan($owner, 'PLTS-PBI10-PERIODE-MASUK');
        $otherPeriod = $this->pengajuan($owner, 'PLTS-PBI10-PERIODE-KELUAR');
        $this->pengajuan($owner, 'PLTS-PBI10-PERIODE-TANPA-TANGGAL');
        $this->catatanPengajuanBaru($inPeriod, $owner, '2026-09-28 09:30:00');
        $this->catatanPengajuanBaru($inPeriod, $owner, '2026-10-02 09:30:00');
        $this->catatanPengajuanBaru($otherPeriod, $owner, '2026-10-01 09:30:00');

        $this->actingAs($owner)->get(route('pengajuan.riwayat', ['periode' => '2026-09']))
            ->assertOk()
            ->assertSee('PLTS-PBI10-PERIODE-MASUK')
            ->assertDontSee('PLTS-PBI10-PERIODE-KELUAR')
            ->assertDontSee('PLTS-PBI10-PERIODE-TANPA-TANGGAL');
    }

    public function test_tc10_11_pencarian_dan_filter_dipertahankan_saat_berpindah_halaman(): void
    {
        $owner = $this->user();
        $other = $this->user();

        for ($index = 1; $index <= 12; $index++) {
            $pengajuan = $this->pengajuan($owner, sprintf('PLTS-PBI10-HAL-%02d', $index), 'diajukan', 'Desa Sukamaju');
            $this->catatanPengajuanBaru($pengajuan, $owner, sprintf('2026-09-%02d 09:30:00', $index));
        }

        $wrongStatus = $this->pengajuan($owner, 'PLTS-PBI10-HAL-STATUS-LAIN', 'selesai', 'Desa Sukamaju');
        $this->catatanPengajuanBaru($wrongStatus, $owner, '2026-09-20 09:30:00');
        $foreign = $this->pengajuan($other, 'PLTS-PBI10-HAL-ORANG-LAIN', 'diajukan', 'Desa Sukamaju');
        $this->catatanPengajuanBaru($foreign, $other, '2026-09-21 09:30:00');

        $filters = ['q' => 'Sukamaju', 'status' => 'diajukan', 'periode' => '2026-09'];
        $firstPage = $this->actingAs($owner)->get(route('pengajuan.riwayat', $filters))
            ->assertOk()
            ->assertSee('PLTS-PBI10-HAL-12')
            ->assertDontSee('PLTS-PBI10-HAL-02');
        $this->assertSame(12, $firstPage->viewData('pengajuans')->total());

        $secondPageUrl = $firstPage->viewData('pengajuans')->url(2);
        parse_str((string) parse_url($secondPageUrl, PHP_URL_QUERY), $query);
        $this->assertSame('Sukamaju', $query['q'] ?? null);
        $this->assertSame('diajukan', $query['status'] ?? null);
        $this->assertSame('2026-09', $query['periode'] ?? null);
        $this->assertSame('2', (string) ($query['page'] ?? ''));

        $this->get($secondPageUrl)
            ->assertOk()
            ->assertSee('PLTS-PBI10-HAL-02')
            ->assertDontSee('PLTS-PBI10-HAL-ORANG-LAIN')
            ->assertDontSee('PLTS-PBI10-HAL-STATUS-LAIN');
    }

    public function test_tc10_12_paginasi_menampilkan_nomor_dan_status_tombol_batas_halaman(): void
    {
        $owner = $this->user();
        for ($index = 1; $index <= 21; $index++) {
            $pengajuan = $this->pengajuan($owner, sprintf('PLTS-PBI10-PAG-%02d', $index));
            $this->catatanPengajuanBaru($pengajuan, $owner, sprintf('2026-10-%02d 09:30:00', $index));
        }

        $firstPage = $this->actingAs($owner)->get(route('pengajuan.riwayat'))
            ->assertOk()
            ->assertSee('aria-disabled="true">Sebelumnya', false)
            ->assertSee('rel="next"', false);
        $this->assertSame(1, $firstPage->viewData('pengajuans')->currentPage());
        $this->assertSame(10, $firstPage->viewData('pengajuans')->lastItem());

        $middlePage = $this->get(route('pengajuan.riwayat', ['page' => 2]))
            ->assertOk()
            ->assertSee('aria-current="page">2', false)
            ->assertSee('rel="prev"', false)
            ->assertSee('rel="next"', false);
        $this->assertSame(2, $middlePage->viewData('pengajuans')->currentPage());

        $lastPage = $this->get(route('pengajuan.riwayat', ['page' => 3]))
            ->assertOk()
            ->assertSee('rel="prev"', false)
            ->assertSee('aria-disabled="true">Berikutnya', false);
        $this->assertSame(3, $lastPage->viewData('pengajuans')->currentPage());
        $this->assertSame(21, $lastPage->viewData('pengajuans')->lastItem());
    }

    public function test_tc10_13_guest_diarahkan_ke_halaman_login(): void
    {
        $this->get(route('pengajuan.riwayat'))
            ->assertRedirect(route('login'));
    }

    public function test_tc10_14_pengguna_dengan_role_selain_masyarakat_ditolak(): void
    {
        foreach (['pemerintah', 'admin'] as $role) {
            $this->actingAs($this->user($role))
                ->get(route('pengajuan.riwayat'))
                ->assertForbidden();
        }
    }

    public function test_tc10_15_aksi_lihat_detail_membuka_tanda_terima_tanpa_timeline(): void
    {
        $owner = $this->user();
        $pengajuan = $this->pengajuan($owner, 'PLTS-PBI10-DETAIL');

        $this->actingAs($owner)->get(route('pengajuan.riwayat'))
            ->assertOk()
            ->assertSee('Lihat detail')
            ->assertSee(route('pengajuan.terkirim', ['pengajuan' => $pengajuan->id]), false)
            ->assertDontSee('Timeline');

        $this->get(route('pengajuan.terkirim', ['pengajuan' => $pengajuan->id]))
            ->assertOk()
            ->assertSee('Pengajuan berhasil dikirim')
            ->assertSee('PLTS-PBI10-DETAIL')
            ->assertDontSee('Timeline');
    }
}
