<?php

namespace App\Services;

use App\Models\StaffAccessProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffProfileManager
{
    public function __construct(private StaffAccessAudit $audit) {}

    private function actor(User $user): User
    {
        // Same initial lock order as PBI-23, serializing authority changes and competing assignments/deletes.
        User::where('role', 'admin')->orderBy('id')->lockForUpdate()->get();
        $actor = User::lockForUpdate()->findOrFail($user->id);
        abort_unless($actor->role === 'admin' && $actor->is_active === true, 403);

        return $actor;
    }

    public function snapshot(StaffAccessProfile $profile): array
    {
        return ['profile_name' => $profile->name, 'description' => $profile->description,
            'is_active' => $profile->is_active, 'permission_codes' => $profile->permissionCodes()];
    }

    private function unchanged(StaffAccessProfile $profile, array $input): void
    {
        $count = $profile->assignments()->lockForUpdate()->get()->count();
        if ($profile->version !== (int) $input['expected_version'] || $count !== (int) $input['expected_usage_count']) {
            throw ValidationException::withMessages(['profile' => 'Profil atau penetapan petugas sudah berubah. Buka kembali rincian dan tinjau dampak terbaru.']);
        }
    }

    public function save(User $user, ?int $id, array $input): StaffAccessProfile
    {
        return DB::transaction(function () use ($user, $id, $input) {
            $actor = $this->actor($user);
            $profile = $id ? StaffAccessProfile::lockForUpdate()->findOrFail($id) : new StaffAccessProfile;
            if ($id) {
                $this->unchanged($profile, $input);
            }
            $before = $id ? $this->snapshot($profile) : [];
            $profile->name = StaffPermissionCatalog::name($input['name']);
            $profile->name_key = StaffPermissionCatalog::key($input['name']);
            $profile->description = $input['description'] ?? null;
            $profile->is_active = (bool) $input['is_active'];
            $profile->version = ($profile->version ?? 0) + 1;
            $profile->save();
            DB::table('staff_profile_permissions')->where('profile_id', $profile->id)->delete();
            foreach ($input['permissions'] as $code) {
                DB::table('staff_profile_permissions')->insert(['profile_id' => $profile->id, 'permission_code' => $code]);
            }
            $action = ! $id ? 'access.profile.created' : ($before['is_active'] && ! $profile->is_active ? 'access.profile.deactivated' : 'access.profile.updated');
            $this->audit->record($actor, $profile, $action, $before, $this->snapshot($profile));

            return $profile;
        }, 3);
    }

    public function assign(User $user, int $staffId, int $profileId, ?int $expectedProfileId, int $expectedVersion): void
    {
        DB::transaction(function () use ($user, $staffId, $profileId, $expectedProfileId, $expectedVersion) {
            $actor = $this->actor($user);
            $oldId = DB::table('staff_profile_assignments')->where('user_id', $staffId)->value('profile_id');
            $profiles = StaffAccessProfile::whereIn('id', array_filter([$oldId, $profileId]))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $profile = $profiles->get($profileId);
            $staff = User::lockForUpdate()->findOrFail($staffId);
            if ($staff->role !== 'pemerintah' || $staff->is_active !== true) {
                throw ValidationException::withMessages(['user_id' => 'Profil hanya dapat ditetapkan kepada akun Pemerintah yang aktif.']);
            }
            if (! $profile || ! $profile->is_active) {
                throw ValidationException::withMessages(['profile_id' => 'Pilih profil aktif yang tersedia.']);
            }
            if ($profile->version !== $expectedVersion || ($oldId ? (int) $oldId : null) !== $expectedProfileId) {
                throw ValidationException::withMessages(['profile_id' => 'Profil atau penetapan sudah berubah. Tinjau ulang pilihan sebelum menyimpan.']);
            }
            if ((int) $oldId === $profileId) {
                return;
            }
            $oldName = $profiles->get($oldId)?->name;
            DB::table('staff_profile_assignments')->updateOrInsert(['user_id' => $staff->id], ['profile_id' => $profileId, 'assigned_by' => $actor->id, 'assigned_at' => now()]);
            $profile->ever_assigned = true;
            $profile->version++;
            $profile->save();
            if ($oldId && isset($profiles[$oldId])) {
                $profiles[$oldId]->version++;
                $profiles[$oldId]->save();
            }
            $this->audit->record($actor, $profile, 'access.profile.assigned', ['profile_name' => $oldName], ['profile_name' => $profile->name], $staff);
        }, 3);
    }

    public function delete(User $user, int $id, array $input): void
    {
        DB::transaction(function () use ($user, $id, $input) {
            $actor = $this->actor($user);
            $profile = StaffAccessProfile::lockForUpdate()->findOrFail($id);
            $this->unchanged($profile, $input);
            if ($profile->ever_assigned || $profile->assignments()->lockForUpdate()->get()->isNotEmpty()) {
                throw ValidationException::withMessages(['profile' => 'Profil masih digunakan atau memiliki riwayat penetapan. Gunakan penonaktifan untuk mempertahankan riwayat.']);
            }
            $before = $this->snapshot($profile);
            DB::table('staff_profile_permissions')->where('profile_id', $id)->delete();
            $profile->delete();
            $this->audit->record($actor, $profile, 'access.profile.deleted', $before, []);
        }, 3);
    }
}
