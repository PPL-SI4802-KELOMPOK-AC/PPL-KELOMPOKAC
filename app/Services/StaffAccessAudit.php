<?php

namespace App\Services;

use App\Models\AdminActivityLog;
use App\Models\StaffAccessProfile;
use App\Models\User;

class StaffAccessAudit
{
    public function record(User $actor, StaffAccessProfile $profile, string $action, array $before, array $after, ?User $staff = null): void
    {
        $changes = [];
        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $field) {
            if (($before[$field] ?? null) !== ($after[$field] ?? null)) {
                $changes[$field] = ['before' => $before[$field] ?? null, 'after' => $after[$field] ?? null];
            }
        }
        $log = new AdminActivityLog;
        $log->forceFill(['actor_id' => $actor->id, 'actor_name' => $actor->name, 'subject_id' => $staff?->id,
            'subject_name' => $staff?->name ?? $profile->name, 'target_type' => 'profile', 'target_id' => $profile->id,
            'action' => $action, 'result' => 'success', 'changes' => $changes, 'created_at' => now()])->save();
    }
}
