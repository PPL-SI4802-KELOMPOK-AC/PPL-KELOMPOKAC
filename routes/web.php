<?php

use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\StaffAccessController;
use App\Http\Middleware\RequireStaffPermission;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminActivityController;
use App\Http\Middleware\RequireAdmin;
use App\Http\Controllers\BuktiPengajuanController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GovernmentDashboardController;
use App\Http\Controllers\GovernmentDashboardViewController;
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
    Route::prefix('hak-akses')->name('access.')->group(function () {
        Route::get('/', [StaffAccessController::class, 'index'])->name('index');
        Route::get('/tambah', [StaffAccessController::class, 'create'])->name('create');
        Route::post('/tinjau', [StaffAccessController::class, 'preview'])->name('preview');
        Route::post('/', [StaffAccessController::class, 'store'])->middleware('throttle:admin-users')->name('store');
        Route::get('/petugas/{staff}', [StaffAccessController::class, 'assignment'])->whereNumber('staff')->name('assignment');
        Route::post('/penetapan/tinjau', [StaffAccessController::class, 'previewAssignment'])->name('assignment.preview');
        Route::post('/penetapan', [StaffAccessController::class, 'assign'])->middleware('throttle:admin-users')->name('assign');
        Route::get('/{profile}/edit', [StaffAccessController::class, 'edit'])->whereNumber('profile')->name('edit');
        Route::get('/{profile}/hapus', [StaffAccessController::class, 'confirmDelete'])->whereNumber('profile')->name('delete');
        Route::get('/{profile}', [StaffAccessController::class, 'show'])->whereNumber('profile')->name('show');
        Route::put('/{profile}', [StaffAccessController::class, 'update'])->middleware('throttle:admin-users')->whereNumber('profile')->name('update');
        Route::delete('/{profile}', [StaffAccessController::class, 'destroy'])->middleware('throttle:admin-users')->whereNumber('profile')->name('destroy');
    });
});

Route::get('/', function () {
    return view('welcome');
})->name('home');
Route::get('/dashboard/data', [DashboardController::class, 'data'])->middleware('throttle:dashboard-data')->name('dashboard.data');

Route::prefix('pemerintah/dashboard')->name('pemerintah.dashboard')->middleware([
    RequirePemerintah::class, RequireStaffPermission::class.':dashboard.view',
])->group(function () {
    Route::get('/', [GovernmentDashboardController::class, 'index']);
    Route::get('/data', [GovernmentDashboardController::class, 'data'])->middleware('throttle:dashboard-data')->name('.data');
    Route::get('/administrasi', [GovernmentDashboardController::class, 'administration'])->middleware('throttle:map-data')->name('.administrasi');
    Route::get('/wilayah', [GovernmentDashboardController::class, 'area'])->middleware('throttle:dashboard-data')->name('.wilayah');

    Route::prefix('tampilan')->name('.tampilan.')->controller(GovernmentDashboardViewController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('/{view}', 'show')->whereNumber('view')->name('show');
        Route::put('/{view}', 'update')->whereNumber('view')->name('update');
        Route::delete('/{view}', 'destroy')->whereNumber('view')->name('destroy');
    });
});

Route::view('/peta-wilayah', 'wilayah.map')->name('wilayah.map');
Route::get('/peta-wilayah/data', [RegionMapController::class, 'data'])->middleware('throttle:map-data')->name('wilayah.map.data');

Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

Route::get('/pengajuan/riwayat', [RiwayatPengajuanController::class, 'index'])
    ->middleware(RequireMasyarakat::class)->name('pengajuan.riwayat');
Route::get('/pengajuan/riwayat/{pengajuan}', [RiwayatPengajuanController::class, 'show'])
    ->middleware(RequireMasyarakat::class)->whereNumber('pengajuan')->name('pengajuan.riwayat.show');

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
    Route::get('/', [WilayahController::class, 'index'])->middleware(RequireStaffPermission::class.':wilayah.view')->name('index');
    Route::get('/data', [WilayahController::class, 'data'])->middleware(RequireStaffPermission::class.':wilayah.view')->name('data');
    Route::post('/', [WilayahController::class, 'store'])->middleware(RequireStaffPermission::class.':wilayah.create')->name('store');
    Route::get('/{wilayah}', [WilayahController::class, 'show'])->middleware(RequireStaffPermission::class.':wilayah.view')->whereNumber('wilayah')->name('show');
    Route::put('/{wilayah}', [WilayahController::class, 'update'])->middleware(RequireStaffPermission::class.':wilayah.update')->whereNumber('wilayah')->name('update');
    Route::delete('/{wilayah}', [WilayahController::class, 'destroy'])->middleware(RequireStaffPermission::class.':wilayah.delete')->whereNumber('wilayah')->name('destroy');
});
