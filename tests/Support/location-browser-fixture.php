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
echo "Database SQLite uji PBI-06 siap.\n";
