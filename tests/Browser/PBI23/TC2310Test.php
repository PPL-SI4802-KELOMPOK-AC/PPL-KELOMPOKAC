<?php

namespace Tests\Browser\PBI23;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Dusk\Browser;

/** Jalankan: php artisan dusk --filter=TC2310Test */
class TC2310Test extends PBI23TestCase
{
    public function test_tc_23_10_validasi_akun_tidak_valid(): void
    {
        $before = User::count();
        $audit = DB::table('admin_activity_logs')->count();
        $attempts = [
            ['email' => $this->fixture['society']->email, 'error' => 'Email sudah digunakan oleh akun lain.'],
            ['email' => 'bukan-email', 'error' => 'Masukkan alamat email yang valid.'],
            ['name' => '', 'error' => 'Nama pengguna wajib diisi.'],
            ['email' => '', 'error' => 'Email wajib diisi.'],
            ['password' => 'pendek', 'password_confirmation' => 'pendek', 'error' => 'Kata sandi minimal 8 karakter.'],
            ['password' => str_repeat('a', 73), 'password_confirmation' => str_repeat('a', 73), 'error' => 'Kata sandi maksimal 72 karakter.'],
            ['password_confirmation' => 'password-lain-123', 'error' => 'Konfirmasi kata sandi belum sesuai.'],
        ];
        $this->browse(function (Browser $b) use ($attempts, $before, $audit) {
            $this->admin($b);
            foreach ($attempts as $index => $attempt) {
                $b->visit('/admin/pengguna/tambah')->pause($this->stepPause());
                $this->fillAccount($b, $attempt);
                // HTML constraints are relaxed only in this test so server validation is exercised.
                $b->script('document.querySelector("[data-admin-user-form]").noValidate=true;document.querySelectorAll("[maxlength]").forEach(e=>e.removeAttribute("maxlength"))');
                if (isset($attempt['password']) && strlen($attempt['password']) > 72) {
                    $b->typeSlowly('password', $attempt['password'], $this->typingPause())->pause($this->stepPause())->typeSlowly('password_confirmation', $attempt['password_confirmation'], $this->typingPause())->pause($this->stepPause());
                }
                $b->press('Tambah pengguna')->pause($this->stepPause())->waitForText($attempt['error']);
                $this->assertSame($before, User::count());
                $this->assertSame($audit, DB::table('admin_activity_logs')->count());
                $this->capture($b, 'TC-23-10-validasi-'.($index + 1));
            }
        });
    }
}
