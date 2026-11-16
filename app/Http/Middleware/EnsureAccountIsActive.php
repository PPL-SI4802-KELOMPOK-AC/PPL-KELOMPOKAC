<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->is_active === false) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson()
                ? response()->json(['message' => 'Akses akun dinonaktifkan. Hubungi admin.'], 401)
                : redirect()->route('login')->withErrors(['email' => 'Akses akun dinonaktifkan. Hubungi admin.']);
        }

        return $next($request);
    }
}
