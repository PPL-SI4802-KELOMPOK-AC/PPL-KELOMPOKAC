<?php

namespace App\Services;

use Illuminate\Support\Str;

final class StaffPermissionCatalog
{
    public const ITEMS = [
        'dashboard.view' => ['module' => 'Dashboard', 'label' => 'Melihat dashboard operasional', 'requires' => []],
        'wilayah.view' => ['module' => 'Data wilayah', 'label' => 'Melihat daftar dan rincian wilayah', 'requires' => []],
        'wilayah.create' => ['module' => 'Data wilayah', 'label' => 'Menambah wilayah', 'requires' => ['wilayah.view']],
        'wilayah.update' => ['module' => 'Data wilayah', 'label' => 'Memperbarui wilayah', 'requires' => ['wilayah.view']],
        'wilayah.delete' => ['module' => 'Data wilayah', 'label' => 'Menghapus wilayah', 'requires' => ['wilayah.view']],
    ];

    public static function name(string $name): string
    {
        return Str::squish($name);
    }

    public static function key(string $name): string
    {
        return hash('sha256', mb_strtolower(self::name($name)));
    }
}
