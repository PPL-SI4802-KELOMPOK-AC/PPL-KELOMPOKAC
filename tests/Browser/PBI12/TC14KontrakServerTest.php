<?php

namespace Tests\Browser\PBI12;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;

/** Kontrak HTTP autentikasi PBI-11/PBI-12 pada SQLite fixture, tanpa migrasi/seeder utama. */
class TC14KontrakServerTest extends PBI12TestCase
{
    public static function blankCredentials(): array
    {
        return [
            'keduanya kosong' => ['', '', ['email', 'password']],
            'email kosong' => ['', self::PASSWORD_MASYARAKAT, ['email']],
            'password kosong' => ['masyarakat.uji@example.test', '', ['password']],
        ];
    }

    #[DataProvider('blankCredentials')]
    public function test_server_rejects_blank_credentials(string $email, string $password, array $errors): void
    {
        $this->postJson(route('login.store'), compact('email', 'password'))
            ->assertUnprocessable()->assertJsonValidationErrors($errors);
        $this->assertGuest();
    }

    public static function forbiddenRoles(): array
    {
        return [
            'masyarakat ke pemerintah' => ['masyarakat', 'wilayah.index'],
            'masyarakat ke admin' => ['masyarakat', 'admin.dashboard'],
            'pemerintah ke admin' => ['pemerintah', 'admin.dashboard'],
            'pemerintah ke masyarakat' => ['pemerintah', 'pengajuan.riwayat'],
            'admin ke pemerintah' => ['admin', 'wilayah.index'],
            'admin ke masyarakat' => ['admin', 'pengajuan.riwayat'],
        ];
    }

    #[DataProvider('forbiddenRoles')]
    public function test_role_middleware_rejects_direct_url(string $role, string $route): void
    {
        $this->actingAs($this->fixture[$role])->get(route($route))->assertForbidden();
    }

    public function test_database_seeder_provides_login_accounts_for_all_roles(): void
    {
        // setUp sudah memastikan koneksi SQLite dan nama file fixture unik.
        // Hapus fixture agar akun buatan test tidak menutupi kekurangan seeder asli.
        DB::table('users')->delete();
        $this->seed(DatabaseSeeder::class);
        foreach (['masyarakat', 'pemerintah', 'admin'] as $role) {
            $account = User::where('role', $role)->first();
            $this->assertNotNull($account, 'Seeder harus menyediakan akun '.$role);
            // Factory proyek menetapkan password awal "password".
            $this->assertTrue(Hash::check('password', $account->password));
            $this->post(route('login.store'), ['email' => $account->email, 'password' => 'password'])
                ->assertRedirect(route(match ($role) {
                    'masyarakat' => 'wilayah.map',
                    'pemerintah' => 'wilayah.index',
                    'admin' => 'admin.dashboard',
                }));
            $this->assertAuthenticatedAs($account);
            $this->post(route('logout'))->assertRedirect(route('login'));
        }
    }
}
