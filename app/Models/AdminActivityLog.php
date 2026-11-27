<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminActivityLog extends Model
{
    public const ACTIONS = ['user.created' => 'Tambah pengguna', 'user.updated' => 'Ubah pengguna',
        'access.profile.created' => 'Tambah profil hak akses', 'access.profile.updated' => 'Ubah profil dan izin',
        'access.profile.deactivated' => 'Nonaktifkan profil', 'access.profile.assigned' => 'Tetapkan profil petugas',
        'access.profile.deleted' => 'Hapus profil hak akses'];
    public const FIELDS = ['name' => 'Nama', 'email' => 'Email', 'role' => 'Peran', 'is_active' => 'Akses akun'];
    public const ACCESS_FIELDS = ['profile_name' => 'Profil hak akses', 'description' => 'Deskripsi', 'is_active' => 'Status profil', 'permission_codes' => 'Izin layanan'];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['changes' => 'array', 'created_at' => 'datetime'];
    }

    public function displayValue(string $field, mixed $value): string
    {
        if (is_array($value)) return implode(', ', array_map(fn ($code) => \App\Services\StaffPermissionCatalog::ITEMS[$code]['label'] ?? $code, $value)) ?: 'Tidak ada izin';
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
