<?php

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../../vendor/autoload.php';
$path = getenv('PBI06_TEST_DATABASE');
$directory = realpath(__DIR__.'/../../storage/framework/testing');
if (PHP_SAPI !== 'cli' || getenv('PBI06_SQLITE_TESTS') !== '1' || ! $path
    || realpath(dirname($path)) !== $directory || ! preg_match('/^pbi06-e2e-[a-f0-9-]+\.sqlite$/', basename($path))
    || is_file(__DIR__.'/../../bootstrap/cache/config.php') || file_exists($path)) {
    throw new RuntimeException('Fixture hanya boleh membuat database SQLite uji baru tanpa config cache.');
}
fclose(fopen($path, 'x'));
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $path]);
DB::purge('sqlite');
if (DB::connection()->getDriverName() !== 'sqlite') {
    throw new RuntimeException('Pengujian PBI-06 hanya boleh memakai SQLite.');
}
Schema::create('users', function (Blueprint $table) {
    $table->id(); $table->string('name'); $table->string('email')->unique();
    $table->string('password'); $table->string('role'); $table->timestamps();
});
Schema::create('pengajuan_plts', function (Blueprint $table) {
    $table->id(); $table->string('nomor_tiket', 50)->unique();
    $table->foreignId('user_id')->constrained('users'); $table->unsignedBigInteger('wilayah_id')->nullable();
    $table->string('ringkasan_pengajuan'); $table->string('status_terkini')->default('diajukan');
    $table->timestamp('status_diperbarui_pada')->useCurrent();
});
Schema::create('lokasi_pengajuan', function (Blueprint $table) {
    $table->id(); $table->foreignId('pengajuan_id')->unique()->constrained('pengajuan_plts');
    $table->text('alamat_deskriptif'); $table->decimal('latitude', 10, 7)->nullable(); $table->decimal('longitude', 11, 7)->nullable();
});
Schema::create('bukti_pengajuan', function (Blueprint $table) {
    $table->id(); $table->foreignId('pengajuan_id')->constrained('pengajuan_plts');
    $table->string('nama_file_asli', 255); $table->string('path_file', 512)->unique();
    $table->string('mime_type', 100); $table->unsignedBigInteger('ukuran_byte'); $table->string('keterangan', 255)->nullable();
});
$user = new User(['name' => 'Pemohon Uji PBI-06', 'email' => 'lokasi-e2e@example.test', 'password' => 'lokasi-e2e-password']);
$user->role = 'masyarakat';
$user->save();

Schema::create('riwayat_status_pengajuan', function (Blueprint $table) {
    $table->id(); $table->foreignId('pengajuan_id')->constrained('pengajuan_plts');
    $table->foreignId('diubah_oleh')->constrained('users');
    $statuses = ['diajukan', 'diperiksa', 'diverifikasi', 'diproses', 'selesai', 'ditolak'];
    $table->enum('status_sebelumnya', $statuses)->nullable(); $table->enum('status_baru', $statuses);
    $table->string('jenis_tindakan', 50); $table->string('hasil_verifikasi', 50)->nullable();
    $table->text('catatan')->nullable(); $table->timestamp('created_at')->useCurrent();
});
$submitter = new User(['name' => 'Pengusul Uji PBI-08', 'email' => 'pengajuan-e2e@example.test', 'password' => 'pengajuan-e2e-password']);
$submitter->role = 'masyarakat';
$submitter->save();
$draft = new App\Models\PengajuanPlts;
$draft->user_id = $submitter->id;
$draft->nomor_tiket = 'DRAFT-'.Illuminate\Support\Str::uuid();
$draft->ringkasan_pengajuan = 'Lokasi Awassagara, Kabupaten Garut';
$draft->status_terkini = 'draft';
$draft->save();
$draft->lokasi()->create(['alamat_deskriptif' => app(App\Services\Pengajuan\LocationCatalog::class)->address([
    'kabupaten' => '3205', 'kecamatan' => 'Cikelet', 'desa' => '32.05.30.2010', 'alamat' => 'Kampung Uji, RT 03 / RW 02',
]), 'latitude' => -7.5812, 'longitude' => 107.6984]);
foreach (['kondisi-rumah.png', 'akses-jalan.png'] as $index => $name) {
    $draft->bukti()->create(['nama_file_asli' => $name, 'path_file' => "fixture-only/$draft->id/$index.png", 'mime_type' => 'image/png', 'ukuran_byte' => 1048576]);
}
echo "Database SQLite uji PBI-06 siap.\n";
