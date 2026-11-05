<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuktiPengajuan extends Model
{
    protected $table = 'bukti_pengajuan';

    public $timestamps = false;

    protected $fillable = [
        'pengajuan_id', 'nama_file_asli', 'path_file', 'mime_type', 'ukuran_byte', 'keterangan',
    ];

    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(PengajuanPlts::class, 'pengajuan_id');
    }
}
