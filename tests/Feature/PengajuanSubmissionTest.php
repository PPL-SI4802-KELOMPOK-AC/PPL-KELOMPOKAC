<?php

namespace Tests\Feature;

use App\Models\PengajuanPlts;
use App\Models\User;
use App\Services\Pengajuan\LocationCatalog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PengajuanSubmissionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'app.key' => 'base64:'.base64_encode(str_repeat('s', 32))]);
        DB::purge('sqlite');
        $this->withoutVite();
        Schema::create('users', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('email')->unique();
            $table->string('password'); $table->string('role'); $table->timestamps();
        });
        Schema::create('pengajuan_plts', function (Blueprint $table) {
            $table->id(); $table->string('nomor_tiket', 50)->unique(); $table->foreignId('user_id')->constrained('users');
            $table->unsignedBigInteger('wilayah_id')->nullable(); $table->string('ringkasan_pengajuan');
            $table->enum('status_terkini', ['draft', 'diajukan', 'diperiksa', 'diverifikasi', 'diproses', 'selesai', 'ditolak']);
            $table->timestamp('status_diperbarui_pada')->useCurrent();
        });
        Schema::create('lokasi_pengajuan', function (Blueprint $table) {
            $table->id(); $table->foreignId('pengajuan_id')->unique()->constrained('pengajuan_plts');
            $table->text('alamat_deskriptif'); $table->decimal('latitude', 10, 7)->nullable(); $table->decimal('longitude', 11, 7)->nullable();
        });
        Schema::create('bukti_pengajuan', function (Blueprint $table) {
            $table->id(); $table->foreignId('pengajuan_id')->constrained('pengajuan_plts');
            $table->string('nama_file_asli', 255); $table->string('path_file', 512)->unique();
            $table->string('mime_type', 100); $table->unsignedBigInteger('ukuran_byte'); $table->string('keterangan', 255)->nullable();
        });
        Schema::create('riwayat_status_pengajuan', function (Blueprint $table) {
            $table->id(); $table->foreignId('pengajuan_id')->constrained('pengajuan_plts');
            $table->foreignId('diubah_oleh')->constrained('users');
            $statuses = ['diajukan', 'diperiksa', 'diverifikasi', 'diproses', 'selesai', 'ditolak'];
            $table->enum('status_sebelumnya', $statuses)->nullable(); $table->enum('status_baru', $statuses);
            $table->string('jenis_tindakan', 50); $table->string('hasil_verifikasi', 50)->nullable();
            $table->text('catatan')->nullable(); $table->timestamp('created_at')->useCurrent();
        });
    }

    private function user(string $role = 'masyarakat'): User
    {
        $user = new User(['name' => 'Pengusul <uji>', 'email' => uniqid().'@example.test', 'password' => 'password-uji']);
        $user->role = $role;
        $user->save();

        return $user;
    }

    private function draft(User $user, int $photos = 1, bool $location = true): PengajuanPlts
    {
        $draft = new PengajuanPlts;
        $draft->user_id = $user->id;
        $draft->nomor_tiket = 'DRAFT-'.str()->uuid();
        $draft->status_terkini = 'draft';
        $draft->ringkasan_pengajuan = 'Lokasi Awassagara, Kabupaten Garut';
        $draft->save();
        if ($location) {
            $draft->lokasi()->create(['alamat_deskriptif' => app(LocationCatalog::class)->address([
                'kabupaten' => '3205', 'kecamatan' => 'Cikelet', 'desa' => '32.05.30.2010', 'alamat' => 'Kampung Uji, RT 01',
            ])]);
        }
        for ($i = 0; $i < $photos; $i++) {
            $draft->bukti()->create(['nama_file_asli' => "bukti-$i.png", 'path_file' => "bukti-pengajuan/$draft->id/$i.png",
                'mime_type' => 'image/png', 'ukuran_byte' => 100]);
        }

        return $draft;
    }

    public function test_only_masyarakat_can_review_submit_and_open_receipts(): void
    {
        $this->get('/pengajuan/lokasi/ringkasan')->assertRedirect('/login');
        $this->postJson('/pengajuan/lokasi/kirim', [])->assertUnauthorized();
        $this->get('/pengajuan/lokasi/terkirim/1')->assertRedirect('/login');
        foreach (['admin', 'pemerintah'] as $role) {
            $this->actingAs($this->user($role))->get('/pengajuan/lokasi/ringkasan')->assertForbidden();
            $this->postJson('/pengajuan/lokasi/kirim', ['draft_id' => 1, 'pernyataan' => 1])->assertForbidden();
            $this->get('/pengajuan/lokasi/terkirim/1')->assertForbidden();
        }
    }

    public function test_review_uses_saved_data_of_owner_and_allows_optional_coordinates(): void
    {
        $owner = $this->user();
        $draft = $this->draft($owner, 2);
        $this->draft($this->user());
        $this->actingAs($owner)->get('/pengajuan/lokasi/ringkasan')->assertOk()
            ->assertViewHas('draft', fn ($value) => $value->id === $draft->id)
            ->assertSee('Periksa sebelum mengirim')->assertSee('Awassagara')->assertSee('Kampung Uji, RT 01')
            ->assertSee('Tidak diisi (opsional)')->assertSee('bukti-1.png')->assertSee('Pengusul &lt;uji&gt;', false)
            ->assertDontSee('data-location-form', false)->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame('draft', $draft->fresh()->status_terkini);
        $this->assertDatabaseCount('riwayat_status_pengajuan', 0);
    }

    public function test_review_redirects_when_location_or_saved_evidence_is_missing(): void
    {
        $owner = $this->user();
        $this->actingAs($owner)->get('/pengajuan/lokasi/ringkasan')->assertRedirect('/pengajuan/lokasi');
        $draft = $this->draft($owner, 0, false);
        $this->get('/pengajuan/lokasi/ringkasan')->assertRedirect('/pengajuan/lokasi');
        $draft->lokasi()->create(['alamat_deskriptif' => 'Alamat tanpa wilayah']);
        $this->get('/pengajuan/lokasi/ringkasan')->assertRedirect('/pengajuan/lokasi');
        $this->draft($owner, 0);
        $this->get('/pengajuan/lokasi/ringkasan')->assertRedirect('/pengajuan/lokasi/bukti')->assertSessionHasErrors('photos');
    }

    public function test_declaration_must_be_accepted_before_submit(): void
    {
        $owner = $this->user();
        $draft = $this->draft($owner);
        $this->actingAs($owner);
        foreach ([[], ['pernyataan' => false], ['pernyataan' => 'no']] as $declaration) {
            $this->postJson('/pengajuan/lokasi/kirim', ['draft_id' => $draft->id] + $declaration)
                ->assertUnprocessable()->assertJsonValidationErrors('pernyataan');
        }
        $this->assertSame('draft', $draft->fresh()->status_terkini);
        $this->assertDatabaseCount('riwayat_status_pengajuan', 0);
    }

    public function test_submit_generates_one_ticket_and_one_initial_status_even_on_retry(): void
    {
        $owner = $this->user();
        $draft = $this->draft($owner);
        $input = ['draft_id' => $draft->id, 'pernyataan' => 1, 'status_terkini' => 'selesai', 'user_id' => 999];
        $receipt = '/pengajuan/lokasi/terkirim/'.$draft->id;
        $this->actingAs($owner)->post('/pengajuan/lokasi/kirim', $input)->assertRedirect($receipt);
        $submitted = $draft->fresh();
        $this->assertSame('diajukan', $submitted->status_terkini);
        $this->assertSame($owner->id, $submitted->user_id);
        $this->assertStringStartsWith('PLTS-', $submitted->nomor_tiket);
        $this->assertLessThanOrEqual(50, strlen($submitted->nomor_tiket));
        $this->assertNotNull($submitted->status_diperbarui_pada);
        $this->assertDatabaseHas('riwayat_status_pengajuan', ['pengajuan_id' => $draft->id, 'diubah_oleh' => $owner->id,
            'status_sebelumnya' => null, 'status_baru' => 'diajukan', 'jenis_tindakan' => 'pengajuan_baru']);
        $this->post('/pengajuan/lokasi/kirim', $input)->assertRedirect($receipt);
        $this->assertSame($submitted->nomor_tiket, $draft->fresh()->nomor_tiket);
        $this->assertDatabaseCount('pengajuan_plts', 1);
        $this->assertDatabaseCount('riwayat_status_pengajuan', 1);
        $this->assertDatabaseCount('lokasi_pengajuan', 1);
        $this->assertDatabaseCount('bukti_pengajuan', 1);
        $this->get($receipt)->assertOk()->assertSee($submitted->nomor_tiket)->assertSee('Diajukan');
        $this->get('/pengajuan/lokasi/bukti')->assertRedirect('/pengajuan/lokasi');

        Storage::fake('r2');
        Storage::disk('r2')->buildTemporaryUrlsUsing(fn ($path) => 'https://example.test/'.$path);
        $this->get('/pengajuan/lokasi/bukti/'.$draft->bukti->first()->id)->assertRedirect();
    }

    public function test_foreign_drafts_and_receipts_are_not_accessible(): void
    {
        $owner = $this->user();
        $draft = $this->draft($owner);
        $this->actingAs($owner)->get('/pengajuan/lokasi/terkirim/'.$draft->id)->assertNotFound();
        $other = $this->user();
        $this->actingAs($other)->post('/pengajuan/lokasi/kirim', ['draft_id' => $draft->id, 'pernyataan' => 1])->assertNotFound();
        $this->actingAs($owner)->post('/pengajuan/lokasi/kirim', ['draft_id' => $draft->id, 'pernyataan' => 1])->assertRedirect();
        $this->actingAs($other)->get('/pengajuan/lokasi/terkirim/'.$draft->id)->assertNotFound();
        $this->get('/pengajuan/lokasi/bukti/'.$draft->bukti->first()->id)->assertNotFound();
    }

    public function test_submit_rechecks_saved_location_and_photo_count(): void
    {
        $owner = $this->user();
        $this->actingAs($owner);
        foreach ([[0, true], [1, false], [6, true]] as [$photos, $location]) {
            $draft = $this->draft($owner, $photos, $location);
            $ticket = $draft->nomor_tiket;
            $this->postJson('/pengajuan/lokasi/kirim', ['draft_id' => $draft->id, 'pernyataan' => 1])->assertUnprocessable();
            $this->assertSame('draft', $draft->fresh()->status_terkini);
            $this->assertSame($ticket, $draft->fresh()->nomor_tiket);
        }
        $this->assertDatabaseCount('riwayat_status_pengajuan', 0);
    }

    public function test_failed_status_insert_rolls_back_ticket_and_submission(): void
    {
        $owner = $this->user();
        $draft = $this->draft($owner);
        $ticket = $draft->nomor_tiket;
        DB::statement("CREATE TRIGGER fail_status BEFORE INSERT ON riwayat_status_pengajuan BEGIN SELECT RAISE(FAIL, 'history unavailable'); END");
        $this->actingAs($owner)->post('/pengajuan/lokasi/kirim', ['draft_id' => $draft->id, 'pernyataan' => 1])
            ->assertStatus(503)->assertDontSee('history unavailable');
        $this->assertSame('draft', $draft->fresh()->status_terkini);
        $this->assertSame($ticket, $draft->fresh()->nomor_tiket);
        $this->assertDatabaseCount('riwayat_status_pengajuan', 0);
    }
}
