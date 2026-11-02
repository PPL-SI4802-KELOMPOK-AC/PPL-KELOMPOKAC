<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\WilayahController;
use App\Http\Controllers\RegionMapController;
use App\Http\Controllers\DashboardController;
use App\Http\Middleware\RequirePemerintah;

Route::get('/', function () {
    return view('welcome');
})->name('home');
Route::get('/dashboard/data', [DashboardController::class, 'data'])->middleware('throttle:dashboard-data')->name('dashboard.data');

Route::view('/peta-wilayah', 'wilayah.map')->name('wilayah.map');
Route::get('/peta-wilayah/data', [RegionMapController::class, 'data'])->middleware('throttle:map-data')->name('wilayah.map.data');

Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

Route::prefix('pemerintah/wilayah')->name('wilayah.')->middleware(RequirePemerintah::class)->group(function () {
    Route::get('/', [WilayahController::class, 'index'])->name('index');
    Route::get('/data', [WilayahController::class, 'data'])->name('data');
    Route::post('/', [WilayahController::class, 'store'])->name('store');
    Route::get('/{wilayah}', [WilayahController::class, 'show'])->whereNumber('wilayah')->name('show');
    Route::put('/{wilayah}', [WilayahController::class, 'update'])->whereNumber('wilayah')->name('update');
    Route::delete('/{wilayah}', [WilayahController::class, 'destroy'])->whereNumber('wilayah')->name('destroy');
});
