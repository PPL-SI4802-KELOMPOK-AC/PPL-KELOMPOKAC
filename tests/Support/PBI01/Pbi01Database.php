<?php

namespace Tests\Support\PBI01;

use App\Models\User;
use App\Models\Wilayah;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/** Skema dan data sintetis; tidak pernah memakai koneksi MySQL/TiDB. */
final class Pbi01Database
{
    public static function rebuild(): array
    {
        $connection = DB::connection();
        $path = $connection->getDatabaseName();
        $directory = realpath(storage_path('framework/testing'));
        if ($connection->getDriverName() !== 'sqlite'
            || ($path !== ':memory:' && (! $directory || realpath(dirname($path)) !== $directory
                || ! preg_match('/^pbi01-dusk-[a-f0-9]+\.sqlite$/', basename($path))))) {
            throw new RuntimeException('PBI-01 hanya boleh mengatur ulang database SQLite uji yang terisolasi.');
        }
        foreach (['riwayat_status_pengajuan', 'lokasi_pengajuan', 'progres_elektrifikasi', 'pengajuan_plts', 'wilayah', 'users'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role');
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('wilayah', function (Blueprint $table) {
            $table->id();
            $table->string('kode_referensi', 32)->nullable()->unique();
            $table->string('nama_wilayah', 150);
            $table->string('tingkat_wilayah', 50);
            $table->text('alamat_deskriptif');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 11, 7)->nullable();
            $table->string('kondisi_listrik')->nullable();
            $table->unsignedInteger('jumlah_kk_terdampak')->nullable();
            $table->unsignedInteger('jumlah_warga_terdampak')->nullable();
            $table->string('sumber_data')->nullable();
            $table->date('data_per_tanggal')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
        });
        Schema::create('pengajuan_plts', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_tiket', 50)->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('wilayah_id')->nullable()->constrained('wilayah')->restrictOnDelete();
            $table->text('ringkasan_pengajuan');
            $table->string('status_terkini');
            $table->timestamp('status_diperbarui_pada')->nullable();
        });
        Schema::create('lokasi_pengajuan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->unique()->constrained('pengajuan_plts')->restrictOnDelete();
            $table->text('alamat_deskriptif');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 11, 7)->nullable();
        });
        Schema::create('riwayat_status_pengajuan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->constrained('pengajuan_plts')->restrictOnDelete();
            $table->foreignId('diubah_oleh')->constrained('users')->restrictOnDelete();
            $table->string('status_sebelumnya')->nullable();
            $table->string('status_baru');
            $table->string('jenis_tindakan');
            $table->string('hasil_verifikasi')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamp('created_at');
        });
        Schema::create('progres_elektrifikasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wilayah_id')->constrained('wilayah')->restrictOnDelete();
        });
        $petugas = self::user('Petugas ESDM Uji', 'pemerintah');
        $masyarakat = self::user('Masyarakat Uji', 'masyarakat');
        $mekarjaya = new Wilayah(self::payload('Mekarjaya'));
        $mekarjaya->created_by = $petugas->id;
        $mekarjaya->save();
        $hapus = new Wilayah(self::payload('Desa Uji Hapus'));
        $hapus->created_by = $petugas->id;
        $hapus->save();
        $pengajuan = DB::table('pengajuan_plts')->insertGetId([
            'nomor_tiket' => 'PLTS-PBI01-001', 'user_id' => $masyarakat->id, 'wilayah_id' => $mekarjaya->id,
            'ringkasan_pengajuan' => 'Pengajuan mandiri sebelum pembaruan master',
            'status_terkini' => 'diajukan', 'status_diperbarui_pada' => '2026-11-01 08:00:00',
        ]);
        DB::table('lokasi_pengajuan')->insert([
            'pengajuan_id' => $pengajuan, 'alamat_deskriptif' => 'Kampung Awal RT 01 RW 02, Mekarjaya',
            'latitude' => -6.92, 'longitude' => 107.62,
        ]);
        DB::table('riwayat_status_pengajuan')->insert([
            'pengajuan_id' => $pengajuan, 'diubah_oleh' => $masyarakat->id, 'status_baru' => 'diajukan',
            'jenis_tindakan' => 'pengajuan_baru', 'catatan' => 'Catatan asli masyarakat', 'created_at' => '2026-11-01 08:00:00',
        ]);

        return compact('petugas', 'masyarakat', 'mekarjaya', 'hapus');
    }

    public static function payload(string $nama = 'Desa Harapan Uji'): array
    {
        return [
            'nama_wilayah' => $nama, 'tingkat_wilayah' => 'desa_kelurahan',
            'alamat_deskriptif' => $nama.', Kecamatan Uji, Kabupaten Bandung, Jawa Barat',
            'latitude' => '-6.9175', 'longitude' => '107.6191', 'jumlah_kk_terdampak' => 25,
            'kondisi_listrik' => 'belum_berlistrik',
        ];
    }

    public static function history(): array
    {
        $snapshot = [];
        foreach (['pengajuan_plts', 'lokasi_pengajuan', 'riwayat_status_pengajuan'] as $table) {
            $snapshot[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        return $snapshot;
    }

    private static function user(string $name, string $role): User
    {
        $user = new User(['name' => $name, 'email' => $role.'@pbi01.example.test', 'password' => 'password-khusus-uji']);
        $user->role = $role;
        $user->save();

        return $user;
    }
}
