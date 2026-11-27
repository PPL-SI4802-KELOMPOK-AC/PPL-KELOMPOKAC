<?php

use App\Services\StaffPermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // TiDB DDL is not transactional. Guards also permit resuming an interrupted additive migration.
        if (! Schema::hasTable('staff_access_profiles')) {
            Schema::create('staff_access_profiles', function (Blueprint $t) {
                $t->id();
                $t->string('name', 150);
                $t->char('name_key', 64)->unique();
                $t->text('description')->nullable();
                $t->boolean('is_active')->default(true)->index();
                $t->boolean('ever_assigned')->default(false);
                $t->unsignedInteger('version')->default(1);
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('staff_permissions')) {
            Schema::create('staff_permissions', function (Blueprint $t) {
                $t->string('code', 80)->primary();
                $t->string('module', 100);
                $t->string('label', 180);
            });
        }
        if (! Schema::hasTable('staff_profile_permissions')) {
            Schema::create('staff_profile_permissions', function (Blueprint $t) {
                $t->id();
                $t->foreignId('profile_id')->constrained('staff_access_profiles')->restrictOnDelete();
                $t->string('permission_code', 80);
                $t->foreign('permission_code')->references('code')->on('staff_permissions')->restrictOnDelete();
                $t->unique(['profile_id', 'permission_code'], 'staff_profile_permission_unique');
            });
        }
        if (! Schema::hasTable('staff_profile_assignments')) {
            Schema::create('staff_profile_assignments', function (Blueprint $t) {
                $t->id();
                $t->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
                $t->foreignId('profile_id')->constrained('staff_access_profiles')->restrictOnDelete();
                $t->foreignId('assigned_by')->nullable()->constrained('users')->restrictOnDelete();
                $t->timestamp('assigned_at');
            });
        }
        foreach (['target_type', 'target_id', 'result'] as $column) {
            if (Schema::hasColumn('admin_activity_logs', $column)) {
                continue;
            }
            Schema::table('admin_activity_logs', function (Blueprint $t) use ($column) {
                match ($column) {
                    'target_type' => $t->string('target_type', 24)->nullable(),
                    'target_id' => $t->unsignedBigInteger('target_id')->nullable(),
                    'result' => $t->string('result', 20)->default('success'),
                };
            });
        }
        DB::transaction(function () {
            foreach (StaffPermissionCatalog::ITEMS as $code => $item) {
                DB::table('staff_permissions')->updateOrInsert(['code' => $code], ['module' => $item['module'], 'label' => $item['label']]);
            }
            // Preserve existing Government capabilities, explicitly and once, rather than a runtime role bypass.
            $staff = DB::table('users')->where('role', 'pemerintah')->where('is_active', true)->orderBy('id')->get(['id', 'name']);
            if ($staff->isEmpty()) {
                return;
            }
            $key = StaffPermissionCatalog::key('Petugas Pemerintah');
            $profile = DB::table('staff_access_profiles')->where('name_key', $key)->first();
            if (! $profile) {
                $id = DB::table('staff_access_profiles')->insertGetId(['name' => 'Petugas Pemerintah', 'name_key' => $key,
                    'description' => 'Dashboard dan pengelolaan data wilayah.', 'is_active' => true, 'ever_assigned' => false,
                    'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
                foreach (array_keys(StaffPermissionCatalog::ITEMS) as $code) {
                    DB::table('staff_profile_permissions')->insert(['profile_id' => $id, 'permission_code' => $code]);
                }
                DB::table('admin_activity_logs')->insert(['actor_id' => null, 'actor_name' => 'Sistem (migrasi hak akses)', 'subject_id' => null,
                    'subject_name' => 'Petugas Pemerintah', 'action' => 'access.profile.created', 'target_type' => 'profile', 'target_id' => $id,
                    'result' => 'success', 'changes' => json_encode(['profile_name' => ['before' => null, 'after' => 'Petugas Pemerintah']]), 'created_at' => now()]);
            } else {
                $id = $profile->id;
            }
            foreach ($staff as $user) {
                if (DB::table('staff_profile_assignments')->where('user_id', $user->id)->exists()) {
                    continue;
                }
                DB::table('staff_profile_assignments')->insert(['user_id' => $user->id, 'profile_id' => $id, 'assigned_by' => null, 'assigned_at' => now()]);
                DB::table('staff_access_profiles')->where('id', $id)->update(['ever_assigned' => true]);
                DB::table('admin_activity_logs')->insert(['actor_id' => null, 'actor_name' => 'Sistem (migrasi hak akses)', 'subject_id' => $user->id,
                    'subject_name' => $user->name, 'action' => 'access.profile.assigned', 'target_type' => 'profile', 'target_id' => $id, 'result' => 'success',
                    'changes' => json_encode(['profile_name' => ['before' => null, 'after' => 'Petugas Pemerintah']]), 'created_at' => now()]);
            }
        });
    }

    public function down(): void
    {
        if (DB::table('staff_access_profiles')->where('ever_assigned', true)->exists()
            || DB::table('admin_activity_logs')->where('action', 'like', 'access.%')->exists()) {
            throw new RuntimeException('Profil dan audit hak akses sudah digunakan. Gunakan migrasi aditif untuk mempertahankan riwayat.');
        }
        Schema::dropIfExists('staff_profile_assignments');
        Schema::dropIfExists('staff_profile_permissions');
        Schema::dropIfExists('staff_permissions');
        Schema::dropIfExists('staff_access_profiles');
        Schema::table('admin_activity_logs', fn (Blueprint $t) => $t->dropColumn(['target_type', 'target_id', 'result']));
    }
};
