<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LokasiPengajuan extends Model
{
    protected $table = 'lokasi_pengajuan';

    public $timestamps = false;

    protected $fillable = ['alamat_deskriptif', 'latitude', 'longitude'];
}
