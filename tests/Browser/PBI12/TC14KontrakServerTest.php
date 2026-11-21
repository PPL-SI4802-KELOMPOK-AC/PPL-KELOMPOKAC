<?php

namespace Tests\Browser\PBI12;

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
}
