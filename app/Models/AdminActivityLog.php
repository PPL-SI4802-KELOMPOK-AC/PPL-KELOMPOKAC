<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminActivityLog extends Model
{
    public const ACTIONS = ['user.created' => 'Tambah pengguna', 'user.updated' => 'Ubah pengguna'];
    public const FIELDS = ['name' => 'Nama', 'email' => 'Email', 'role' => 'Peran', 'is_active' => 'Akses akun'];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['changes' => 'array', 'created_at' => 'datetime'];
    }

    public function displayValue(string $field, mixed $value): string
    {
        if ($value === null) {
            return '—';
        }
        if ($field === 'is_active') {
            return $value ? 'Aktif' : 'Nonaktif';
        }
        if ($field === 'role') {
            return ['masyarakat' => 'Masyarakat', 'pemerintah' => 'Pemerintah', 'admin' => 'Admin'][$value] ?? (string) $value;
        }

        return (string) $value;
    }
}
