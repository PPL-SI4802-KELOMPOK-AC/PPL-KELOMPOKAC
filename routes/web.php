<?php

use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminActivityController;
use App\Http\Middleware\RequireAdmin;
use App\Http\Controllers\BuktiPengajuanController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\LokasiPengajuanController;
use App\Http\Controllers\PengajuanController;
use App\Http\Controllers\RegionMapController;
use App\Http\Controllers\RiwayatPengajuanController;
use App\Http\Controllers\WilayahController;
use App\Http\Middleware\RequireMasyarakat;
use App\Http\Middleware\RequirePemerintah;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware(RequireAdmin::class)->group(function () {
    Route::get('/', fn () => redirect()->route('admin.dashboard'))->name('index');
    Route::get('/ringkasan', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/aktivitas', [AdminActivityController::class, 'index'])->name('activities.index');
    Route::get('/pengguna', [AdminUserController::class, 'index'])->name('users.index');
    Route::get('/pengguna/tambah', [AdminUserController::class, 'create'])->name('users.create');
    Route::post('/pengguna', [AdminUserController::class, 'store'])->middleware('throttle:admin-users')->name('users.store');
    Route::get('/pengguna/{managedUser}/edit', [AdminUserController::class, 'edit'])->whereNumber('managedUser')->name('users.edit');
    Route::put('/pengguna/{managedUser}', [AdminUserController::class, 'update'])->middleware('throttle:admin-users')->whereNumber('managedUser')->name('users.update');
});

Route::get('/', function () {
    return view('welcome');
})->name('home');
Route::get('/dashboard/data', [DashboardController::class, 'data'])->middleware('throttle:dashboard-data')->name('dashboard.data');

Route::view('/peta-wilayah', 'wilayah.map')->name('wilayah.map');
Route::get('/peta-wilayah/data', [RegionMapController::class, 'data'])->middleware('throttle:map-data')->name('wilayah.map.data');

Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

Route::get('/pengajuan/riwayat', [RiwayatPengajuanController::class, 'index'])
    ->middleware(RequireMasyarakat::class)->name('pengajuan.riwayat');

Route::prefix('pengajuan/lokasi')->name('pengajuan.')->middleware(RequireMasyarakat::class)->group(function () {
    Route::get('/', [LokasiPengajuanController::class, 'edit'])->name('lokasi');
    Route::get('/administrasi', [LokasiPengajuanController::class, 'administration'])->middleware('throttle:map-data')->name('lokasi.administrasi');
    Route::post('/', [LokasiPengajuanController::class, 'store'])->middleware('throttle:location-save')->name('lokasi.store');
    Route::get('/bukti', [BuktiPengajuanController::class, 'edit'])->name('bukti');
    Route::post('/bukti', [BuktiPengajuanController::class, 'store'])->middleware('throttle:location-save')->name('bukti.store');
    Route::get('/bukti/{bukti}', [BuktiPengajuanController::class, 'show'])->whereNumber('bukti')->name('bukti.show');
    Route::get('/ringkasan', [PengajuanController::class, 'review'])->name('ringkasan');
    Route::post('/kirim', [PengajuanController::class, 'submit'])->middleware('throttle:location-save')->name('kirim');
    Route::get('/terkirim/{pengajuan}', [PengajuanController::class, 'receipt'])->whereNumber('pengajuan')->name('terkirim');
});

Route::prefix('pemerintah/wilayah')->name('wilayah.')->middleware(RequirePemerintah::class)->group(function () {
    Route::get('/', [WilayahController::class, 'index'])->name('index');
    Route::get('/data', [WilayahController::class, 'data'])->name('data');
    Route::post('/', [WilayahController::class, 'store'])->name('store');
    Route::get('/{wilayah}', [WilayahController::class, 'show'])->whereNumber('wilayah')->name('show');
    Route::put('/{wilayah}', [WilayahController::class, 'update'])->whereNumber('wilayah')->name('update');
    Route::delete('/{wilayah}', [WilayahController::class, 'destroy'])->whereNumber('wilayah')->name('destroy');
});
