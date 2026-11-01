<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RegionMapTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('t', 32))]);
    }

    public function test_home_navigation_opens_the_map(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route('wilayah.map'))
            ->assertSee('Jelajahi Peta Wilayah');
    }

    public function test_base_map_does_not_need_a_database_or_village_coordinates(): void
    {
        // Stronger than a NULL fixture: this page cannot request any database connection.
        // No migrations or changes to the user's MySQL dataset are needed for this test.
        DB::shouldReceive('connection')->never();

        $this->get(route('wilayah.map'))
            ->assertOk()
            ->assertSee('Peta Wilayah | TerangDesa')
            ->assertSee('aria-current="page"', false)
            ->assertSee('data-region-map', false)
            ->assertSee('Kembali ke Jawa Barat')
            ->assertSee('Memuat peta dasar')
            ->assertSee('Coba lagi')
            ->assertSee('tahun 2024')
            ->assertSee('masih memerlukan verifikasi');
    }

    public function test_only_public_map_configuration_is_rendered(): void
    {
        config(['database.connections.mysql.password' => 'private-db-canary']);
        config(['map.tile_url' => 'https://tiles.example.test/{z}/{x}/{y}.png']);

        $this->get(route('wilayah.map'))
            ->assertOk()
            ->assertSee('https://tiles.example.test/{z}/{x}/{y}.png', false)
            ->assertDontSee('private-db-canary');
    }
}
