<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0c3d5b">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <title>@yield('title', 'Pengguna & peran | Admin TerangDesa')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="admin-page system-admin">
<a class="skip-link" href="#konten">Lewati ke konten</a>
<button class="drawer-backdrop" data-drawer-close aria-label="Tutup menu navigasi" hidden></button>
<aside id="sidebar" class="sidebar" aria-label="Administrasi sistem" tabindex="-1">
    <button class="button drawer-close" data-drawer-close type="button" hidden>Tutup menu ×</button>
    <a class="brand" href="{{ route('admin.dashboard') }}" aria-label="TerangDesa, administrasi">
        <span class="brand__symbol">@include('admin.partials.icon', ['name' => 'sun'])</span>
        <span class="brand__text">TerangDesa<small>PEMETAAN &amp; PENGAJUAN PLTS</small></span>
    </a>
    <p class="sidebar-label">ADMINISTRASI SISTEM</p>
    <nav aria-label="Navigasi admin">
        <a href="{{ route('admin.dashboard') }}" @if(request()->routeIs('admin.dashboard')) aria-current="page" @endif>@include('admin.partials.icon', ['name' => 'grid'])Ringkasan operasional</a>
        <a href="{{ route('admin.users.index') }}" @if(request()->routeIs('admin.users.*')) aria-current="page" @endif>@include('admin.partials.icon', ['name' => 'users'])Pengguna &amp; peran</a>
        <a href="{{ route('admin.users.index') }}#data-master">@include('admin.partials.icon', ['name' => 'database'])Data master</a>
        <a href="{{ route('admin.activities.index') }}" @if(request()->routeIs('admin.activities.*')) aria-current="page" @endif>@include('admin.partials.icon', ['name' => 'history'])Log aktivitas</a>
    </nav>
    <div class="sidebar-context"><strong>TerangDesa · Jawa Barat</strong><span>Energi untuk setiap desa</span><a href="{{ route('home') }}">Kunjungi halaman publik ↗</a></div>
</aside>
<div class="workspace">
    <header class="site-header">
        <button class="button drawer-toggle" type="button" data-drawer-open aria-controls="sidebar" aria-expanded="false" hidden>☰ <span>Menu</span></button>
        <p class="breadcrumb">Administrasi <span aria-hidden="true">/</span> <span>@yield('breadcrumb', 'Pengguna & peran')</span></p>
        <div class="workspace-identity">
            <span class="account-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 2)) }}</span>
            <div><strong>{{ auth()->user()->name }}</strong><span>Administrator</span></div>
            <form action="{{ route('logout') }}" method="POST">@csrf<button class="button admin-logout" type="submit">Keluar</button></form>
        </div>
    </header>
    <main id="konten" class="content-width" tabindex="-1">@yield('content')</main>
    <footer class="site-footer content-width"><span>TerangDesa · Administrasi sistem</span><span>Jawa Barat</span></footer>
</div>
</body>
</html>
