<?php

namespace App\Services;

use App\Models\AdminActivityLog;
use App\Models\User;

class AdminActivityRecorder
{
    public function record(?User $actor, User $subject, array $before = [], bool $created = false): void
    {
        $changes = [];
        foreach (array_keys(AdminActivityLog::FIELDS) as $field) {
            $old = $before[$field] ?? null;
            $new = $subject->getAttribute($field);
            if ($created || $old !== $new) {
                $changes[$field] = ['before' => $old, 'after' => $new];
            }
        }
        // Rekam hanya penanda perubahan, tanpa hash atau kata sandi asli.
        if ($created || $subject->wasChanged('password')) {
            $changes['password_changed'] = true;
        }
        if ($changes === []) {
            return;
        }
        $log = new AdminActivityLog;
        $log->forceFill([
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name ?? 'Sistem (terminal)',
            'subject_id' => $subject->id,
            'subject_name' => $subject->name,
            'action' => $created ? 'user.created' : 'user.updated',
            'changes' => $changes,
            'created_at' => now(),
        ])->save();
    }
}
