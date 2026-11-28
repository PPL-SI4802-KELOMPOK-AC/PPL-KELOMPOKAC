<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GovernmentDashboardView extends Model
{
    protected $fillable = ['name', 'normalized_name', 'year', 'kabupaten', 'kecamatan', 'desa', 'kondisi'];

    protected $hidden = ['user_id', 'normalized_name'];

    protected function casts(): array
    {
        return ['year' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
