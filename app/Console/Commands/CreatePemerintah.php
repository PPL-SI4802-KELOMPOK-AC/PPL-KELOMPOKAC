<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreatePemerintah extends Command
{
    protected $signature = 'terangdesa:pemerintah';

    protected $description = 'Buat akun Pemerintah melalui terminal pengelola, tanpa registrasi publik';

    public function handle(): int
    {
        $data = [
            'name' => $this->ask('Nama pengelola'),
            'email' => $this->ask('Email'),
            'password' => $this->secret('Kata sandi (minimal 12 karakter)'),
            'password_confirmation' => $this->secret('Ulangi kata sandi'),
        ];
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:254', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'max:255', 'confirmed'],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        $user = new User(collect($data)->only(['name', 'email', 'password'])->all());
        $user->role = 'pemerintah';
        $user->save();
        $this->info('Akun Pemerintah berhasil dibuat. Masuk melalui /login.');

        return self::SUCCESS;
    }
}
