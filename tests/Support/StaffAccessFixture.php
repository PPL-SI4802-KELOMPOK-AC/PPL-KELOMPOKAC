<?php

namespace Tests\Support;

use App\Models\User;
use App\Services\StaffPermissionCatalog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Explicit test-fixture grants; never a production authorization bypass. */
trait StaffAccessFixture
{
    protected function grantStaffFixture(User $user): void
    {
        if ($user->role !== 'pemerintah') {
            return;
        }
        if (DB::connection()->getDriverName() !== 'sqlite') {
            throw new \RuntimeException('Fixture hak akses hanya untuk SQLite pengujian.');
        }
        if (! Schema::hasColumn('users', 'is_active')) {
            Schema::table('users', fn (Blueprint $t) => $t->boolean('is_active')->default(true));
        }
        if (! Schema::hasTable('admin_activity_logs')) {
            (require database_path('migrations/2026_11_07_000002_create_admin_activity_logs_table.php'))->up();
        }
        if (! Schema::hasTable('staff_access_profiles')) {
            (require database_path('migrations/2026_11_27_000001_create_staff_access_profiles.php'))->up();
        }
        $id = DB::table('staff_access_profiles')->value('id');
        if (! $id) {
            $id = DB::table('staff_access_profiles')->insertGetId(['name' => 'Profil fixture', 'name_key' => StaffPermissionCatalog::key('Profil fixture'),
                'is_active' => true, 'ever_assigned' => true, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            foreach (array_keys(StaffPermissionCatalog::ITEMS) as $code) {
                DB::table('staff_profile_permissions')->insert(['profile_id' => $id, 'permission_code' => $code]);
            }
        }
        DB::table('staff_profile_assignments')->updateOrInsert(['user_id' => $user->id], ['profile_id' => $id, 'assigned_by' => null, 'assigned_at' => now()]);
    }

    protected function resetStaffAccessFixture(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            throw new \RuntimeException('Reset fixture hak akses hanya untuk SQLite pengujian.');
        }
        foreach (['staff_profile_assignments', 'staff_profile_permissions', 'staff_permissions', 'staff_access_profiles', 'admin_activity_logs'] as $table) {
            Schema::dropIfExists($table);
        }
    }
}
