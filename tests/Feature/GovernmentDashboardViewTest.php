<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GovernmentDashboardViewTest extends TestCase
{
    private const URL = '/pemerintah/dashboard/tampilan';

    private const MIGRATION = 'database/migrations/2026_11_28_000001_create_government_dashboard_views_table.php';

    private const EMPTY_FILTERS = ['year' => null, 'kabupaten' => null, 'kecamatan' => null, 'desa' => null, 'kondisi' => null];

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => true,
            'app.key' => 'base64:'.base64_encode(str_repeat('v', 32)),
        ]);
        DB::purge('sqlite');
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        (require base_path(self::MIGRATION))->up();
    }

    public function test_sqlite_migration_can_roll_back_and_run_again_without_removing_users(): void
    {
        $owner = $this->account('migration');
        (require base_path(self::MIGRATION))->down();
        $this->assertFalse(Schema::hasTable('government_dashboard_views'));
        $this->artisan('migrate', ['--database' => 'sqlite', '--path' => self::MIGRATION])->assertExitCode(0);
        $this->assertTrue(Schema::hasTable('government_dashboard_views'));
        $this->actingAs($owner)->postJson(self::URL, ['name' => 'SQLite'])->assertCreated();

        $this->artisan('migrate:rollback', ['--database' => 'sqlite', '--path' => self::MIGRATION, '--step' => 1])->assertExitCode(0);
        $this->assertFalse(Schema::hasTable('government_dashboard_views'));
        $this->assertDatabaseHas('users', ['id' => $owner->id]);
        $this->artisan('migrate', ['--database' => 'sqlite', '--path' => self::MIGRATION])->assertExitCode(0);
        $this->assertDatabaseCount('government_dashboard_views', 0);
    }

    public function test_unique_constraint_is_enforced_by_sqlite(): void
    {
        $first = $this->account('first-index');
        $second = $this->account('second-index');
        $view = ['name' => 'Review', 'normalized_name' => 'review'];
        $first->governmentDashboardViews()->create($view);
        $second->governmentDashboardViews()->create($view);
        $this->assertDatabaseCount('government_dashboard_views', 2);
        $this->expectException(UniqueConstraintViolationException::class);
        $first->governmentDashboardViews()->create($view);
    }

    public function test_mysql_migration_emits_one_create_with_inline_constraints_without_connecting(): void
    {
        $default = DB::getDefaultConnection();
        DB::extend('pbi19_offline_mysql', fn () => new MySqlConnection(
            fn () => throw new \LogicException('Offline DDL test must not open PDO.'),
            'offline', '', ['driver' => 'mysql'],
        ));
        config(['database.connections.pbi19_offline_mysql' => ['driver' => 'pbi19_offline_mysql']]);
        DB::setDefaultConnection('pbi19_offline_mysql');
        try {
            $queries = DB::connection()->pretend(fn () => (require base_path(self::MIGRATION))->up());
        } finally {
            DB::setDefaultConnection($default);
            DB::purge('pbi19_offline_mysql');
        }

        $this->assertCount(1, $queries);
        $ddl = $queries[0]['query'];
        $this->assertStringStartsWith('CREATE TABLE `government_dashboard_views`', $ddl);
        $this->assertStringContainsString('`user_id` BIGINT UNSIGNED NOT NULL', $ddl);
        $this->assertStringContainsString('`normalized_name` VARBINARY(1024) NOT NULL', $ddl);
        $this->assertStringContainsString('UNIQUE KEY `government_dashboard_views_user_normalized_unique` (`user_id`, `normalized_name`)', $ddl);
        $this->assertStringContainsString('FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE', $ddl);
    }

    public function test_case_folded_names_keep_accents_distinct(): void
    {
        $this->actingAs($this->account('unicode'));
        $this->postJson(self::URL, ['name' => 'Straße'])->assertCreated();
        $this->postJson(self::URL, ['name' => 'STRASSE'])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson(self::URL, ['name' => 'Énergie'])->assertCreated();
        $this->postJson(self::URL, ['name' => 'Energie'])->assertCreated();
    }

    public function test_foreign_key_rejects_a_missing_owner(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('FOREIGN KEY constraint failed');
        DB::table('government_dashboard_views')->insert([
            'user_id' => 999, 'name' => 'Review', 'normalized_name' => 'review',
        ]);
    }

    private function account(string $label, string $role = 'pemerintah'): User
    {
        $user = new User(['name' => $label, 'email' => $label.'@example.test', 'password' => 'local-fixture']);
        $user->role = $role;
        $user->is_active = true;
        $user->save();

        return $user;
    }

    public function test_crud_round_trips_all_filters_and_can_clear_them(): void
    {
        $user = $this->account('owner');
        $filters = ['year' => 2026, 'kabupaten' => '3278', 'kecamatan' => '32.78.01', 'desa' => '32.78.01.1004', 'kondisi' => 'unknown'];
        $created = $this->actingAs($user)->postJson(self::URL, ['name' => '  Review   Bandung  '] + $filters)
            ->assertCreated()->assertJsonPath('data.name', 'Review Bandung')
            ->assertJsonMissingPath('data.user_id')->assertJsonMissingPath('data.normalized_name');
        $url = self::URL.'/'.$created->json('data.id');
        $this->assertDatabaseHas('government_dashboard_views', ['user_id' => $user->id, 'normalized_name' => 'review bandung'] + $filters);
        $this->getJson(self::URL)->assertOk()->assertJsonPath('data.0.filter_labels', [
            'year' => 'Tahun 2026', 'kabupaten' => 'Kota Tasikmalaya', 'kecamatan' => 'Cihideung',
            'desa' => 'Argasari', 'kondisi' => 'Data belum tersedia',
        ])->assertJsonMissingPath('data.0.user_id');
        $this->getJson($url)->assertOk()->assertJsonPath('dashboard_url', route('pemerintah.dashboard', $filters));
        $this->putJson($url, ['name' => 'REVIEW BANDUNG'] + self::EMPTY_FILTERS)
            ->assertOk()->assertJsonPath('data.name', 'REVIEW BANDUNG')->assertJsonPath('data.kondisi', null);
        $this->getJson($url)->assertOk()->assertJsonPath('dashboard_url', route('pemerintah.dashboard'));
        $this->getJson(self::URL)->assertOk()->assertJsonPath('data.0.filter_labels', [
            'year' => 'Semua periode', 'kabupaten' => 'Semua kabupaten/kota', 'kecamatan' => 'Semua kecamatan',
            'desa' => 'Semua desa/kelurahan', 'kondisi' => 'Semua kondisi listrik',
        ]);
        $this->deleteJson($url)->assertNoContent();
        $this->getJson($url)->assertNotFound();
    }

    public function test_missing_saved_view_table_returns_a_safe_storage_error(): void
    {
        $this->actingAs($this->account('owner'));
        Schema::drop('government_dashboard_views');

        $this->getJson(self::URL)->assertServiceUnavailable()
            ->assertJsonPath('code', 'saved_views_storage_unavailable');
        $this->postJson(self::URL, ['name' => 'Bandung'])->assertServiceUnavailable()
            ->assertJsonPath('code', 'saved_views_storage_unavailable')
            ->assertJsonPath('message', 'Penyimpanan tampilan belum tersedia karena tabel database belum disiapkan. Hubungi administrator.')
            ->assertDontSee('SQLSTATE')->assertDontSee('government_dashboard_views');
    }

    public function test_names_and_operations_are_scoped_to_the_signed_in_owner(): void
    {
        $owner = $this->account('first');
        $other = $this->account('second');
        $first = $this->actingAs($owner)->postJson(self::URL, ['name' => ' Énergie   Jawa '])->assertCreated()->json('data.id');
        $this->postJson(self::URL, ['name' => "éNERGIE\tJAWA"])->assertUnprocessable()->assertJsonValidationErrors('name');
        $second = $this->postJson(self::URL, ['name' => 'Lain'])->assertCreated()->json('data.id');
        $this->putJson(self::URL.'/'.$second, ['name' => 'énergie jawa'] + self::EMPTY_FILTERS)
            ->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->actingAs($other)->postJson(self::URL, ['name' => 'énergie jawa'])->assertCreated();
        $this->getJson(self::URL)->assertOk()->assertJsonCount(1, 'data')->assertJsonMissing(['id' => $first]);
        $foreignUrl = self::URL.'/'.$first;
        $this->getJson($foreignUrl)->assertNotFound();
        $this->putJson($foreignUrl, [])->assertNotFound();
        $this->deleteJson($foreignUrl)->assertNotFound();
        $this->assertDatabaseHas('government_dashboard_views', ['id' => $first, 'user_id' => $owner->id]);
    }

    public function test_invalid_filters_and_client_supplied_owner_are_rejected(): void
    {
        $user = $this->account('validator');
        $this->actingAs($user)->postJson(self::URL, ['name' => 'Test', 'user_id' => $user->id])
            ->assertUnprocessable()->assertJsonValidationErrors('user_id');
        $this->postJson(self::URL, ['name' => 'Test', 'year' => 2101, 'desa' => '32.78.01.1004', 'kondisi' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors(['year', 'kabupaten', 'kecamatan', 'kondisi']);
        $this->postJson(self::URL, ['name' => 'Test', 'kabupaten' => '3279', 'kecamatan' => '32.78.01'])
            ->assertUnprocessable()->assertJsonValidationErrors('kecamatan');
        $id = $this->postJson(self::URL, ['name' => 'Test'])->assertCreated()->json('data.id');
        $this->putJson(self::URL.'/'.$id, ['name' => 'Test'])->assertUnprocessable()
            ->assertJsonValidationErrors(array_keys(self::EMPTY_FILTERS));
    }

    public function test_stale_filters_remain_saved_and_can_be_repaired(): void
    {
        $user = $this->account('stale');
        $saved = $user->governmentDashboardViews()->create(['name' => 'Lama', 'normalized_name' => 'lama', 'kabupaten' => '3299']);
        $url = self::URL.'/'.$saved->id;
        $this->actingAs($user)->getJson($url)->assertOk()
            ->assertJsonPath('data.kabupaten', '3299')->assertJsonPath('dashboard_url', null)
            ->assertJsonStructure(['filter_errors' => ['kabupaten']]);
        $this->assertDatabaseHas('government_dashboard_views', ['id' => $saved->id, 'kabupaten' => '3299']);
        $this->getJson(self::URL)->assertOk()->assertJsonPath('data.0.filter_labels.kabupaten', '3299 (tidak tersedia)');
        $this->putJson($url, ['name' => 'Baru'] + self::EMPTY_FILTERS)->assertOk();
        $this->getJson($url)->assertOk()->assertJsonPath('dashboard_url', route('pemerintah.dashboard'));
    }

    public function test_role_and_account_state_guard_the_endpoints_and_only_deletion_cascades(): void
    {
        $this->getJson(self::URL)->assertUnauthorized();
        $this->actingAs($this->account('citizen', 'masyarakat'))->postJson(self::URL, ['name' => 'Test'])->assertForbidden();
        $owner = $this->account('disabled');
        $id = $this->actingAs($owner)->postJson(self::URL, ['name' => 'Test'])->assertCreated()->json('data.id');
        $owner->is_active = false;
        $owner->save();
        $this->getJson(self::URL.'/'.$id)->assertUnauthorized();
        $this->assertDatabaseHas('government_dashboard_views', ['id' => $id, 'user_id' => $owner->id]);
        $owner->delete();
        $this->assertDatabaseMissing('government_dashboard_views', ['id' => $id]);
    }
}
