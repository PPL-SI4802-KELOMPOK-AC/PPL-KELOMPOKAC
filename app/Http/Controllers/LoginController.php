<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'string', 'max:255'],
        ]);
        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages(['email' => 'Email atau kata sandi tidak sesuai.']);
        }
        if ($request->user()->is_active === false) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            throw ValidationException::withMessages(['email' => 'Email atau kata sandi tidak sesuai, atau akun dinonaktifkan.']);
        }
        $request->session()->regenerate();

        $role = $request->user()->role;
        $destination = match ($role) {
            'pemerintah' => 'wilayah.index',
            'masyarakat' => 'pengajuan.lokasi',
            'admin' => 'admin.dashboard',
            default => 'home',
        };
        $intended = $request->session()->pull('url.intended');
        $path = $intended ? parse_url($intended, PHP_URL_PATH) : null;
        $local = is_string($intended) && ($intended === url('/') || str_starts_with($intended, url('/').'/'));
        $allowed = in_array($path, ['/', '/peta-wilayah'], true)
            || ($role === 'masyarakat' && ($path === '/pengajuan/riwayat' || $path === '/pengajuan/lokasi' || str_starts_with($path ?? '', '/pengajuan/lokasi/')))
            || ($role === 'pemerintah' && ($path === '/pemerintah/wilayah' || str_starts_with($path ?? '', '/pemerintah/wilayah/')))
            || ($role === 'admin' && ($path === '/admin' || str_starts_with($path ?? '', '/admin/')));

        return $local && $allowed ? redirect()->to($intended) : redirect()->route($destination);
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
