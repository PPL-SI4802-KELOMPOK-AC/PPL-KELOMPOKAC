<?php

namespace Tests\Browser\PBI12;

use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\DataProvider;

/** Requirement autentikasi bersama PBI-11 dan PBI-12 (deskripsi Jira duplikat). */
class TC13AutentikasiLengkapTest extends PBI12TestCase
{
    public static function emptyFields(): array
    {
        return [
            'email kosong' => ['', self::PASSWORD_MASYARAKAT, 'email'],
            'password kosong' => ['masyarakat.uji@example.test', '', 'password'],
        ];
    }

    #[DataProvider('emptyFields')]
    public function test_single_empty_field_is_rejected(string $email, string $password, string $missing): void
    {
        $this->browse(function (Browser $browser) use ($email, $password, $missing) {
            try {
                $this->clearBrowserSession($browser);
                $browser->visit(route('login'))->pause(2000);
                if ($email !== '') {
                    $browser->type('email', $email)->pause(2000);
                }
                if ($password !== '') {
                    $browser->type('password', $password)->pause(2000);
                }
                $browser->pause(2000)->press('Masuk')->pause(2000)->assertPathIs('/login');
                $this->assertTrue($browser->script(
                    'return document.getElementById('.json_encode($missing).').validity.valueMissing;'
                )[0]);
            } finally {
                $browser->pause(3000);
            }
        });
    }

    public function test_invalid_credentials_show_exactly_the_same_generic_message(): void
    {
        $this->browse(function (Browser $browser) {
            try {
                $messages = [];
                foreach (['masyarakat.uji@example.test', 'unknown@example.test'] as $email) {
                    $this->clearBrowserSession($browser);
                    $browser->visit(route('login'))->pause(2000)
                        ->type('email', $email)->pause(2000)->type('password', 'wrong-password')->pause(2000)
                        ->press('Masuk')->pause(2000)->waitFor('#email-error')->assertPathIs('/login');
                    $messages[] = $browser->text('#email-error');
                    $browser->visit(route('admin.dashboard'))->pause(2000)->assertPathIs('/login');
                }
                $this->assertSame(['Email atau kata sandi salah', 'Email atau kata sandi salah'], $messages);
            } finally {
                $browser->pause(3000);
            }
        });
    }

    public static function roleRoutes(): array
    {
        return [
            'masyarakat' => ['masyarakat', 'pengajuan.riwayat', '/peta-wilayah', self::PASSWORD_MASYARAKAT],
            'pemerintah' => ['pemerintah', 'wilayah.index', '/pemerintah/wilayah', self::PASSWORD_PEMERINTAH],
            'admin' => ['admin', 'admin.dashboard', '/admin/ringkasan', self::PASSWORD_ADMIN],
        ];
    }

    #[DataProvider('roleRoutes')]
    public function test_allowed_route_and_logout_for_each_role(string $role, string $route, string $destination, string $password): void
    {
        $this->browse(function (Browser $browser) use ($role, $route, $destination, $password) {
            try {
                $this->clearBrowserSession($browser);
                $this->login($browser, $this->fixture[$role]->email, $password);
                $browser->assertPathIs($destination)->visit(route($route))->pause(2000);
                $this->assertSame(200, $browser->script('return performance.getEntriesByType("navigation")[0].responseStatus;')[0]);
                if ($role === 'pemerintah') {
                    $browser->waitUntil('document.querySelector("[data-table-wrap]").getAttribute("aria-busy") === "false"');
                }
                $browser->pause(2000)->click('form[action$="/logout"] button[type="submit"]')->pause(2000)
                    ->waitForLocation('/login')->assertPathIs('/login')
                    ->visit(route($route))->pause(2000)->assertPathIs('/login');
            } finally {
                $browser->pause(3000);
            }
        });
    }

    #[DataProvider('roleRoutes')]
    public function test_guest_redirect_for_each_protected_role_route(string $role, string $route, string $destination, string $password): void
    {
        $this->browse(function (Browser $browser) use ($route) {
            try {
                $this->clearBrowserSession($browser);
                $browser->visit(route($route))->pause(2000)->assertPathIs('/login');
            } finally {
                $browser->pause(3000);
            }
        });
    }
}
