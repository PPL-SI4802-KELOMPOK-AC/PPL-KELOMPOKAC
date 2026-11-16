<?php

namespace Tests\Feature\PBI01;

use Illuminate\Support\Facades\DB;
use Tests\Support\PBI01\Pbi01Database;
use Tests\TestCase;

class KelolaWilayahTest extends TestCase
{
    private array $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'app.key' => 'base64:'.base64_encode(str_repeat('w', 32))]);
        DB::purge('sqlite');
        $this->withoutVite();
        $this->fixture = Pbi01Database::rebuild();
        $this->actingAs($this->fixture['petugas']);
    }

    public function test_tc_17_01_tambah_wilayah_dan_created_by_otomatis(): void
    {
        $payload = Pbi01Database::payload();
        $this->postJson('/pemerintah/wilayah', $payload + ['created_by' => $this->fixture['masyarakat']->id])
            ->assertCreated()->assertJsonPath('wilayah.created_by', $this->fixture['petugas']->id);
        $this->assertDatabaseHas('wilayah', ['nama_wilayah' => $payload['nama_wilayah'], 'jumlah_kk_terdampak' => 25,
            'created_by' => $this->fixture['petugas']->id]);
        $this->getJson('/pemerintah/wilayah/data?q=Desa%20Harapan%20Uji')->assertOk()
            ->assertJsonPath('total', 1)->assertJsonPath('data.0.nama_wilayah', $payload['nama_wilayah']);
    }

    public function test_tc_17_02_edit_kk_mekarjaya_tanpa_menimpa_riwayat(): void
    {
        $history = Pbi01Database::history();
        $id = $this->fixture['mekarjaya']->id;
        $payload = array_replace(Pbi01Database::payload('Mekarjaya'), ['jumlah_kk_terdampak' => 42]);
        $this->putJson('/pemerintah/wilayah/'.$id, $payload)->assertOk()->assertJsonPath('wilayah.jumlah_kk_terdampak', 42);
        $this->getJson('/pemerintah/wilayah/'.$id)->assertOk()->assertJsonPath('jumlah_kk_terdampak', 42);
        $this->assertDatabaseHas('wilayah', ['id' => $id, 'jumlah_kk_terdampak' => 42, 'created_by' => $this->fixture['petugas']->id]);
        $this->assertSame($history, Pbi01Database::history());
    }

    public function test_tc_17_03_hapus_wilayah_tanpa_relasi(): void
    {
        $history = Pbi01Database::history();
        $id = $this->fixture['hapus']->id;
        $this->deleteJson('/pemerintah/wilayah/'.$id)->assertOk();
        $this->assertDatabaseMissing('wilayah', ['id' => $id]);
        $this->getJson('/pemerintah/wilayah/data?q=Desa%20Uji%20Hapus')->assertOk()->assertJsonPath('total', 0);
        $this->assertSame($history, Pbi01Database::history());
    }

    public function test_tc_17_04_kolom_wajib_kosong_ditolak(): void
    {
        foreach (['nama_wilayah', 'tingkat_wilayah', 'alamat_deskriptif'] as $field) {
            $this->postJson('/pemerintah/wilayah', array_replace(Pbi01Database::payload(), [$field => '']))
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertDatabaseCount('wilayah', 2);
    }

    public function test_tc_17_05_koordinat_di_luar_batas_ditolak(): void
    {
        foreach ([['91', '107'], ['-91', '107'], ['-6', '181'], ['-6', '-181'], ['abc', '107']] as [$latitude, $longitude]) {
            $this->postJson('/pemerintah/wilayah', array_replace(Pbi01Database::payload(), compact('latitude', 'longitude')))
                ->assertUnprocessable()->assertJsonValidationErrors(abs((float) $longitude) > 180 ? 'longitude' : 'latitude');
        }
        $this->assertDatabaseCount('wilayah', 2);
    }

    public function test_tc_17_06_tanpa_request_hapus_data_tetap_ada(): void
    {
        // Interaksi Batalkan pada dialog asli diuji oleh Dusk TC-17-06.
        $this->get('/pemerintah/wilayah')->assertOk();
        $this->assertDatabaseHas('wilayah', ['id' => $this->fixture['hapus']->id]);
    }

    public function test_tc_17_07_masyarakat_mendapat_403_di_halaman_dan_endpoint(): void
    {
        $this->actingAs($this->fixture['masyarakat']);
        $id = $this->fixture['mekarjaya']->id;
        $this->get('/pemerintah/wilayah')->assertForbidden();
        $this->getJson('/pemerintah/wilayah/data')->assertForbidden();
        $this->getJson('/pemerintah/wilayah/'.$id)->assertForbidden();
        $this->postJson('/pemerintah/wilayah', Pbi01Database::payload())->assertForbidden();
        $this->putJson('/pemerintah/wilayah/'.$id, Pbi01Database::payload())->assertForbidden();
        $this->deleteJson('/pemerintah/wilayah/'.$id)->assertForbidden();
        $this->assertDatabaseCount('wilayah', 2);
    }

    public function test_wilayah_terkait_pengajuan_atau_progres_tidak_boleh_dihapus(): void
    {
        $history = Pbi01Database::history();
        $this->deleteJson('/pemerintah/wilayah/'.$this->fixture['mekarjaya']->id)->assertStatus(409);
        DB::table('progres_elektrifikasi')->insert(['wilayah_id' => $this->fixture['hapus']->id]);
        $this->deleteJson('/pemerintah/wilayah/'.$this->fixture['hapus']->id)->assertStatus(409);
        $this->assertDatabaseCount('wilayah', 2);
        $this->assertSame($history, Pbi01Database::history());
    }
}
