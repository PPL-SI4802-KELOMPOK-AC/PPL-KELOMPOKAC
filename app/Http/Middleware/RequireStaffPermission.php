<?php

namespace App\Http\Middleware;

use App\Services\StaffAccess;
use Closure;
use Illuminate\Http\Request;

class RequireStaffPermission
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        abort_unless($request->user() && app(StaffAccess::class)->allows($request->user(), $permission), 403,
            'Profil petugas tidak memberikan izin untuk tindakan ini. Hubungi Admin.');

        return $next($request);
    }
}
