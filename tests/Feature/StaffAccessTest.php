<?php

namespace Tests\Feature;

use App\Models\AdminActivityLog;
use App\Models\StaffAccessProfile;
use App\Models\User;
use App\Services\StaffAccessAudit;
use App\Services\StaffPermissionCatalog;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\PBI24Fixture;
use Tests\TestCase;

class StaffAccessTest extends TestCase
{
    use PBI24Fixture;

    private array $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'app.key' => 'base64:'.base64_encode(str_repeat('p', 32))]);
        DB::purge('sqlite');
        $this->withoutVite();
        $this->fixture = $this->buildAccessFixture();
        $this->actingAs($this->fixture['admin']);
    }

    public function test_tc_24_01_list_search_pagination_details_and_active_navigation(): void
    {
        $profile = $this->accessProfile();
        foreach (range(1, 11) as $n) {
            $this->accessProfile('Profil '.sprintf('%02d', $n));
        }
        $this->get('/admin/hak-akses?q=Verifikasi')->assertOk()->assertSee('Petugas Verifikasi')->assertViewHas('profiles', fn ($items) => $items->total() === 1);
        $this->get('/admin/hak-akses?page=2')->assertOk()->assertViewHas('profiles', fn ($items) => $items->currentPage() === 2);
        $this->get('/admin/hak-akses/'.$profile->id)->assertOk()->assertSee('Melihat daftar dan rincian wilayah');
        $this->get('/admin/hak-akses?q=tidak-ada')->assertSee('Tidak ada profil yang cocok.');
    }

    public function test_tc_24_02_create_profile_with_normalized_unique_name_and_audit(): void
    {
        $this->postJson('/admin/hak-akses', $this->profileInput(null, ['name' => '  Petugas   Verifikasi  ']))->assertOk();
        $profile = StaffAccessProfile::where('name', 'Petugas Verifikasi')->firstOrFail();
        $this->assertSame(StaffPermissionCatalog::key('petugas verifikasi'), $profile->name_key);
        $this->assertSame(['wilayah.view'], $profile->permissionCodes());
        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'access.profile.created', 'target_id' => $profile->id, 'result' => 'success']);
        $this->get('/admin/aktivitas')->assertOk()->assertSee('Tambah profil hak akses');
    }

    public function test_tc_24_03_edit_profile_replaces_permissions_and_preserves_account_data(): void
    {
        $profile = $this->accessProfile();
        $before = DB::table('users')->get()->toJson();
        $this->putJson('/admin/hak-akses/'.$profile->id, $this->profileInput($profile, ['description' => 'Tugas terbaru', 'permissions' => ['dashboard.view']]))->assertOk();
        $this->assertSame(['dashboard.view'], $profile->permissionCodes());
        $this->assertSame('Tugas terbaru', $profile->fresh()->description);
        $this->assertSame($before, DB::table('users')->get()->toJson());
    }

    public function test_tc_24_04_assign_one_profile_and_enforce_unique_user_constraint(): void
    {
        $a = $this->accessProfile();
        $b = $this->accessProfile('Petugas Dashboard', ['dashboard.view']);
        $this->postJson('/admin/hak-akses/penetapan', $this->assignmentInput($this->fixture['staff'], $a))->assertOk();
        $this->postJson('/admin/hak-akses/penetapan', $this->assignmentInput($this->fixture['staff'], $b))->assertOk();
        $this->assertSame(1, DB::table('staff_profile_assignments')->where('user_id', $this->fixture['staff']->id)->count());
        $this->assertTrue($a->fresh()->ever_assigned);
        $this->assertTrue($b->fresh()->ever_assigned);
        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'access.profile.assigned', 'subject_id' => $this->fixture['staff']->id]);
        $this->expectException(QueryException::class);
        DB::table('staff_profile_assignments')->insert($this->fixture['staff']->staffAssignment()->first()->only(['user_id', 'profile_id', 'assigned_by', 'assigned_at']));
    }

    public function test_tc_24_05_authorized_read_and_create_use_real_operational_endpoints(): void
    {
        $profile = $this->accessProfile('Petugas Input', ['wilayah.view', 'wilayah.create']);
        $this->postJson('/admin/hak-akses/penetapan', $this->assignmentInput($this->fixture['staff'], $profile))->assertOk();
        $this->actingAs($this->fixture['staff'])->get('/pemerintah/wilayah')->assertOk();
        $this->postJson('/pemerintah/wilayah', ['nama_wilayah' => 'Desa Baru', 'tingkat_wilayah' => 'desa_kelurahan', 'alamat_deskriptif' => 'Kabupaten Bandung, Jawa Barat'])->assertCreated();
        $this->assertDatabaseHas('wilayah', ['nama_wilayah' => 'Desa Baru', 'created_by' => $this->fixture['staff']->id]);
    }

    public function test_tc_24_06_revocation_applies_to_next_request_without_relogin(): void
    {
        $profile = $this->accessProfile();
        $this->postJson('/admin/hak-akses/penetapan', $this->assignmentInput($this->fixture['staff'], $profile))->assertOk();
        $this->actingAs($this->fixture['staff'])->get('/pemerintah/wilayah')->assertOk();
        $this->actingAs($this->fixture['admin'])->putJson('/admin/hak-akses/'.$profile->id, $this->profileInput($profile, ['permissions' => []]))->assertOk();
        $this->actingAs($this->fixture['staff'])->get('/pemerintah/wilayah')->assertForbidden();
        $this->getJson('/pemerintah/wilayah/data')->assertForbidden();
        $this->get('/peta-wilayah')->assertOk()->assertDontSee('>Data wilayah</a>', false);
    }

    public function test_tc_24_07_deactivation_removes_access_but_preserves_accounts_assignments_and_history(): void
    {
        $profile = $this->accessProfile();
        $this->postJson('/admin/hak-akses/penetapan', $this->assignmentInput($this->fixture['staff'], $profile))->assertOk();
        $users = DB::table('users')->get()->toJson();
        $this->putJson('/admin/hak-akses/'.$profile->id, $this->profileInput($profile, ['is_active' => 0]))->assertOk();
        $this->assertSame($users, DB::table('users')->get()->toJson());
        $this->assertDatabaseHas('staff_profile_assignments', ['profile_id' => $profile->id]);
        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'access.profile.deactivated', 'target_id' => $profile->id]);
        $this->actingAs($this->fixture['staff'])->get('/pemerintah/wilayah')->assertForbidden();
    }

    public function test_tc_24_08_delete_unused_profile_retains_audit_snapshot(): void
    {
        $this->postJson('/admin/hak-akses', $this->profileInput())->assertOk();
        $profile = StaffAccessProfile::where('name', 'Petugas Verifikasi')->firstOrFail();
        $this->deleteJson('/admin/hak-akses/'.$profile->id, $this->profileInput($profile))->assertOk();
        $this->assertDatabaseMissing('staff_access_profiles', ['id' => $profile->id]);
        $this->assertDatabaseMissing('staff_profile_permissions', ['profile_id' => $profile->id]);
        $log = AdminActivityLog::where('action', 'access.profile.deleted')->firstOrFail();
        $this->assertSame($profile->id, $log->target_id);
        $this->assertSame('Petugas Verifikasi', $log->subject_name);
        $this->assertSame(1, AdminActivityLog::where('action', 'access.profile.created')->where('target_id', $profile->id)->count());
    }

    public function test_tc_24_09_invalid_names_permissions_dependencies_and_confirmation_are_rejected(): void
    {
        $this->accessProfile();
        foreach ([['name' => ''], ['name' => ' petugas   VERIFIKASI '], ['permissions' => ['admin.users.delete']], ['permissions' => ['wilayah.delete']], ['confirmed' => 0]] as $extra) {
            $this->postJson('/admin/hak-akses', $this->profileInput(null, $extra))->assertUnprocessable();
        }
        $this->assertSame(2, StaffAccessProfile::count());
        $this->assertSame(5, DB::table('staff_permissions')->count());
    }

    public function test_tc_24_10_wrong_role_inactive_staff_and_inactive_profile_are_rejected(): void
    {
        $profile = $this->accessProfile();
        foreach (['society', 'admin', 'inactive'] as $key) {
            $this->postJson('/admin/hak-akses/penetapan', $this->assignmentInput($this->fixture[$key], $profile))->assertUnprocessable();
        }
        $profile->is_active = false;
        $profile->save();
        $this->postJson('/admin/hak-akses/penetapan', $this->assignmentInput($this->fixture['staff'], $profile))->assertUnprocessable();
        $this->assertSame(0, $profile->assignments()->count());
    }

    public function test_tc_24_11_current_and_historical_assignments_block_delete_even_when_inactive(): void
    {
        $profile = $this->accessProfile();
        $this->postJson('/admin/hak-akses/penetapan', $this->assignmentInput($this->fixture['staff'], $profile))->assertOk();
        $this->deleteJson('/admin/hak-akses/'.$profile->id, $this->profileInput($profile))->assertUnprocessable();
        $other = $this->accessProfile('Profil pengganti');
        $this->postJson('/admin/hak-akses/penetapan', $this->assignmentInput($this->fixture['staff'], $other))->assertOk();
        $this->assertSame(0, $profile->assignments()->count());
        $this->deleteJson('/admin/hak-akses/'.$profile->id, $this->profileInput($profile))->assertUnprocessable();
        $this->assertDatabaseHas('staff_access_profiles', ['id' => $profile->id]);
    }

    public function test_tc_24_12_non_admin_cannot_manage_or_self_escalate_and_inactive_admin_is_rejected(): void
    {
        $profile = $this->accessProfile();
        foreach (['staff', 'society'] as $key) {
            $this->actingAs($this->fixture[$key])->get('/admin/hak-akses')->assertForbidden();
            $this->postJson('/admin/hak-akses', $this->profileInput())->assertForbidden();
            $this->putJson('/admin/hak-akses/'.$profile->id, $this->profileInput($profile))->assertForbidden();
            $this->deleteJson('/admin/hak-akses/'.$profile->id, $this->profileInput($profile))->assertForbidden();
            $this->postJson('/admin/hak-akses/penetapan', $this->assignmentInput($this->fixture['staff'], $profile))->assertForbidden();
        }
        $this->fixture['admin']->is_active = false;
        $this->fixture['admin']->save();
        $this->actingAs($this->fixture['admin'])->postJson('/admin/hak-akses', $this->profileInput())->assertUnauthorized();
    }

    public function test_tc_24_13_direct_urls_and_ungranted_mutations_are_denied_by_server(): void
    {
        $profile = $this->accessProfile();
        $this->postJson('/admin/hak-akses/penetapan', $this->assignmentInput($this->fixture['staff'], $profile))->assertOk();
        $this->actingAs($this->fixture['staff'])->get('/pemerintah/dashboard')->assertForbidden();
        $this->getJson('/pemerintah/dashboard/data')->assertForbidden();
        $this->postJson('/pemerintah/wilayah', [])->assertForbidden();
        $this->putJson('/pemerintah/wilayah/'.$this->fixture['region'], [])->assertForbidden();
        $this->deleteJson('/pemerintah/wilayah/'.$this->fixture['region'])->assertForbidden();
    }

    public function test_tc_24_14_preview_and_cancellation_have_no_writes(): void
    {
        $profile = $this->accessProfile();
        $count = AdminActivityLog::count();
        $this->post('/admin/hak-akses/tinjau', $this->profileInput($profile, ['profile_id' => $profile->id, 'permissions' => ['dashboard.view']]))->assertOk()->assertSee('Tinjau perubahan profil');
        $this->get('/admin/hak-akses/'.$profile->id.'/hapus')->assertOk();
        $this->get('/admin/hak-akses/'.$profile->id)->assertOk();
        $this->assertSame(['wilayah.view'], $profile->permissionCodes());
        $this->assertSame($count, AdminActivityLog::count());
    }

    public function test_tc_24_15_assignment_committed_after_initial_delete_screen_survives_final_check(): void
    {
        $profile = $this->accessProfile();
        $stale = $this->profileInput($profile);
        $this->get('/admin/hak-akses/'.$profile->id.'/hapus')->assertOk();
        $this->postJson('/admin/hak-akses/penetapan', $this->assignmentInput($this->fixture['staff'], $profile))->assertOk();
        $this->deleteJson('/admin/hak-akses/'.$profile->id, $stale)->assertUnprocessable();
        $this->assertDatabaseHas('staff_profile_assignments', ['user_id' => $this->fixture['staff']->id, 'profile_id' => $profile->id]);
        $this->assertDatabaseHas('staff_access_profiles', ['id' => $profile->id]);
        // Even an attempted direct delete outside the service is blocked by the database FK.
        $this->expectException(QueryException::class);
        DB::table('staff_access_profiles')->where('id', $profile->id)->delete();
    }

    public function test_tc_24_16_audit_failure_rolls_back_create_permissions_assignment_deactivation_and_delete(): void
    {
        $profile = $this->accessProfile();
        $other = $this->accessProfile('Petugas lainnya');
        $this->mock(StaffAccessAudit::class, fn ($mock) => $mock->shouldReceive('record')->andThrow(new \RuntimeException('audit-test-failure')));
        $this->postJson('/admin/hak-akses', $this->profileInput(null, ['name' => 'Gagal Tambah']))->assertStatus(503);
        $this->assertDatabaseMissing('staff_access_profiles', ['name' => 'Gagal Tambah']);
        $this->putJson('/admin/hak-akses/'.$profile->id, $this->profileInput($profile, ['permissions' => ['dashboard.view'], 'is_active' => 0]))->assertStatus(503);
        $this->assertTrue($profile->fresh()->is_active);
        $this->assertSame(['wilayah.view'], $profile->permissionCodes());
        $old = $this->fixture['staff']->staffAssignment()->value('profile_id');
        $this->postJson('/admin/hak-akses/penetapan', $this->assignmentInput($this->fixture['staff'], $profile))->assertStatus(503);
        $this->assertSame($old, $this->fixture['staff']->staffAssignment()->value('profile_id'));
        $this->assertFalse($profile->fresh()->ever_assigned);
        $this->deleteJson('/admin/hak-akses/'.$other->id, $this->profileInput($other))->assertStatus(503);
        $this->assertDatabaseHas('staff_access_profiles', ['id' => $other->id]);
        $this->assertSame(['wilayah.view'], $other->permissionCodes());
    }

    public function test_new_unassigned_staff_have_no_implicit_grants_and_login_falls_back_to_public_map(): void
    {
        $new = new User(['name' => 'Petugas Baru', 'email' => 'new@example.test', 'password' => 'password-khusus-uji']);
        $new->role = 'pemerintah';
        $new->is_active = true;
        $new->save();
        $this->actingAs($new)->get('/pemerintah/wilayah')->assertForbidden();
        $this->post('/logout');
        $this->post('/login', ['email' => $new->email, 'password' => 'password-khusus-uji'])->assertRedirect('/peta-wilayah');
    }

    public function test_pbi23_role_status_and_admin_self_protection_remain_effective(): void
    {
        $admin = $this->fixture['admin'];
        $staff = $this->fixture['staff'];
        $input = ['name' => $staff->name, 'email' => $staff->email, 'role' => 'masyarakat', 'is_active' => 1];
        $this->put('/admin/pengguna/'.$staff->id, $input)->assertRedirect();
        $this->actingAs($staff->fresh())->get('/pemerintah/wilayah')->assertForbidden();
        $this->assertDatabaseHas('staff_profile_assignments', ['user_id' => $staff->id]);
        $this->actingAs($admin)->put('/admin/pengguna/'.$admin->id, ['name' => $admin->name, 'email' => $admin->email, 'role' => 'masyarakat', 'is_active' => 0])->assertSessionHasErrors('role');
        $this->assertSame('admin', $admin->fresh()->role);
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_preview_does_not_require_final_confirmation_but_the_mutation_does(): void
    {
        $input = $this->profileInput();
        unset($input['confirmed']);
        $this->post('/admin/hak-akses/tinjau', $input)->assertOk()->assertSee('Tinjau perubahan profil');
        $this->assertDatabaseMissing('staff_access_profiles', ['name' => 'Petugas Verifikasi']);
        $this->postJson('/admin/hak-akses', $input)->assertUnprocessable();
    }

    public function test_admin_authority_is_rechecked_inside_the_transaction(): void
    {
        DB::table('users')->where('id', $this->fixture['admin']->id)->update(['is_active' => false]);
        $this->postJson('/admin/hak-akses', $this->profileInput())->assertForbidden();
        $this->assertDatabaseMissing('staff_access_profiles', ['name' => 'Petugas Verifikasi']);
    }

    public function test_inactive_profile_and_inactive_staff_still_block_profile_deletion(): void
    {
        $profile = $this->accessProfile();
        $this->postJson('/admin/hak-akses/penetapan', $this->assignmentInput($this->fixture['staff'], $profile))->assertOk();
        DB::table('users')->where('id', $this->fixture['staff']->id)->update(['is_active' => false]);
        $this->putJson('/admin/hak-akses/'.$profile->id, $this->profileInput($profile, ['is_active' => 0]))->assertOk();
        $this->deleteJson('/admin/hak-akses/'.$profile->id, $this->profileInput($profile))->assertUnprocessable();
        $this->assertDatabaseHas('staff_profile_assignments', ['profile_id' => $profile->id]);
    }

    public function test_migration_can_resume_without_duplicate_profiles_assignments_or_audit(): void
    {
        $before = [StaffAccessProfile::count(), DB::table('staff_profile_assignments')->count(), AdminActivityLog::count()];
        (require database_path('migrations/2026_11_27_000001_create_staff_access_profiles.php'))->up();
        $this->assertSame($before, [StaffAccessProfile::count(), DB::table('staff_profile_assignments')->count(), AdminActivityLog::count()]);
    }
}
