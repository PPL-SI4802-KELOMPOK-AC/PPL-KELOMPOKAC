<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePemerintah
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Sesi berakhir. Silakan masuk kembali.'], 401);
            }

            return redirect()->guest(route('login'));
        }

        abort_unless($request->user()->role === 'pemerintah', 403, 'Hanya akun Pemerintah yang dapat mengelola data wilayah.');

        return $next($request);
    }
}
