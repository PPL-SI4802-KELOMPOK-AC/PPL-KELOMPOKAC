<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wilayah extends Model
{
    protected $table = 'wilayah';

    public $timestamps = false;

    protected $fillable = [
        'kode_referensi', 'nama_wilayah', 'tingkat_wilayah', 'alamat_deskriptif',
        'latitude', 'longitude', 'kondisi_listrik', 'jumlah_kk_terdampak',
        'jumlah_warga_terdampak', 'sumber_data', 'data_per_tanggal',
    ];

    protected function casts(): array
    {
        return [
            'jumlah_kk_terdampak' => 'integer',
            'jumlah_warga_terdampak' => 'integer',
        ];
    }
}
