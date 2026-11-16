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
            if ($request->is('pemerintah/wilayah*', 'pengajuan/lokasi*', 'login', 'admin', 'admin/*')) {
                $message = 'Database belum dapat diakses. Periksa layanan MySQL dan konfigurasi koneksi, lalu coba kembali.';

                return $request->expectsJson()
                    ? response()->json(['message' => $message], 503)
                    : response()->view('errors.database', ['message' => $message], 503);
            }
        });
    })->create();
