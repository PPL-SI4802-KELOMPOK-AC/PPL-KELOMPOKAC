<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Silakan masuk sebagai admin.'], 401)
                : redirect()->guest(route('login'));
        }

        abort_unless($request->user()->role === 'admin', 403, 'Halaman ini hanya tersedia untuk admin.');

        return $next($request);
    }
}
