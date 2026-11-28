<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class StaffAccess
{
    public function allows(User $user, string $code): bool
    {
        if (! isset(StaffPermissionCatalog::ITEMS[$code]) || $user->role !== 'pemerintah' || $user->is_active === false) {
            return false;
        }
        // Deliberately no session, model-relation or persistent cache: every request sees current grants.
        $codes = DB::table('staff_profile_assignments as a')->join('staff_access_profiles as p', 'p.id', '=', 'a.profile_id')
            ->join('staff_profile_permissions as g', 'g.profile_id', '=', 'p.id')->join('users as u', 'u.id', '=', 'a.user_id')
            ->where('u.id', $user->id)->where('u.role', 'pemerintah')->where('u.is_active', true)->where('p.is_active', true)
            ->pluck('g.permission_code')->all();
        foreach ([$code, ...StaffPermissionCatalog::ITEMS[$code]['requires']] as $required) {
            if (! in_array($required, $codes, true)) {
                return false;
            }
        }

        return true;
    }
}
