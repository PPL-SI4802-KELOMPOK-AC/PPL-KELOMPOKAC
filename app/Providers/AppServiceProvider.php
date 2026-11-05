<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Nama limiter terpisah: aktivitas peta tidak boleh menghabiskan kuota login.
        RateLimiter::for('map-data', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        RateLimiter::for('dashboard-data', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('location-save', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?? $request->ip()));
    }
}
