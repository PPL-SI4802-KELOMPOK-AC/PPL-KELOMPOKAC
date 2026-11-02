<?php

namespace Tests\Feature;

use App\Services\DashboardSummary;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    public function test_home_renders_hero_workflow_and_existing_navigation_without_database_dependency(): void
    {
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('t', 32))]);
        DB::shouldReceive('connection')->never();
        $this->get('/')->assertOk()->assertSee('Kenali kebutuhan listrik desa.')
            ->assertSee('Bagaimana TerangDesa bekerja?')->assertSee('Energi Bersih dan Terjangkau')
            ->assertSee(route('wilayah.map'))->assertSee(route('wilayah.index'))->assertSee('#cara-kerja', false)
            ->assertSee('data-summary-url', false)->assertDontSee('ambient__cursor');
    }

    public function test_summary_failure_is_safe_even_when_debug_is_enabled(): void
    {
        config(['app.debug' => true, 'database.connections.mysql.password' => 'dashboard-password-canary']);
        $this->mock(DashboardSummary::class, function ($mock) {
            $mock->shouldReceive('data')->once()->andThrow(new \RuntimeException('dashboard-password-canary private-sql-canary'));
        });
        $this->getJson('/dashboard/data')->assertStatus(503)
            ->assertJsonPath('message', 'Ringkasan belum dapat dimuat. Periksa koneksi database lalu coba kembali.')
            ->assertDontSee('dashboard-password-canary')->assertDontSee('private-sql-canary');
    }
}
