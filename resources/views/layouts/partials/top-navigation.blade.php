        <header class="site-header location-site-header">
            <a class="brand" href="{{ route('home') }}" aria-label="TerangDesa, beranda">
                <span class="brand__symbol" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1.5 1.5m11 11L19 19M5 19l1.5-1.5m11-11L19 5"/></svg></span>
                <span class="brand__text">TerangDesa<small>PEMETAAN &amp; PENGAJUAN PLTS</small></span>
            </a>
            <nav class="top-nav" aria-label="Navigasi utama">
                @if(auth()->user()?->role !== 'masyarakat')
                    <a href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif>Beranda</a>
                @endif
                <a href="{{ route('wilayah.map') }}" @if(request()->routeIs('wilayah.map')) aria-current="page" @endif>Peta wilayah</a>
                @if(auth()->user()?->role === 'masyarakat')
                <a href="{{ route('pengajuan.lokasi') }}" @if(request()->routeIs('pengajuan.*') && !request()->routeIs('pengajuan.riwayat')) aria-current="page" @endif>Pengajuan PLTS</a>
                <a href="{{ route('pengajuan.riwayat') }}" @if(request()->routeIs('pengajuan.riwayat')) aria-current="page" @endif>Riwayat</a>
                @elseif(auth()->user()?->role === 'pemerintah')
                    <a href="{{ route('wilayah.index') }}">Data wilayah</a>
                @endif
            </nav>
            <div class="workspace-identity">
                @auth<span class="account-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 2)) }}</span>@endauth
                @auth<div><strong>{{ auth()->user()->name }}</strong><span>{{ ucfirst(auth()->user()->role ?? 'Akun terhubung') }}</span></div>@endauth
                @auth
                    <form action="{{ route('logout') }}" method="POST" data-leave-form>@csrf<button class="button" type="submit">Keluar</button></form>
                @else
                    <a class="button" href="{{ route('login') }}">Masuk</a>
                @endauth
            </div>
        </header>
