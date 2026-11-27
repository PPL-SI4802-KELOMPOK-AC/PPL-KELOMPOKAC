<?php

namespace Tests\Support;

use App\Models\StaffAccessProfile;
use App\Models\User;
use App\Services\StaffPermissionCatalog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait PBI24Fixture
{
    use StaffAccessFixture;

    protected function buildAccessFixture(): array
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            throw new \RuntimeException('PBI-24 hanya boleh menulis fixture SQLite terisolasi.');
        }
        $this->resetStaffAccessFixture();
        foreach (['progres_elektrifikasi', 'riwayat_status_pengajuan', 'pengajuan_plts', 'wilayah', 'users'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password');
            $t->string('role');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('wilayah', function (Blueprint $t) {
            $t->id();
            $t->string('kode_referensi')->nullable()->unique();
            $t->string('nama_wilayah');
            $t->string('tingkat_wilayah');
            $t->text('alamat_deskriptif');
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 11, 7)->nullable();
            $t->string('kondisi_listrik')->nullable();
            $t->unsignedInteger('jumlah_kk_terdampak')->nullable();
            $t->unsignedInteger('jumlah_warga_terdampak')->nullable();
            $t->string('sumber_data')->nullable();
            $t->date('data_per_tanggal')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
        });
        Schema::create('pengajuan_plts', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('wilayah_id')->nullable();
            $t->string('status_terkini');
            $t->timestamp('status_diperbarui_pada');
        });
        Schema::create('riwayat_status_pengajuan', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('pengajuan_id');
            $t->string('status_baru');
            $t->string('jenis_tindakan');
            $t->timestamp('created_at');
        });
        Schema::create('progres_elektrifikasi', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('wilayah_id');
            $t->date('tanggal_progres');
        });
        $users = [];
        foreach (['admin' => ['Admin Uji', 'admin', true], 'staff' => ['Petugas Uji', 'pemerintah', true], 'society' => ['Masyarakat Uji', 'masyarakat', true], 'inactive' => ['Petugas Nonaktif', 'pemerintah', false]] as $key => [$name,$role,$active]) {
            $user = new User(['name' => $name, 'email' => $key.'@pbi24.example.test', 'password' => 'password-khusus-uji']);
            $user->role = $role;
            $user->is_active = $active;
            $user->save();
            $users[$key] = $user;
        }
        (require database_path('migrations/2026_11_07_000002_create_admin_activity_logs_table.php'))->up();
        (require database_path('migrations/2026_11_27_000001_create_staff_access_profiles.php'))->up();
        $region = DB::table('wilayah')->insertGetId(['nama_wilayah' => 'Mekarjaya', 'tingkat_wilayah' => 'desa_kelurahan',
            'alamat_deskriptif' => 'Mekarjaya, Kabupaten Bandung, Jawa Barat', 'latitude' => -6.9175, 'longitude' => 107.6191, 'jumlah_kk_terdampak' => 25]);

        return $users + ['region' => $region];
    }

    protected function accessProfile(string $name = 'Petugas Verifikasi', array $permissions = ['wilayah.view'], bool $active = true): StaffAccessProfile
    {
        $profile = new StaffAccessProfile;
        $profile->forceFill(['name' => $name, 'name_key' => StaffPermissionCatalog::key($name), 'description' => 'Profil untuk pengujian',
            'is_active' => $active, 'ever_assigned' => false, 'version' => 1])->save();
        foreach ($permissions as $code) {
            DB::table('staff_profile_permissions')->insert(['profile_id' => $profile->id, 'permission_code' => $code]);
        }

        return $profile;
    }

    protected function profileInput(?StaffAccessProfile $profile = null, array $extra = []): array
    {
        return $extra + ['name' => $profile?->name ?? 'Petugas Verifikasi', 'description' => 'Verifikasi dan pendataan wilayah', 'is_active' => 1,
            'permissions' => ['wilayah.view'], 'confirmed' => 1, 'expected_version' => $profile?->fresh()->version ?? 1,
            'expected_usage_count' => $profile?->assignments()->count() ?? 0];
    }

    protected function assignmentInput(User $staff, StaffAccessProfile $profile): array
    {
        return ['user_id' => $staff->id, 'profile_id' => $profile->id, 'expected_profile_id' => $staff->staffAssignment()->value('profile_id'),
            'expected_version' => $profile->fresh()->version, 'confirmed' => 1];
    }
}
