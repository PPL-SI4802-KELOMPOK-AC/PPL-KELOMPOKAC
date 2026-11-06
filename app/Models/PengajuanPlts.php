<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PengajuanPlts extends Model
{
    protected $table = 'pengajuan_plts';

    public $timestamps = false;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function lokasi(): HasOne
    {
        return $this->hasOne(LokasiPengajuan::class, 'pengajuan_id');
    }

    public function bukti(): HasMany
    {
        return $this->hasMany(BuktiPengajuan::class, 'pengajuan_id');
    }
}
