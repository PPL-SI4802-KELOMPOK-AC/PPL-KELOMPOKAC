<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        DB::purge('sqlite');
        $this->withoutVite();
        Schema::create('users', function (Blueprint $table) {
            $table->id(); $table->string('name', 150); $table->string('email', 254)->unique();
            $table->string('password'); $table->string('role'); $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('wilayah', function (Blueprint $table) {
            $table->id(); $table->string('nama_wilayah');
        });
        Schema::create('pengajuan_plts', function (Blueprint $table) {
            $table->id(); $table->string('status_terkini');
        });
        $migration = require database_path('migrations/2026_11_07_000002_create_admin_activity_logs_table.php');
        $migration->up();
    }

    private function account(string $role = 'admin', array $overrides = []): User
    {
        $user = new User(array_merge(['name' => 'Akun '.ucfirst($role),
            'email' => uniqid().'@example.test', 'password' => 'password-pengujian-123'], $overrides));
        $user->role = $role;
        $user->is_active = true;
        $user->save();

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge(['name' => 'Pengguna Baru', 'email' => 'baru@example.test',
            'role' => 'masyarakat', 'is_active' => '1', 'password' => 'password-baru-123',
            'password_confirmation' => 'password-baru-123'], $overrides);
    }

    public function test_guests_and_other_roles_cannot_read_or_write_admin_users(): void
    {
        $target = $this->account('masyarakat');
        $this->get('/admin/pengguna')->assertRedirect('/login');
        $this->postJson('/admin/pengguna', $this->payload())->assertUnauthorized();
        foreach (['masyarakat', 'pemerintah'] as $role) {
            $this->actingAs($this->account($role));
            $this->get('/admin')->assertForbidden();
            $this->get('/admin/pengguna')->assertForbidden();
            $this->get('/admin/pengguna/tambah')->assertForbidden();
            $this->get('/admin/pengguna/'.$target->id.'/edit')->assertForbidden();
            $this->post('/admin/pengguna', $this->payload())->assertForbidden();
            $this->put('/admin/pengguna/'.$target->id, $this->payload())->assertForbidden();
        }
        $this->assertDatabaseMissing('users', ['email' => 'baru@example.test']);
    }

    public function test_admin_sees_real_counts_filters_and_forms(): void
    {
        $admin = $this->account();
        $person = $this->account('masyarakat', ['name' => 'Warga Unik', 'email' => 'warga@example.test']);
        $this->account('pemerintah', ['name' => 'Petugas Wilayah']);
        DB::table('wilayah')->insert(['nama_wilayah' => 'Desa Referensi']);
        $this->actingAs($admin)->get('/admin')->assertRedirect('/admin/ringkasan');
        $this->get('/admin/pengguna')->assertOk()->assertSee('Kelola data operasional')
            ->assertSee('Warga Unik')->assertSee('Petugas Wilayah')->assertDontSee('Data contoh')
            ->assertViewHas('regionCount', 1)->assertViewHas('counts', fn ($counts) => $counts->sum() === 3);
        $this->get('/admin/pengguna?q=warga&role=masyarakat')->assertOk()
            ->assertViewHas('users', fn ($users) => $users->total() === 1 && $users->first()->id === $person->id)
            ->assertViewHas('counts', fn ($counts) => $counts->sum() === 3);
        $this->get('/admin/pengguna?q=warga%40example.test')->assertOk()
            ->assertViewHas('users', fn ($users) => $users->total() === 1);
        $this->get('/admin/pengguna?q=%25')->assertOk()
            ->assertViewHas('users', fn ($users) => $users->total() === 0);
        $this->get('/admin/pengguna/tambah')->assertOk()->assertSee('Informasi akun');
        $this->get('/admin/pengguna/'.$person->id.'/edit')->assertOk()->assertSee('warga@example.test');
    }

    public function test_list_paginates_and_escapes_user_supplied_html(): void
    {
        $admin = $this->account();
        foreach (range(1, 11) as $i) {
            $this->account('masyarakat', ['name' => sprintf('Warga %02d', $i)]);
        }
        $this->actingAs($admin)->get('/admin/pengguna?page=2&role=masyarakat')->assertOk()
            ->assertViewHas('users', fn ($users) => $users->total() === 11 && $users->count() === 1)
            ->assertSee('role=masyarakat', false);
        $this->account('masyarakat', ['name' => '<script>alert(1)</script>']);
        $this->get('/admin/pengguna?q=script')->assertOk()
            ->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_admin_can_create_each_role_with_a_hashed_password(): void
    {
        $this->actingAs($this->account());
        foreach (['masyarakat', 'pemerintah', 'admin'] as $role) {
            $email = $role.'@example.test';
            $this->post('/admin/pengguna', $this->payload(['email' => $email, 'role' => $role,
                'id' => 999999, 'created_at' => '2000-01-01']))->assertRedirect('/admin/pengguna');
            $user = User::where('email', $email)->firstOrFail();
            $this->assertSame($role, $user->role);
            $this->assertTrue($user->is_active);
            $this->assertTrue(Hash::check('password-baru-123', $user->password));
            $this->assertNotSame(999999, $user->id);
        }
    }

    public function test_invalid_duplicate_and_unconfirmed_values_are_rejected(): void
    {
        $admin = $this->account('admin', ['email' => 'dipakai@example.test']);
        $this->actingAs($admin)->postJson('/admin/pengguna', $this->payload([
            'email' => $admin->email, 'role' => 'superadmin', 'is_active' => 'invalid',
            'password' => 'pendek', 'password_confirmation' => 'berbeda',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['email', 'role', 'is_active', 'password']);
        $this->assertSame(1, User::count());
    }

    public function test_edit_can_change_role_disable_access_and_keep_or_replace_password(): void
    {
        $admin = $this->account();
        $target = $this->account('masyarakat', ['email' => 'target@example.test']);
        $hash = $target->password;
        $this->actingAs($admin)->put('/admin/pengguna/'.$target->id, $this->payload([
            'email' => $target->email, 'role' => 'pemerintah', 'is_active' => '0',
            'password' => '', 'password_confirmation' => '',
        ]))->assertRedirect('/admin/pengguna');
        $this->assertSame('pemerintah', $target->fresh()->role);
        $this->assertFalse($target->fresh()->is_active);
        $this->assertSame($hash, $target->fresh()->password);
        $this->put('/admin/pengguna/'.$target->id, $this->payload(['email' => $target->email]))
            ->assertRedirect('/admin/pengguna');
        $this->assertTrue($target->fresh()->is_active);
        $this->assertTrue(Hash::check('password-baru-123', $target->fresh()->password));
    }

    public function test_admin_cannot_disable_or_demote_their_own_account(): void
    {
        $admin = $this->account();
        $this->actingAs($admin);
        foreach ([['role' => 'masyarakat'], ['is_active' => '0']] as $changes) {
            $this->putJson('/admin/pengguna/'.$admin->id, $this->payload(array_merge([
                'role' => 'admin', 'email' => $admin->email,
            ], $changes)))->assertUnprocessable()->assertJsonValidationErrors('role');
        }
        $this->assertSame('admin', $admin->fresh()->role);
        $this->assertTrue($admin->fresh()->is_active);
        $this->put('/admin/pengguna/'.$admin->id, $this->payload([
            'email' => $admin->email, 'role' => 'admin', 'name' => 'Nama Admin Baru',
            'password' => '', 'password_confirmation' => '',
        ]))->assertRedirect('/admin/pengguna');
        $this->assertSame('Nama Admin Baru', $admin->fresh()->name);
    }

    public function test_inactive_accounts_cannot_log_in_and_active_admin_lands_in_admin_area(): void
    {
        $admin = $this->account('admin', ['email' => 'admin@example.test']);
        $this->post('/login', ['email' => $admin->email, 'password' => 'password-pengujian-123'])
            ->assertRedirect('/admin/ringkasan');
        $this->post('/logout');
        $admin->is_active = false;
        $admin->save();
        $this->from('/login')->post('/login', [
            'email' => $admin->email, 'password' => 'password-pengujian-123',
        ])->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_disabling_an_account_also_rejects_an_existing_session_on_next_request(): void
    {
        $person = $this->account('masyarakat');
        $this->actingAs($person)->get('/peta-wilayah')->assertOk();
        $person->is_active = false;
        $person->save();
        $this->get('/peta-wilayah')->assertRedirect('/login');
        $this->assertGuest();
        $admin = $this->account();
        $admin->is_active = false;
        $admin->save();
        $this->actingAs($admin)->getJson('/admin/pengguna')->assertUnauthorized();
        $this->assertGuest();
    }

    public function test_intended_admin_links_are_allowed_only_for_admin(): void
    {
        $admin = $this->account();
        $intended = url('/admin/pengguna/tambah');
        $this->withSession(['url.intended' => $intended])->post('/login', [
            'email' => $admin->email, 'password' => 'password-pengujian-123',
        ])->assertRedirect($intended);
        $this->post('/logout');
        $person = $this->account('masyarakat');
        $this->withSession(['url.intended' => $intended])->post('/login', [
            'email' => $person->email, 'password' => 'password-pengujian-123',
        ])->assertRedirect('/pengajuan/lokasi');
    }

    public function test_first_admin_command_hashes_password_and_does_not_replace_an_existing_account(): void
    {
        $this->artisan('admin:create', ['email' => 'pertama@example.test', '--name' => 'Admin Pertama'])
            ->expectsQuestion('Kata sandi (8–72 karakter)', 'Admin123')
            ->expectsQuestion('Konfirmasi kata sandi', 'Admin123')
            ->assertSuccessful();
        $admin = User::where('email', 'pertama@example.test')->firstOrFail();
        $this->assertSame('admin', $admin->role);
        $this->assertTrue($admin->is_active);
        $this->assertTrue(Hash::check('Admin123', $admin->password));
        $this->artisan('admin:create', ['email' => 'pertama@example.test', '--name' => 'Duplikat'])
            ->expectsQuestion('Kata sandi (8–72 karakter)', 'Admin123')
            ->expectsQuestion('Konfirmasi kata sandi', 'Admin123')
            ->assertFailed();
        $this->assertSame(1, User::count());
        $this->assertSame('Admin Pertama', $admin->fresh()->name);
        $this->assertDatabaseHas('admin_activity_logs', ['actor_id' => null, 'actor_name' => 'Sistem (terminal)', 'subject_id' => $admin->id, 'action' => 'user.created']);
        $this->assertSame(1, \App\Models\AdminActivityLog::count());
    }

    public function test_eight_character_password_is_accepted_and_seven_is_rejected_on_create_and_edit(): void
    {
        $this->actingAs($this->account());
        $this->postJson('/admin/pengguna', $this->payload([
            'password' => 'Abcd123', 'password_confirmation' => 'Abcd123',
        ]))->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->post('/admin/pengguna', $this->payload([
            'password' => 'Abcd1234', 'password_confirmation' => 'Abcd1234',
        ]))->assertRedirect('/admin/pengguna');
        $user = User::where('email', 'baru@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('Abcd1234', $user->password));
        $this->putJson('/admin/pengguna/'.$user->id, $this->payload([
            'password' => 'Xyz1234', 'password_confirmation' => 'Xyz1234',
        ]))->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->put('/admin/pengguna/'.$user->id, $this->payload([
            'password' => 'Xyz12345', 'password_confirmation' => 'Xyz12345',
        ]))->assertRedirect('/admin/pengguna');
        $this->assertTrue(Hash::check('Xyz12345', $user->fresh()->password));
        $this->get('/admin/pengguna/tambah')->assertOk()
            ->assertSee('minlength="8"', false)->assertDontSee('minlength="12"', false);
    }

    public function test_access_filter_combines_with_role_and_search_without_changing_summary(): void
    {
        $admin = $this->account();
        $inactive = $this->account('masyarakat', ['name' => 'Warga 0', 'email' => 'nonaktif@example.test']);
        $inactive->is_active = false;
        $inactive->save();
        $this->account('masyarakat', ['name' => 'Warga Aktif']);
        $this->actingAs($admin)->get('/admin/pengguna?role=masyarakat&access=inactive&q=0')->assertOk()
            ->assertViewHas('users', fn ($users) => $users->total() === 1 && $users->first()->id === $inactive->id)
            ->assertViewHas('counts', fn ($counts) => $counts->sum() === 3);
        $this->get('/admin/pengguna?role=masyarakat&access=active')->assertOk()
            ->assertViewHas('users', fn ($users) => $users->total() === 1 && $users->first()->is_active);
        $this->get('/admin/pengguna?access=invalid')->assertSessionHasErrors('access');
        $this->get('/admin/pengguna?role=masyarakat&access=inactive&page=999')
            ->assertRedirect(route('admin.users.index', ['role' => 'masyarakat', 'access' => 'inactive', 'page' => 1]));
    }

    public function test_admin_command_rejects_seven_character_password_without_creating_a_user(): void
    {
        $this->artisan('admin:create', ['email' => 'pendek@example.test', '--name' => 'Admin Uji'])
            ->expectsQuestion('Kata sandi (8–72 karakter)', 'Abcd123')
            ->expectsQuestion('Konfirmasi kata sandi', 'Abcd123')
            ->assertFailed();
        $this->assertDatabaseMissing('users', ['email' => 'pendek@example.test']);
    }


    public function test_operational_summary_counts_database_rows_and_excludes_drafts(): void
    {
        $admin = $this->account();
        $inactive = $this->account('masyarakat');
        $inactive->is_active = false;
        $inactive->save();
        $this->account('pemerintah');
        DB::table('wilayah')->insert([['nama_wilayah' => 'Desa A'], ['nama_wilayah' => 'Desa B']]);
        foreach (['draft', 'draft', 'diajukan', 'diajukan', 'diperiksa', 'diverifikasi', 'diproses', 'selesai', 'selesai', 'ditolak'] as $status) {
            DB::table('pengajuan_plts')->insert(['status_terkini' => $status]);
        }
        $this->actingAs($admin)->get('/admin/ringkasan')->assertOk()
            ->assertViewHas('submitted', 8)->assertViewHas('inProgress', 5)
            ->assertViewHas('regionCount', 2)->assertViewHas('activeUsers', 2)
            ->assertViewHas('roles', fn ($roles) => $roles->sum() === 3)
            ->assertSee('Belum ada aktivitas tercatat.')->assertDontSee('Segera hadir');
        $this->get('/admin/aktivitas')->assertOk()->assertSee('Belum ada aktivitas tercatat.');
    }

    public function test_operational_pages_are_admin_only(): void
    {
        foreach (['/admin/ringkasan', '/admin/aktivitas'] as $path) {
            $this->get($path)->assertRedirect('/login');
            $this->getJson($path)->assertUnauthorized();
        }
        foreach (['masyarakat', 'pemerintah'] as $role) {
            $this->actingAs($this->account($role));
            $this->get('/admin/ringkasan')->assertForbidden();
            $this->get('/admin/aktivitas')->assertForbidden();
        }
    }

    public function test_user_changes_are_logged_atomically_without_passwords_or_hashes(): void
    {
        $admin = $this->account();
        $this->actingAs($admin)->post('/admin/pengguna', $this->payload())->assertRedirect();
        $target = User::where('email', 'baru@example.test')->firstOrFail();
        $created = \App\Models\AdminActivityLog::firstOrFail();
        $this->assertSame($admin->id, $created->actor_id);
        $this->assertSame($target->id, $created->subject_id);
        $this->assertSame('user.created', $created->action);
        $this->assertSame('masyarakat', $created->changes['role']['after']);
        $raw = DB::table('admin_activity_logs')->value('changes');
        $this->assertStringNotContainsString('password-baru-123', $raw);
        $this->assertStringNotContainsString($target->password, $raw);
        $this->put('/admin/pengguna/'.$target->id, $this->payload([
            'name' => 'Nama Diperbarui', 'role' => 'pemerintah', 'is_active' => '0',
            'password' => '', 'password_confirmation' => '',
        ]))->assertRedirect();
        $updated = \App\Models\AdminActivityLog::latest('id')->firstOrFail();
        $this->assertSame('user.updated', $updated->action);
        $this->assertSame(['before' => 'masyarakat', 'after' => 'pemerintah'], $updated->changes['role']);
        $this->assertSame(['before' => true, 'after' => false], $updated->changes['is_active']);
        $this->assertArrayNotHasKey('password_changed', $updated->changes);
        $this->get('/admin/aktivitas')->assertOk()->assertSee('Nama Diperbarui')
            ->assertSee('Lihat perubahan')->assertDontSee('password-baru-123')->assertDontSee($target->password);
        $this->get('/admin/ringkasan')->assertOk()
            ->assertViewHas('recentActivities', fn ($rows) => $rows->count() === 2 && $rows->first()->id === $updated->id);
    }

    public function test_no_op_and_invalid_user_updates_do_not_create_activity(): void
    {
        $admin = $this->account();
        $target = $this->account('masyarakat', ['name' => 'Pengguna Baru', 'email' => 'baru@example.test']);
        $this->actingAs($admin)->put('/admin/pengguna/'.$target->id, $this->payload([
            'password' => '', 'password_confirmation' => '',
        ]))->assertRedirect();
        $this->assertSame(0, \App\Models\AdminActivityLog::count());
        $this->putJson('/admin/pengguna/'.$admin->id, $this->payload([
            'email' => $admin->email, 'role' => 'masyarakat',
        ]))->assertUnprocessable();
        $this->assertSame(0, \App\Models\AdminActivityLog::count());
        $this->put('/admin/pengguna/'.$target->id, $this->payload())->assertRedirect();
        $changes = \App\Models\AdminActivityLog::firstOrFail()->changes;
        $this->assertSame(['password_changed' => true], $changes);
    }

    public function test_failure_to_write_activity_rolls_back_account_creation_and_edit(): void
    {
        $admin = $this->account();
        $target = $this->account('masyarakat', ['name' => 'Nama Asli']);
        Schema::drop('admin_activity_logs');
        $this->actingAs($admin)->postJson('/admin/pengguna', $this->payload())->assertStatus(503);
        $this->assertDatabaseMissing('users', ['email' => 'baru@example.test']);
        $this->putJson('/admin/pengguna/'.$target->id, $this->payload([
            'email' => $target->email, 'name' => 'Tidak Boleh Tersimpan',
        ]))->assertStatus(503);
        $this->assertSame('Nama Asli', $target->fresh()->name);
    }

    public function test_activity_filters_use_wib_day_boundaries_and_keep_overall_counts(): void
    {
        $admin = $this->account();
        foreach (['2026-11-06 16:59:59', '2026-11-06 17:00:00', '2026-11-07 16:59:59', '2026-11-07 17:00:00'] as $time) {
            DB::table('admin_activity_logs')->insert([
                'actor_id' => $admin->id, 'actor_name' => 'Admin 0', 'subject_id' => $admin->id,
                'subject_name' => 'Akun Target', 'action' => 'user.updated', 'changes' => '{}', 'created_at' => $time,
            ]);
        }
        $this->actingAs($admin)->get('/admin/aktivitas?from=2026-11-07&until=2026-11-07&action=user.updated&q=0')
            ->assertOk()->assertViewHas('totalActivities', 4)
            ->assertViewHas('activities', fn ($rows) => $rows->total() === 2);
        $this->get('/admin/aktivitas?q=%25')->assertOk()
            ->assertViewHas('activities', fn ($rows) => $rows->total() === 0);
        $this->get('/admin/aktivitas?action=invalid')->assertSessionHasErrors('action');
        $this->get('/admin/aktivitas?from=2026-11-08&until=2026-11-07')->assertSessionHasErrors('until');
        $this->get('/admin/aktivitas?until=2026-11-07')->assertOk()
            ->assertViewHas('activities', fn ($rows) => $rows->total() === 3);
    }

    public function test_activity_pagination_preserves_filters_and_escapes_untrusted_names(): void
    {
        $admin = $this->account();
        foreach (range(1, 17) as $i) {
            DB::table('admin_activity_logs')->insert([
                'actor_id' => $admin->id, 'actor_name' => '<script>alert(1)</script>',
                'subject_id' => $admin->id, 'subject_name' => 'Pengguna '.$i,
                'action' => 'user.updated', 'changes' => '{}', 'created_at' => '2026-11-07 01:00:00',
            ]);
        }
        $this->actingAs($admin)->get('/admin/aktivitas?action=user.updated&page=2')->assertOk()
            ->assertViewHas('activities', fn ($rows) => $rows->total() === 17 && $rows->count() === 2)
            ->assertSee('action=user.updated', false)->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
        $this->get('/admin/aktivitas?action=user.updated&page=999')
            ->assertRedirect(route('admin.activities.index', ['action' => 'user.updated', 'page' => 2]));
        $this->get('/admin/ringkasan')->assertOk()
            ->assertViewHas('recentActivities', fn ($rows) => $rows->count() === 5);
    }

    public function test_additive_access_migration_preserves_existing_users_and_can_roll_back(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_active'));
        $id = DB::table('users')->insertGetId(['name' => 'Akun Lama', 'email' => 'lama@example.test',
            'password' => 'hash-asli', 'role' => 'pemerintah']);
        $migration = require database_path('migrations/2026_11_07_000001_add_is_active_to_users_table.php');
        $migration->up();
        $this->assertDatabaseHas('users', ['id' => $id, 'password' => 'hash-asli', 'role' => 'pemerintah', 'is_active' => 1]);
        $migration->down();
        $this->assertFalse(Schema::hasColumn('users', 'is_active'));
        $this->assertDatabaseHas('users', ['id' => $id, 'email' => 'lama@example.test']);
    }
}
