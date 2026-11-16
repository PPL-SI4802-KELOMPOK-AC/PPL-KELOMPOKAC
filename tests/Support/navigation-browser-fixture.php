<?php

// Reuse the existing guard: only a new SQLite file under storage/framework/testing.
require __DIR__.'/location-browser-fixture.php';

foreach (['pemerintah', 'admin'] as $role) {
    $user = new App\Models\User(['name' => 'Navigasi '.ucfirst($role), 'email' => $role.'-navigation@example.test', 'password' => 'navigation-e2e-password']);
    $user->role = $role;
    $user->save();
}

Illuminate\Support\Facades\Schema::create('wilayah', function (Illuminate\Database\Schema\Blueprint $table) {
    $table->id(); $table->string('kode_referensi')->nullable(); $table->string('nama_wilayah');
    $table->string('tingkat_wilayah')->nullable(); $table->text('alamat_deskriptif');
    $table->decimal('latitude', 10, 7)->nullable(); $table->decimal('longitude', 11, 7)->nullable();
    $table->string('kondisi_listrik')->nullable(); $table->unsignedInteger('jumlah_kk_terdampak')->nullable();
    $table->unsignedInteger('jumlah_warga_terdampak')->nullable(); $table->string('sumber_data')->nullable();
    $table->date('data_per_tanggal')->nullable(); $table->unsignedBigInteger('dibuat_oleh')->nullable();
});
App\Models\Wilayah::create(['kode_referensi' => '32.05.30.2010', 'nama_wilayah' => 'AWASSAGARA',
    'tingkat_wilayah' => 'desa_kelurahan', 'alamat_deskriptif' => 'AWASSAGARA, CIKELET, KABUPATEN GARUT, JAWA BARAT',
    'latitude' => -7.5812, 'longitude' => 107.6984, 'jumlah_kk_terdampak' => 7,
    'sumber_data' => 'Data uji navigasi', 'data_per_tanggal' => '2024-12-31']);
Illuminate\Support\Facades\DB::table('wilayah')->update(['dibuat_oleh' => 123456]);
