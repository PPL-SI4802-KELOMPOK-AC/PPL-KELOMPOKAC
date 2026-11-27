<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffProfileAssignment extends Model
{
    protected $table = 'staff_profile_assignments';

    public $timestamps = false;

    public function profile(): BelongsTo
    {
        return $this->belongsTo(StaffAccessProfile::class, 'profile_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
