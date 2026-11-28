<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [\App\Http\Middleware\EnsureAccountIsActive::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Jangan kirim SQL, stack trace, atau konfigurasi koneksi ke halaman pengelola.
        $exceptions->render(function (Illuminate\Database\QueryException $exception, Illuminate\Http\Request $request) {
            if ($request->routeIs('pemerintah.dashboard.tampilan.*') && $request->expectsJson()) {
                [$state, $code, $detail] = array_pad($exception->errorInfo ?? [], 3, null);
                $missingTable = ($state === '42S02' && (int) $code === 1146
                    && preg_match("/Table '[^']+\\.government_dashboard_views' doesn't exist/i", (string) $detail))
                    || ($state === 'HY000' && (int) $code === 1
                        && preg_match('/^no such table: (?:\w+\.)?government_dashboard_views$/i', (string) $detail));
                if ($missingTable) {
                    return response()->json([
                        'code' => 'saved_views_storage_unavailable',
                        'message' => 'Penyimpanan tampilan belum tersedia karena tabel database belum disiapkan. Hubungi administrator.',
                    ], 503)->header('Cache-Control', 'private, no-store');
                }
            }
            if ($request->is('pemerintah/wilayah*', 'pemerintah/dashboard*', 'pengajuan/lokasi*', 'login', 'admin', 'admin/*')) {
                $message = 'Database belum dapat diakses. Periksa layanan MySQL dan konfigurasi koneksi, lalu coba kembali.';

                return $request->expectsJson()
                    ? response()->json(['message' => $message], 503)
                    : response()->view('errors.database', ['message' => $message], 503);
            }
        });
    })->create();
