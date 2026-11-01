<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('/peta-wilayah', 'wilayah.map')->name('wilayah.map');
