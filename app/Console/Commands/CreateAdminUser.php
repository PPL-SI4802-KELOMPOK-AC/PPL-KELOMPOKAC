<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AdminActivityRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;

class CreateAdminUser extends Command
{
    protected $signature = 'admin:create {email? : Email akun admin baru} {--name= : Nama admin}';
    protected $description = 'Buat akun admin baru; kata sandi diminta secara tersembunyi.';

    public function handle(): int
    {
        if (! Schema::hasColumn('users', 'is_active') || ! Schema::hasTable('admin_activity_logs')) {
            $this->error('Jalankan migrasi admin sesuai docs/admin.md terlebih dahulu.');

            return self::FAILURE;
        }
        $input = [
            'name' => trim($this->option('name') ?: $this->ask('Nama admin')),
            'email' => mb_strtolower(trim($this->argument('email') ?: $this->ask('Email admin'))),
            'password' => $this->secret('Kata sandi (8–72 karakter)'),
            'password_confirmation' => $this->secret('Konfirmasi kata sandi'),
        ];
        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:254', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }
        $user = new User(['name' => $input['name'], 'email' => $input['email'], 'password' => $input['password']]);
        $user->role = 'admin';
        $user->is_active = true;
        DB::transaction(function () use ($user) {
            $user->save();
            app(AdminActivityRecorder::class)->record(null, $user, created: true);
        });
        $this->info('Akun admin berhasil dibuat. Masuk melalui /login untuk membuka /admin/ringkasan.');

        return self::SUCCESS;
    }
}
