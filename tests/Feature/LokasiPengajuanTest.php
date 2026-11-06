<?php

namespace Tests\Feature;

use App\Models\PengajuanPlts;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LokasiPengajuanTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Database tersendiri di memori. Tidak menjalankan migrasi atau menulis TiDB.
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'app.key' => 'base64:'.base64_encode(str_repeat('l', 32))]);
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
            $table->enum('status_terkini', ['diajukan', 'diperiksa', 'diverifikasi', 'diproses', 'selesai', 'ditolak', 'draft'])->default('diajukan');
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
    }

    private function user(string $role = 'masyarakat'): User
    {
        $user = new User(['name' => 'Pemohon Uji', 'email' => uniqid().'@example.test', 'password' => 'password-uji']);
        $user->role = $role;
        $user->save();

        return $user;
    }

    private function input(array $overrides = []): array
    {
        return array_merge(['provinsi' => 'Jawa Barat', 'kabupaten' => '3205', 'kecamatan' => 'Cikelet',
            'desa' => '32.05.30.2010', 'alamat' => 'Kampung Uji, RT 03 / RW 02',
            'latitude' => '-7,5812', 'longitude' => '107,6984'], $overrides);
    }

    private function photo(string $name = 'bukti.png', ?int $kilobytes = null): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6l1sAAAAASUVORK5CYII=');
        $file = UploadedFile::fake()->createWithContent($name, $png);

        return $kilobytes === null ? $file : $file->size($kilobytes);
    }

    public function test_only_masyarakat_can_open_choices_or_save_locations(): void
    {
        $this->get('/pengajuan/lokasi')->assertRedirect('/login');
        $this->postJson('/pengajuan/lokasi', $this->input())->assertUnauthorized();
        $this->getJson('/pengajuan/lokasi/administrasi?kabupaten=3205')->assertUnauthorized();
        foreach (['pemerintah', 'admin'] as $role) {
            $this->actingAs($this->user($role));
            $this->get('/pengajuan/lokasi')->assertForbidden();
            $this->postJson('/pengajuan/lokasi', $this->input())->assertForbidden();
            $this->getJson('/pengajuan/lokasi/administrasi?kabupaten=3205')->assertForbidden();
        }
        $this->assertDatabaseCount('pengajuan_plts', 0);
    }

    public function test_save_and_reopen_location_with_decimal_commas_and_same_draft(): void
    {
        $owner = $this->user();
        $this->actingAs($owner)->post('/pengajuan/lokasi', $this->input(['user_id' => 999, 'status_terkini' => 'diajukan']))
            ->assertRedirect('/pengajuan/lokasi')->assertSessionHas('location_saved');
        $draft = PengajuanPlts::firstOrFail();
        $this->assertSame($owner->id, $draft->user_id);
        $this->assertSame('draft', $draft->status_terkini);
        $this->assertNull($draft->wilayah_id);
        $this->assertStringStartsWith('DRAFT-', $draft->nomor_tiket);
        $this->assertDatabaseHas('lokasi_pengajuan', ['pengajuan_id' => $draft->id,
            'alamat_deskriptif' => 'Kampung Uji, RT 03 / RW 02, Awassagara, Cikelet, Kabupaten Garut, Jawa Barat',
            'latitude' => -7.5812, 'longitude' => 107.6984]);
        $this->get('/pengajuan/lokasi')->assertOk()->assertViewHas('values', fn ($values) => $values['kabupaten'] === '3205' && $values['kecamatan'] === 'Cikelet'
            && $values['desa'] === '32.05.30.2010' && $values['alamat'] === 'Kampung Uji, RT 03 / RW 02');
        $ticket = $draft->nomor_tiket;
        // Retry permintaan awal tidak boleh membuat pengajuan/lokasi kedua.
        $this->post('/pengajuan/lokasi', $this->input(['alamat' => 'Alamat revisi']))->assertRedirect();
        $this->assertDatabaseCount('pengajuan_plts', 1);
        $this->assertDatabaseCount('lokasi_pengajuan', 1);
        $this->assertSame($ticket, $draft->fresh()->nomor_tiket);
        $this->assertStringStartsWith('Alamat revisi,', $draft->fresh()->lokasi->alamat_deskriptif);
    }

    public function test_invalid_location_is_rejected_before_creating_a_draft(): void
    {
        $this->actingAs($this->user());
        foreach ([['alamat' => ''], ['latitude' => ''], ['longitude' => ''], ['longitude' => 'bukan angka'],
            ['latitude' => '-8.8', 'longitude' => '115.2'], ['kecamatan' => 'Kecamatan berbeda'],
            ['kabupaten' => '3201'], ['desa' => 'desa-palsu']] as $input) {
            $this->postJson('/pengajuan/lokasi', $this->input($input))->assertUnprocessable();
        }
        $this->assertDatabaseCount('pengajuan_plts', 0);
        $this->assertDatabaseCount('lokasi_pengajuan', 0);
    }

    public function test_coordinates_can_be_omitted_added_and_removed_from_same_draft(): void
    {
        $this->actingAs($this->user());
        $input = $this->input();
        unset($input['latitude'], $input['longitude']);
        $this->post('/pengajuan/lokasi', $input)->assertRedirect();
        $draft = PengajuanPlts::firstOrFail();
        $this->assertDatabaseHas('lokasi_pengajuan', ['pengajuan_id' => $draft->id, 'latitude' => null, 'longitude' => null]);
        $this->get('/pengajuan/lokasi')->assertOk()->assertViewHas('values', fn ($values) => $values['latitude'] === '' && $values['longitude'] === '');
        $this->post('/pengajuan/lokasi', $this->input(['draft_id' => $draft->id]))->assertRedirect();
        $this->assertDatabaseHas('lokasi_pengajuan', ['pengajuan_id' => $draft->id, 'latitude' => -7.5812, 'longitude' => 107.6984]);
        $this->post('/pengajuan/lokasi', $this->input(['draft_id' => $draft->id, 'latitude' => '', 'longitude' => '']))->assertRedirect();
        $this->assertDatabaseHas('lokasi_pengajuan', ['pengajuan_id' => $draft->id, 'latitude' => null, 'longitude' => null]);
        $this->assertDatabaseCount('pengajuan_plts', 1);
    }

    public function test_other_owners_and_submitted_records_cannot_be_edited(): void
    {
        $owner = $this->user();
        $other = $this->user();
        $this->actingAs($owner)->post('/pengajuan/lokasi', $this->input())->assertRedirect();
        $draft = PengajuanPlts::firstOrFail();
        $originalAddress = $draft->lokasi->alamat_deskriptif;
        $this->actingAs($other)->post('/pengajuan/lokasi', $this->input(['draft_id' => $draft->id]))->assertNotFound();
        $this->get('/pengajuan/lokasi')->assertViewHas('draft', null)->assertDontSee($originalAddress);
        $draft->status_terkini = 'diajukan';
        $draft->save();
        $this->actingAs($owner)->post('/pengajuan/lokasi', $this->input(['draft_id' => $draft->id]))->assertStatus(409);
        $this->assertSame($originalAddress, $draft->fresh()->lokasi->alamat_deskriptif);
        $this->assertDatabaseCount('pengajuan_plts', 1);
    }

    public function test_invalid_array_input_can_be_rendered_back_without_error(): void
    {
        $this->actingAs($this->user())->from('/pengajuan/lokasi')
            ->post('/pengajuan/lokasi', $this->input(['kabupaten' => ['3205']]))->assertRedirect('/pengajuan/lokasi');
        $this->get('/pengajuan/lokasi')->assertOk();
    }

    public function test_administrative_choices_are_local_reference_data(): void
    {
        $this->actingAs($this->user())->getJson('/pengajuan/lokasi/administrasi?kabupaten=3205')
            ->assertOk()->assertJsonFragment(['code' => '32.05.30.2010', 'name' => 'Awassagara', 'subdistrict' => 'Cikelet', 'district' => 'Garut']);
        $this->getJson('/pengajuan/lokasi/administrasi?kabupaten=9999')->assertUnprocessable();
    }

    public function test_owner_can_upload_multiple_valid_photos_to_their_draft(): void
    {
        Storage::fake('r2');
        $owner = $this->user();
        $this->actingAs($owner)->post('/pengajuan/lokasi', $this->input())->assertRedirect();
        $draft = PengajuanPlts::firstOrFail();
        $photos = [$this->photo('panel-lapuk.png', 100), $this->photo('atap.png', 120)];

        $this->post('/pengajuan/lokasi/bukti', ['draft_id' => $draft->id, 'photos' => $photos])
            ->assertRedirect('/pengajuan/lokasi/bukti')->assertSessionHas('evidence_saved', '2 foto bukti berhasil disimpan.');

        $this->assertDatabaseCount('bukti_pengajuan', 2);
        foreach ($draft->fresh()->bukti as $evidence) {
            $this->assertSame($draft->id, $evidence->pengajuan_id);
            $this->assertContains($evidence->mime_type, ['image/jpeg', 'image/png']);
            $this->assertGreaterThan(0, $evidence->ukuran_byte);
            Storage::disk('r2')->assertExists($evidence->path_file);
        }
        $this->get('/pengajuan/lokasi/bukti')->assertOk()->assertSee('panel-lapuk.png')->assertSee('atap.png');
    }

    public function test_location_and_evidence_are_separate_steps_with_a_saved_location_required(): void
    {
        $this->get('/pengajuan/lokasi/bukti')->assertRedirect('/login');
        $owner = $this->user();
        $this->actingAs($owner)->get('/pengajuan/lokasi/bukti')
            ->assertRedirect('/pengajuan/lokasi')->assertSessionHas('location_required');

        $this->post('/pengajuan/lokasi', $this->input(['action' => 'continue']))
            ->assertRedirect('/pengajuan/lokasi/bukti');
        $draft = PengajuanPlts::firstOrFail();
        $this->get('/pengajuan/lokasi')->assertOk()
            ->assertSee('Tentukan lokasi pengajuan')->assertSee('data-location-form', false)
            ->assertDontSee('data-evidence-upload', false)->assertDontSee('data-location-editor', false);
        $this->get('/pengajuan/lokasi/bukti')->assertOk()
            ->assertSee('Tunjukkan kondisi di lapangan')->assertSee('data-evidence-upload', false)
            ->assertDontSee('data-location-form', false)->assertSee($draft->lokasi->alamat_deskriptif);

        DB::table('bukti_pengajuan')->insert(['pengajuan_id' => $draft->id, 'nama_file_asli' => 'tetap.png',
            'path_file' => 'bukti-pengajuan/tetap.png', 'mime_type' => 'image/png', 'ukuran_byte' => 68]);
        $this->post('/pengajuan/lokasi', $this->input(['draft_id' => $draft->id, 'action' => 'save', 'alamat' => 'Alamat baru']))
            ->assertRedirect('/pengajuan/lokasi');
        $this->assertDatabaseCount('pengajuan_plts', 1);
        $this->assertDatabaseHas('bukti_pengajuan', ['pengajuan_id' => $draft->id, 'nama_file_asli' => 'tetap.png']);

        $this->actingAs($this->user())->get('/pengajuan/lokasi/bukti')->assertRedirect('/pengajuan/lokasi');
        $this->actingAs($this->user('pemerintah'))->get('/pengajuan/lokasi/bukti')->assertForbidden();
        $draft->status_terkini = 'diajukan';
        $draft->save();
        $this->actingAs($owner)->get('/pengajuan/lokasi/bukti')->assertRedirect('/pengajuan/lokasi');
    }

    public function test_continue_rejects_incomplete_locations_even_with_an_existing_draft(): void
    {
        $this->actingAs($this->user());
        $this->postJson('/pengajuan/lokasi', ['action' => 'continue'])
            ->assertUnprocessable()->assertJsonValidationErrors(['provinsi', 'kabupaten', 'kecamatan', 'desa', 'alamat']);
        $this->assertDatabaseCount('pengajuan_plts', 0);
        $this->post('/pengajuan/lokasi', $this->input())->assertRedirect('/pengajuan/lokasi');
        $draft = PengajuanPlts::firstOrFail();
        $original = $draft->lokasi->alamat_deskriptif;
        foreach (['kabupaten', 'kecamatan', 'desa', 'alamat'] as $field) {
            $this->postJson('/pengajuan/lokasi', $this->input(['action' => 'continue', 'draft_id' => $draft->id, $field => '   ']))
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertSame($original, $draft->fresh()->lokasi->alamat_deskriptif);
        $this->assertDatabaseCount('pengajuan_plts', 1);
    }

    public function test_photo_upload_rejects_non_images_oversized_images_and_more_than_five(): void
    {
        Storage::fake('r2');
        $this->actingAs($this->user())->post('/pengajuan/lokasi', $this->input())->assertRedirect();
        $draft = PengajuanPlts::firstOrFail();

        $this->postJson('/pengajuan/lokasi/bukti', ['draft_id' => $draft->id,
            'photos' => [UploadedFile::fake()->create('catatan.txt', 10, 'text/plain')]])->assertUnprocessable();
        $this->postJson('/pengajuan/lokasi/bukti', ['draft_id' => $draft->id,
            'photos' => [$this->photo('besar.png', 5121)]])->assertUnprocessable();
        $this->postJson('/pengajuan/lokasi/bukti', ['draft_id' => $draft->id,
            'photos' => array_map(fn ($index) => $this->photo('foto-'.$index.'.png'), range(1, 6))])->assertUnprocessable();
        $this->assertDatabaseCount('bukti_pengajuan', 0);
        $this->assertSame([], Storage::disk('r2')->allFiles());
    }

    public function test_photo_upload_checks_role_ownership_and_draft_status(): void
    {
        Storage::fake('r2');
        $owner = $this->user();
        $this->actingAs($owner)->post('/pengajuan/lokasi', $this->input())->assertRedirect();
        $draft = PengajuanPlts::firstOrFail();
        $photo = fn () => $this->photo('bukti.png');

        $this->actingAs($this->user('pemerintah'))->post('/pengajuan/lokasi/bukti', ['draft_id' => $draft->id, 'photos' => [$photo()]])->assertForbidden();
        $this->actingAs($this->user())->post('/pengajuan/lokasi/bukti', ['draft_id' => $draft->id, 'photos' => [$photo()]])->assertNotFound();
        $draft->status_terkini = 'diajukan';
        $draft->save();
        $this->actingAs($owner)->post('/pengajuan/lokasi/bukti', ['draft_id' => $draft->id, 'photos' => [$photo()]])->assertStatus(409);
        $this->assertDatabaseCount('bukti_pengajuan', 0);
        $this->assertSame([], Storage::disk('r2')->allFiles());
    }

    public function test_metadata_failure_removes_only_objects_uploaded_by_the_request(): void
    {
        Storage::fake('r2');
        $this->actingAs($this->user())->post('/pengajuan/lokasi', $this->input())->assertRedirect();
        $draft = PengajuanPlts::firstOrFail();
        $preexistingPath = "bukti-pengajuan/$draft->id/keep.png";
        Storage::disk('r2')->put($preexistingPath, 'pre-existing object');
        DB::statement("CREATE TRIGGER fail_bukti_insert BEFORE INSERT ON bukti_pengajuan BEGIN SELECT RAISE(FAIL, 'metadata unavailable'); END");

        $this->from('/pengajuan/lokasi/bukti')->post('/pengajuan/lokasi/bukti', ['draft_id' => $draft->id,
            'photos' => [$this->photo('bukti.png')]])
            ->assertRedirect('/pengajuan/lokasi/bukti')->assertSessionHasErrors('photos');

        $this->assertDatabaseCount('bukti_pengajuan', 0);
        $this->assertSame([$preexistingPath], Storage::disk('r2')->allFiles());
    }

    public function test_photo_upload_rejects_when_draft_already_has_five_evidence_rows(): void
    {
        Storage::fake('r2');
        $this->actingAs($this->user())->post('/pengajuan/lokasi', $this->input())->assertRedirect();
        $draft = PengajuanPlts::firstOrFail();
        for ($index = 1; $index <= 5; $index++) {
            DB::table('bukti_pengajuan')->insert(['pengajuan_id' => $draft->id, 'nama_file_asli' => "lama-$index.jpg",
                'path_file' => "bukti-pengajuan/$draft->id/lama-$index.jpg", 'mime_type' => 'image/jpeg', 'ukuran_byte' => 100]);
        }

        $this->postJson('/pengajuan/lokasi/bukti', ['draft_id' => $draft->id,
            'photos' => [$this->photo('baru.png')]])->assertUnprocessable();
        $this->assertDatabaseCount('bukti_pengajuan', 5);
        $this->assertSame([], Storage::disk('r2')->allFiles());
    }

    public function test_r2_failure_shows_a_clear_error_without_creating_metadata(): void
    {
        $this->actingAs($this->user())->post('/pengajuan/lokasi', $this->input())->assertRedirect();
        $draft = PengajuanPlts::firstOrFail();
        Storage::shouldReceive('disk')->once()->with('r2')->andThrow(new \RuntimeException('private R2 failure detail'));

        $this->from('/pengajuan/lokasi/bukti')->post('/pengajuan/lokasi/bukti', ['draft_id' => $draft->id,
            'photos' => [$this->photo('bukti.png')]])
            ->assertRedirect('/pengajuan/lokasi/bukti')->assertSessionHasErrors('photos');

        $this->assertDatabaseCount('bukti_pengajuan', 0);
        $this->assertStringNotContainsString('private R2 failure detail', session()->get('errors')->first('photos'));
    }

    public function test_saved_photo_redirect_is_issued_only_to_the_draft_owner(): void
    {
        Storage::fake('r2');
        $owner = $this->user();
        $this->actingAs($owner)->post('/pengajuan/lokasi', $this->input())->assertRedirect();
        $draft = PengajuanPlts::firstOrFail();
        $id = DB::table('bukti_pengajuan')->insertGetId(['pengajuan_id' => $draft->id, 'nama_file_asli' => 'bukti.png',
            'path_file' => "bukti-pengajuan/$draft->id/unique.png", 'mime_type' => 'image/png', 'ukuran_byte' => 68]);
        Storage::disk('r2')->buildTemporaryUrlsUsing(fn ($path) => 'https://private-r2.example.test/signed/'.$path);

        $this->actingAs($owner)->get('/pengajuan/lokasi/bukti/'.$id)->assertRedirect('https://private-r2.example.test/signed/bukti-pengajuan/'.$draft->id.'/unique.png');
        $this->actingAs($this->user())->get('/pengajuan/lokasi/bukti/'.$id)->assertNotFound();
    }
}
