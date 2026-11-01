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
<body>
    <a class="skip-link" href="#konten">Lewati ke konten</a>
    <div class="ambient" aria-hidden="true"><div class="ambient__back"></div><div class="ambient__front"></div><div class="ambient__cursor"></div></div>
    <aside class="sidebar" aria-label="Menu aplikasi TerangDesa">
        <a class="brand" href="{{ route('home') }}" aria-label="TerangDesa, beranda">
            <span class="brand__symbol" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1.5 1.5m11 11L19 19M5 19l1.5-1.5m11-11L19 5"/></svg></span>
            <span class="brand__text">TerangDesa<small>PEMETAAN &amp; PENGAJUAN PLTS</small></span>
        </a>
        <p class="sidebar-label">RUANG KERJA</p>
        <nav aria-label="Navigasi utama">
            <a href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif><svg aria-hidden="true" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>Beranda</a>
            <a href="{{ route('wilayah.map') }}" @if(request()->routeIs('wilayah.map')) aria-current="page" @endif><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m3 5 6-2 6 2 6-2v16l-6 2-6-2-6 2Zm6-2v16m6-14v16"/></svg>Peta Wilayah</a>
        </nav>
        <div class="sidebar-context"><strong>TerangDesa · Jawa Barat</strong><span>Energi untuk setiap desa</span><span>Referensi wilayah tahun 2024</span></div>
    </aside>
    <div class="workspace">
        <header class="site-header">
            <p class="breadcrumb">TerangDesa <span aria-hidden="true">/</span> <span>@yield('breadcrumb', 'Beranda')</span></p>
            <div class="workspace-identity">
                <span class="workspace-identity__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m3 5 6-2 6 2 6-2v16l-6 2-6-2-6 2Zm6-2v16m6-14v16"/></svg></span>
                <div><strong>@auth{{ auth()->user()->name }}@else TerangDesa @endauth</strong><span>@auth Akun terhubung @else Pemetaan wilayah @endauth</span></div>
            </div>
        </header>
        <main id="konten" class="content-width" tabindex="-1">@yield('content')</main>
        <footer class="site-footer content-width"><span>TerangDesa · Jawa Barat</span><span>Pendataan kebutuhan energi desa</span></footer>
    </div>
</body>
</html>
