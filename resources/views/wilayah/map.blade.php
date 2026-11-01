@extends('layouts.app')
@section('title', 'Peta Wilayah | TerangDesa')
@section('breadcrumb', 'Peta Wilayah')
@section('content')
    <section class="page-intro" aria-labelledby="page-title">
        <h1 id="page-title">Peta Wilayah</h1>
        <p>Jelajahi wilayah pendataan kebutuhan listrik di Jawa Barat.</p>
    </section>
    <section class="map-panel" data-map-panel data-state="loading" aria-label="Peta wilayah Jawa Barat">
        <div class="map-toolbar">
            <div class="map-location">
                <span class="map-location__icon"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m3 5 6-2 6 2 6-2v16l-6 2-6-2-6 2Zm6-2v16m6-14v16"/></svg></span>
                <div><h2>Jawa Barat</h2><span>Peta dasar · Indonesia</span></div>
            </div>
            <button class="button button--return" type="button" data-map-reset disabled>
                <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M3 10a9 9 0 1 1 2 8M3 4v6h6"/></svg>Kembali ke Jawa Barat
            </button>
        </div>
        <div class="map-stage">
            <div id="region-map" data-region-map data-tile-url="{{ config('map.tile_url') }}" data-attribution="{{ config('map.attribution') }}" role="region" aria-label="Peta interaktif Jawa Barat" aria-describedby="map-help" aria-busy="true"></div>
            <noscript><p class="noscript-message">Aktifkan JavaScript untuk membuka peta interaktif.</p></noscript>
        </div>
        <div class="map-statusbar">
            <div class="map-status" role="status" aria-live="polite" aria-atomic="true"><span class="status-dot" aria-hidden="true"></span><span data-map-status>Memuat peta dasar…</span></div>
            <button class="button button--retry" type="button" data-map-retry hidden>Coba lagi</button>
            <p id="map-help">Geser untuk menjelajah · Gunakan + / − untuk zoom</p>
        </div>
    </section>
    <aside class="data-note" aria-labelledby="data-note-title">
        <svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-10v1"/></svg>
        <div><h2 id="data-note-title">Tentang data wilayah</h2>
            <p>Dataset merupakan referensi administratif tahun 2024. Informasi elektrifikasi dan jumlah KK terdampak masih memerlukan verifikasi.</p>
            <p>Peta dasar belum menampilkan penanda maupun detail wilayah. Satu titik pengajuan nantinya dapat mewakili kelompok warga, bukan setiap rumah.</p>
        </div>
        <a class="map-issue" href="https://www.openstreetmap.org/fixthemap" target="_blank" rel="noopener noreferrer">Laporkan masalah peta <span aria-hidden="true">↗</span></a>
    </aside>
@endsection
