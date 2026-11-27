<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffAccessProfile extends Model
{
    protected $table = 'staff_access_profiles';

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'ever_assigned' => 'boolean', 'version' => 'integer'];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(StaffProfileAssignment::class, 'profile_id');
    }

    public function permissionCodes(): array
    {
        return $this->getConnection()->table('staff_profile_permissions')->where('profile_id', $this->id)
            ->orderBy('permission_code')->pluck('permission_code')->all();
    }
}
