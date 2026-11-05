<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireMasyarakat
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Silakan masuk untuk menyimpan lokasi pengajuan.'], 401)
                : redirect()->guest(route('login'));
        }
        abort_unless($request->user()->role === 'masyarakat', 403, 'Pengisian lokasi pengajuan tersedia untuk akun masyarakat.');

        return $next($request);
    }
}
