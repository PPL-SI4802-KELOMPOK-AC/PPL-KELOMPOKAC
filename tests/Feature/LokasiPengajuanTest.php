<?php

namespace Tests\Feature;

use App\Models\PengajuanPlts;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
            $table->id(); $table->string('name'); $table->string('email')->unique();
            $table->string('password'); $table->string('role'); $table->timestamps();
        });
        Schema::create('pengajuan_plts', function (Blueprint $table) {
            $table->id(); $table->string('nomor_tiket', 50)->unique();
            $table->foreignId('user_id')->constrained('users'); $table->unsignedBigInteger('wilayah_id')->nullable();
            $table->string('ringkasan_pengajuan');
            $table->enum('status_terkini', ['diajukan', 'diperiksa', 'diverifikasi', 'diproses', 'selesai', 'ditolak', 'draft'])->default('diajukan');
            $table->timestamp('status_diperbarui_pada')->useCurrent();
        });
        Schema::create('lokasi_pengajuan', function (Blueprint $table) {
            $table->id(); $table->foreignId('pengajuan_id')->unique()->constrained('pengajuan_plts');
            $table->text('alamat_deskriptif'); $table->decimal('latitude', 10, 7)->nullable(); $table->decimal('longitude', 11, 7)->nullable();
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
        $this->get('/pengajuan/lokasi')->assertOk()->assertViewHas('values', fn ($values) =>
            $values['kabupaten'] === '3205' && $values['kecamatan'] === 'Cikelet'
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
}
