<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0c3d5b">
    <meta name="description" content="TerangDesa — jelajahi wilayah pendataan kebutuhan listrik di Jawa Barat.">
    <title>@yield('title', 'TerangDesa')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="@yield('body-class')">
    <a class="skip-link" href="#konten">Lewati ke konten</a>
    @unless(request()->routeIs('home', 'wilayah.index', 'wilayah.map', 'login', 'pengajuan.*'))
        <div class="ambient" aria-hidden="true"><div class="ambient__back"></div><div class="ambient__front"></div><div class="ambient__cursor"></div></div>
    @endunless
    <button class="drawer-backdrop" data-drawer-close aria-label="Tutup menu navigasi" hidden></button>
    <aside id="sidebar" class="sidebar" aria-label="Menu aplikasi TerangDesa" tabindex="-1">
        <button class="button drawer-close" data-drawer-close type="button" hidden>Tutup menu ×</button>
        <a class="brand" href="{{ route('home') }}" aria-label="TerangDesa, beranda">
            <span class="brand__symbol" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1.5 1.5m11 11L19 19M5 19l1.5-1.5m11-11L19 5"/></svg></span>
            <span class="brand__text">TerangDesa<small>PEMETAAN &amp; PENGAJUAN PLTS</small></span>
        </a>
        <p class="sidebar-label">RUANG KERJA</p>
        <nav aria-label="Navigasi utama">
            @if(!auth()->check() || auth()->user()->role === 'masyarakat')
                <a href="{{ route('pengajuan.lokasi') }}" @if(request()->routeIs('pengajuan.*')) aria-current="page" @endif><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M12 21s7-6 7-12a7 7 0 0 0-14 0c0 6 7 12 7 12Z"/><circle cx="12" cy="9" r="2"/></svg>Pengajuan PLTS</a>
            @endif
            <a href="{{ route('wilayah.index') }}" @if(request()->routeIs('wilayah.index')) aria-current="page" @endif><svg aria-hidden="true" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 10h18M9 10v10"/></svg>Data wilayah</a>
            <a href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif><svg aria-hidden="true" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>Beranda</a>
            <a href="{{ route('wilayah.map') }}" @if(request()->routeIs('wilayah.map')) aria-current="page" @endif><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m3 5 6-2 6 2 6-2v16l-6 2-6-2-6 2Zm6-2v16m6-14v16"/></svg>Peta Wilayah</a>
        </nav>
        <div class="sidebar-context"><strong>TerangDesa · Jawa Barat</strong><span>Energi untuk setiap desa</span><span>Pendataan kebutuhan energi desa</span></div>
    </aside>
    <div class="workspace">
        <header class="site-header">
            <button class="button drawer-toggle" type="button" data-drawer-open aria-controls="sidebar" aria-expanded="false" hidden>☰ <span>Menu</span></button>
            <p class="breadcrumb">TerangDesa <span aria-hidden="true">/</span> <span>@yield('breadcrumb', 'Beranda')</span></p>
            <div class="workspace-identity">
                <span class="workspace-identity__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m3 5 6-2 6 2 6-2v16l-6 2-6-2-6 2Zm6-2v16m6-14v16"/></svg></span>
                <div><strong>@auth{{ auth()->user()->name }}@else TerangDesa @endauth</strong><span>@auth{{ ucfirst(auth()->user()->role ?? 'Akun terhubung') }}@else Pemetaan wilayah @endauth</span></div>
                @auth
                    <form action="{{ route('logout') }}" method="POST" data-leave-form>@csrf<button class="button" type="submit">Keluar</button></form>
                @else
                    @unless(request()->routeIs('login'))<a class="button" href="{{ route('login') }}">Masuk</a>@endunless
                @endauth
            </div>
        </header>
        <main id="konten" class="content-width" tabindex="-1">@yield('content')</main>
        <footer class="site-footer content-width"><span>TerangDesa · Jawa Barat</span><span>Pendataan kebutuhan energi desa</span></footer>
    </div>
</body>
</html>
