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

class Pbi09SkenarioTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'app.key' => 'base64:'.base64_encode(str_repeat('p', 32)),
        ]);
        DB::purge('sqlite');
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
            $table->enum('status_sebelumnya', ['diajukan', 'diperiksa', 'diverifikasi', 'diproses', 'selesai', 'ditolak'])->nullable();
            $table->enum('status_baru', ['diajukan', 'diperiksa', 'diverifikasi', 'diproses', 'selesai', 'ditolak']);
            $table->string('jenis_tindakan', 50);
            $table->string('hasil_verifikasi', 50)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    private function user(): User
    {
        $user = new User([
            'name' => 'Pemohon PBI-09',
            'email' => uniqid('pbi09-', true).'@example.test',
            'password' => 'password-uji',
        ]);
        $user->role = 'masyarakat';
        $user->save();

        return $user;
    }

    private function draft(User $user, int $photos = 0): PengajuanPlts
    {
        $draft = new PengajuanPlts;
        $draft->user_id = $user->id;
        $draft->nomor_tiket = 'DRAFT-'.str()->uuid();
        $draft->status_terkini = 'draft';
        $draft->ringkasan_pengajuan = 'Lokasi Awassagara, Kabupaten Garut';
        $draft->save();

        $draft->lokasi()->create([
            'alamat_deskriptif' => app(LocationCatalog::class)->address([
                'kabupaten' => '3205',
                'kecamatan' => 'Cikelet',
                'desa' => '32.05.30.2010',
                'alamat' => 'Kampung Uji, RT 01',
            ]),
        ]);

        for ($index = 0; $index < $photos; $index++) {
            $draft->bukti()->create([
                'nama_file_asli' => "bukti-$index.png",
                'path_file' => "bukti-pengajuan/$draft->id/$index.png",
                'mime_type' => 'image/png',
                'ukuran_byte' => 1024,
            ]);
        }

        return $draft;
    }

    private function png(string $name, int $bytes = 1024): UploadedFile
    {
        $content = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6l1sAAAAASUVORK5CYII=');
        $file = UploadedFile::fake()->createWithContent($name, $content);
        $file->sizeToReport = $bytes;

        return $file;
    }

    private function submit(PengajuanPlts $draft)
    {
        return $this->postJson('/pengajuan/lokasi/kirim', [
            'draft_id' => $draft->id,
            'pernyataan' => 1,
        ]);
    }

    public function test_tc09_01_data_valid_dan_lima_foto_berhasil_dikirim(): void
    {
        Storage::fake('r2');
        $owner = $this->user();
        $draft = $this->draft($owner);
        $this->actingAs($owner);

        $photos = [$this->png('bukti-batas.png', 5_120_000)];
        foreach (range(1, 4) as $index) {
            $photos[] = $this->png("bukti-$index.png");
        }

        $this->post('/pengajuan/lokasi/bukti', ['draft_id' => $draft->id, 'photos' => $photos])
            ->assertRedirect('/pengajuan/lokasi/bukti')
            ->assertSessionHas('evidence_saved', '5 foto bukti berhasil disimpan.');

        $this->assertDatabaseCount('bukti_pengajuan', 5);
        $this->assertDatabaseHas('bukti_pengajuan', [
            'pengajuan_id' => $draft->id,
            'nama_file_asli' => 'bukti-batas.png',
            'ukuran_byte' => 5_120_000,
        ]);

        $draft->lokasi->update(['latitude' => -7.5812, 'longitude' => 107.6984]);
        $this->actingAs($owner)->submit($draft)->assertRedirect('/pengajuan/lokasi/terkirim/'.$draft->id);

        $this->assertSame('diajukan', $draft->fresh()->status_terkini);
        $this->assertDatabaseCount('riwayat_status_pengajuan', 1);
    }

    public function test_tc09_02_data_utama_kosong_ditolak_dan_draft_tetap_tersimpan(): void
    {
        $owner = $this->user();
        $draft = $this->draft($owner, 1);
        $ticket = $draft->nomor_tiket;
        $draft->ringkasan_pengajuan = '';
        $draft->save();

        $this->actingAs($owner)->submit($draft)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('data_pengajuan');

        $this->assertSame('draft', $draft->fresh()->status_terkini);
        $this->assertSame($ticket, $draft->fresh()->nomor_tiket);
        $this->assertDatabaseCount('riwayat_status_pengajuan', 0);
    }

    public function test_tc09_03_lokasi_tidak_valid_ditolak_dan_draft_tetap_tersimpan(): void
    {
        $owner = $this->user();
        $draft = $this->draft($owner, 1);
        $draft->lokasi->update(['alamat_deskriptif' => 'Alamat tanpa data wilayah']);

        $this->actingAs($owner)->submit($draft)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lokasi');

        $this->assertSame('draft', $draft->fresh()->status_terkini);
        $this->assertDatabaseCount('riwayat_status_pengajuan', 0);
    }

    public function test_tc09_04_pengajuan_tanpa_foto_bukti_ditolak(): void
    {
        $owner = $this->user();
        $draft = $this->draft($owner);

        $this->actingAs($owner)->submit($draft)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('photos');

        $this->assertSame('draft', $draft->fresh()->status_terkini);
        $this->assertDatabaseCount('riwayat_status_pengajuan', 0);
    }

    public function test_tc09_05_format_foto_tidak_didukung_dan_ukuran_terlalu_besar_ditolak(): void
    {
        Storage::fake('r2');
        $owner = $this->user();
        $draft = $this->draft($owner);
        $this->actingAs($owner);

        $this->postJson('/pengajuan/lokasi/bukti', [
            'draft_id' => $draft->id,
            'photos' => [UploadedFile::fake()->createWithContent('catatan.txt', 'bukan foto')],
        ])->assertUnprocessable()->assertJsonValidationErrors('photos.0');

        $this->postJson('/pengajuan/lokasi/bukti', [
            'draft_id' => $draft->id,
            'photos' => [$this->png('terlalu-besar.png', 5_120_001)],
        ])->assertUnprocessable()->assertJsonValidationErrors('photos.0');

        $this->assertDatabaseCount('bukti_pengajuan', 0);
        $this->assertSame('draft', $draft->fresh()->status_terkini);
        $this->assertSame([], Storage::disk('r2')->allFiles());
    }

    public function test_tc09_06_jumlah_foto_melebihi_lima_ditolak(): void
    {
        Storage::fake('r2');
        $owner = $this->user();
        $draft = $this->draft($owner);
        $photos = [];
        foreach (range(1, 6) as $index) {
            $photos[] = $this->png("bukti-$index.png");
        }

        $this->actingAs($owner)->postJson('/pengajuan/lokasi/bukti', [
            'draft_id' => $draft->id,
            'photos' => $photos,
        ])->assertUnprocessable()->assertJsonValidationErrors('photos');

        $this->assertDatabaseCount('bukti_pengajuan', 0);
        $this->assertSame('draft', $draft->fresh()->status_terkini);
        $this->assertSame([], Storage::disk('r2')->allFiles());
    }

    public function test_tc09_07_kedua_koordinat_boleh_dikosongkan(): void
    {
        $owner = $this->user();
        $draft = $this->draft($owner, 1);

        $this->actingAs($owner)->submit($draft)->assertRedirect('/pengajuan/lokasi/terkirim/'.$draft->id);

        $this->assertSame('diajukan', $draft->fresh()->status_terkini);
        $this->assertNull($draft->fresh()->lokasi->latitude);
        $this->assertNull($draft->fresh()->lokasi->longitude);
        $this->assertDatabaseCount('riwayat_status_pengajuan', 1);
    }

    public function test_tc09_08_satu_koordinat_saja_ditolak(): void
    {
        $owner = $this->user();
        $this->actingAs($owner);

        foreach ([['latitude' => -7.5, 'longitude' => null], ['latitude' => null, 'longitude' => 107.7]] as $coordinates) {
            $draft = $this->draft($owner, 1);
            $draft->lokasi->update($coordinates);

            $this->submit($draft)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('lokasi');
            $this->assertSame('draft', $draft->fresh()->status_terkini);
        }

        $this->assertDatabaseCount('riwayat_status_pengajuan', 0);
    }

    public function test_tc09_09_data_diperbaiki_dan_pengajuan_berhasil_dikirim_ulang(): void
    {
        Storage::fake('r2');
        $owner = $this->user();
        $draft = $this->draft($owner);
        $ticket = $draft->nomor_tiket;
        $draft->ringkasan_pengajuan = '';
        $draft->save();
        $draft->lokasi->update(['alamat_deskriptif' => 'Alamat tanpa wilayah']);
        $this->actingAs($owner);

        $this->submit($draft)->assertUnprocessable()
            ->assertJsonValidationErrors(['data_pengajuan', 'lokasi', 'photos']);
        $this->assertSame('draft', $draft->fresh()->status_terkini);

        $draft->ringkasan_pengajuan = 'Lokasi Awassagara, Kabupaten Garut';
        $draft->save();
        $draft->lokasi->update([
            'alamat_deskriptif' => app(LocationCatalog::class)->address([
                'kabupaten' => '3205',
                'kecamatan' => 'Cikelet',
                'desa' => '32.05.30.2010',
                'alamat' => 'Kampung Uji, RT 01',
            ]),
        ]);
        $this->post('/pengajuan/lokasi/bukti', [
            'draft_id' => $draft->id,
            'photos' => [$this->png('bukti-perbaikan.png')],
        ])->assertRedirect('/pengajuan/lokasi/bukti');

        $this->submit($draft)->assertRedirect('/pengajuan/lokasi/terkirim/'.$draft->id);

        $this->assertSame('diajukan', $draft->fresh()->status_terkini);
        $this->assertNotSame($ticket, $draft->fresh()->nomor_tiket);
        $this->assertDatabaseCount('riwayat_status_pengajuan', 1);
    }
}
