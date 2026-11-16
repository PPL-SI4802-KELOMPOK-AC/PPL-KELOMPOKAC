<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PublicNavigationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'app.key' => 'base64:'.base64_encode(str_repeat('n', 32))]);
        DB::purge('sqlite');
        $this->withoutVite();
        Schema::create('users', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('email')->unique();
            $table->string('password'); $table->string('role'); $table->timestamps();
        });
    }

    private function user(string $role): User
    {
        $user = new User(['name' => 'Akun '.ucfirst($role), 'email' => $role.'@example.test', 'password' => 'navigation-password']);
        $user->role = $role;
        $user->save();

        return $user;
    }

    public function test_guests_get_the_public_home_map_and_login_without_the_workspace_sidebar(): void
    {
        $this->get('/')->assertOk()->assertSee('top-navigation-page')->assertDontSee('data-region-map', false)
            ->assertSee('data-dashboard', false)->assertSee('Sebaran KK Belum Berlistrik')
            ->assertSee('SDG 7')->assertSee('Masuk')->assertDontSee('id="sidebar"', false);
        $this->get('/peta-wilayah')->assertOk()->assertSee('top-navigation-page')
            ->assertSee('aria-current="page"', false)->assertDontSee('id="sidebar"', false)
            ->assertSee('data-map-filters', false)->assertSee('data-map-detail-panel', false);
        $this->get('/login')->assertOk()->assertSee('Masuk ke TerangDesa')->assertDontSee('id="sidebar"', false);
        $this->get('/pengajuan/lokasi')->assertRedirect('/login');
        $this->get('/pengajuan/riwayat')->assertRedirect('/login');
        $this->get('/pemerintah/wilayah')->assertRedirect('/login');
    }

    public function test_masyarakat_map_keeps_submission_navigation_and_cannot_manage_regions(): void
    {
        $this->actingAs($this->user('masyarakat'))->get('/peta-wilayah')->assertOk()
            ->assertSee('top-navigation-page')->assertSee('Pengajuan PLTS')->assertSee('Riwayat')
            ->assertSee('Akun Masyarakat')->assertSee('Keluar')->assertDontSee('id="sidebar"', false)
            ->assertDontSee('Data wilayah');
        $this->get('/pemerintah/wilayah')->assertForbidden();
    }

    public function test_pemerintah_workspace_and_map_keep_the_existing_sidebar(): void
    {
        $this->actingAs($this->user('pemerintah'));
        foreach (['/pemerintah/wilayah', '/peta-wilayah'] as $path) {
            $this->get($path)->assertOk()->assertSee('id="sidebar"', false)->assertSee('Data wilayah')
                ->assertDontSee('top-navigation-page');
        }
        $this->get('/pengajuan/lokasi')->assertForbidden();
        $this->get('/pengajuan/riwayat')->assertForbidden();
    }

    public function test_login_lands_on_existing_routes_for_each_role(): void
    {
        foreach (['masyarakat' => '/peta-wilayah', 'pemerintah' => '/pemerintah/wilayah', 'admin' => '/admin/ringkasan'] as $role => $path) {
            $this->user($role);
            $this->post('/login', ['email' => $role.'@example.test', 'password' => 'navigation-password'])->assertRedirect($path);
            $this->post('/logout');
        }
    }

    public function test_login_respects_an_authorized_intended_page_but_rejects_cross_role_and_external_destinations(): void
    {
        $this->user('masyarakat');
        foreach ([url('/pengajuan/riwayat') => '/pengajuan/riwayat', url('/pemerintah/wilayah') => '/peta-wilayah',
            'https://untrusted.example/pengajuan/lokasi' => '/peta-wilayah'] as $intended => $destination) {
            $this->withSession(['url.intended' => $intended])->post('/login', [
                'email' => 'masyarakat@example.test', 'password' => 'navigation-password',
            ])->assertRedirect($destination);
            $this->post('/logout');
        }
    }

    public function test_login_uses_the_same_generic_error_for_wrong_password_and_unknown_email(): void
    {
        $this->user('masyarakat');

        foreach ([
            ['email' => 'masyarakat@example.test', 'password' => 'wrong-password'],
            ['email' => 'unknown@example.test', 'password' => 'navigation-password'],
        ] as $credentials) {
            $this->from('/login')->post('/login', $credentials)
                ->assertRedirect('/login')
                ->assertSessionHasErrors(['email' => 'Email atau kata sandi salah']);
            $this->assertGuest();
        }
    }

    public function test_login_rejects_empty_email_or_password(): void
    {
        $this->user('masyarakat');

        foreach ([
            [['email' => '', 'password' => 'navigation-password'], 'email'],
            [['email' => 'masyarakat@example.test', 'password' => ''], 'password'],
        ] as [$credentials, $field]) {
            $this->from('/login')->post('/login', $credentials)
                ->assertRedirect('/login')
                ->assertSessionHasErrors($field);
            $this->assertGuest();
        }
    }

    public function test_admin_has_public_navigation_without_receiving_pemerintah_permissions(): void
    {
        $this->actingAs($this->user('admin'))->get('/')->assertOk()->assertSee('top-navigation-page')
            ->assertDontSee('id="sidebar"', false)->assertSee('Akun Admin');
        $this->get('/pemerintah/wilayah')->assertForbidden();
        $this->get('/pengajuan/lokasi')->assertForbidden();
    }
}
