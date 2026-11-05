@extends('layouts.app')
@section('title', 'Peta Wilayah | TerangDesa')
@section('body-class', 'admin-page map-page')
@section('breadcrumb', 'Peta Wilayah')
@section('content')
    <section class="page-intro" aria-labelledby="page-title">
        <p class="eyebrow"><span aria-hidden="true"></span>DATA WILAYAH · JAWA BARAT</p>
        <h1 id="page-title">Peta Wilayah Jawa Barat</h1>
        <p>Lihat sebaran wilayah dan jumlah keluarga yang belum mendapatkan akses listrik berdasarkan data tersimpan.</p>
    </section>
    <section class="map-panel" data-map-panel data-state="loading" aria-label="Peta wilayah Jawa Barat">
        <form class="map-filters" data-map-filters role="search">
            <div class="field"><label for="map-search">Cari wilayah</label><input id="map-search" name="q" type="search" maxlength="150" placeholder="Cari desa, kecamatan, atau alamat…" autocomplete="off"></div>
            <div class="field"><label for="map-condition">Catatan KK</label><select id="map-condition" name="condition"><option value="all">Semua catatan</option><option value="has_unserved">Ada KK belum berlistrik</option><option value="no_unserved">Tercatat 0 KK belum berlistrik</option><option value="unavailable">Angka KK belum tersedia</option></select></div>
            <div class="field"><label for="map-kabupaten">Kabupaten/kota</label><select id="map-kabupaten" name="kabupaten"><option value="">Semua kabupaten/kota</option></select></div>
            <button class="button button--primary" type="submit">Terapkan</button>
            <button class="button" type="reset" data-map-filter-reset>Reset</button>
            <details class="map-advanced"><summary>Filter lanjutan</summary><div class="field"><label for="map-location-filter">Ketersediaan lokasi</label><select id="map-location-filter" name="location"><option value="all">Semua wilayah</option><option value="available">Lokasi tersedia</option><option value="missing">Lokasi belum tersedia</option></select></div></details>
        </form>
        <div class="map-quick-filters" role="group" aria-label="Filter cepat jumlah KK">
            <span>Tampilkan</span><button type="button" data-map-quick="all" aria-pressed="true">Semua wilayah</button><button type="button" data-map-quick="has_unserved" aria-pressed="false"><i class="quick-dot quick-dot--red" aria-hidden="true"></i>Ada KK belum berlistrik</button><button type="button" data-map-quick="no_unserved" aria-pressed="false"><i class="quick-dot quick-dot--green" aria-hidden="true"></i>Tercatat nol KK</button><button type="button" data-map-quick="unavailable" aria-pressed="false">Angka belum tersedia</button>
        </div>
        <div class="map-search-results" data-map-search-results hidden aria-label="Hasil pencarian wilayah"></div>
        <div class="map-data-status" role="status" aria-live="polite" data-map-data-state="loading"><span data-map-data-status>Memuat data wilayah…</span><button class="button" type="button" data-map-search-global hidden>Cari di semua kabupaten</button><button class="button" type="button" data-map-data-retry hidden>Coba lagi data</button></div>
        <div class="map-insights" aria-label="Ringkasan hasil filter"><div><span>Wilayah ditemukan</span><strong data-map-stat="total">—</strong></div><div><span>KK belum berlistrik</span><strong data-map-stat="affected_kk">—</strong></div><div><span>Wilayah tanpa angka KK</span><strong data-map-stat="unavailable">—</strong></div></div>
        <div class="map-toolbar">
            <div class="map-location"><span class="map-location__icon"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m3 5 6-2 6 2 6-2v16l-6 2-6-2-6 2Zm6-2v16m6-14v16"/></svg></span><div><h2 data-map-context>Jawa Barat</h2><span data-map-context-hint>Pilih kabupaten untuk menjelajahi desa</span></div></div>
            <div class="map-toolbar-actions"><button class="button" type="button" data-map-expand aria-expanded="false" aria-controls="region-map">Perluas peta ⛶</button><button class="button button--return" type="button" data-map-reset disabled><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M3 10a9 9 0 1 1 2 8M3 4v6h6"/></svg>Kembali ke Jawa Barat</button>
            <button class="button" type="button" data-map-show-detail hidden>Lihat detail</button></div>
        </div>
        <div class="map-workspace">
            <div class="map-stage" data-boundary-state="loading">
                <div id="region-map" data-region-map data-tile-url="{{ config('map.tile_url') }}" data-attribution="{{ config('map.attribution') }}" data-boundary-url="{{ asset('data/jawa-barat.geojson') }}" data-data-url="{{ route('wilayah.map.data') }}" data-areas-url="{{ asset('data/map-boundaries') }}" role="region" aria-label="Peta interaktif Jawa Barat" aria-describedby="map-help" aria-busy="true"></div>
                <div class="map-hover-card" data-map-preview aria-hidden="true"><span data-preview-level>JELAJAHI WILAYAH</span><strong data-preview-name>Mulai dari kabupaten</strong><div data-preview-value>Arahkan kursor ke area berwarna</div><small data-preview-hint>Klik untuk membuka desa dan jumlah KK</small></div>
                <noscript><p class="noscript-message">Aktifkan JavaScript untuk membuka peta interaktif.</p></noscript>
            </div>
            <aside class="map-detail" data-map-detail-panel aria-labelledby="map-detail-title" aria-live="polite">
                <div class="map-detail__heading"><p class="detail-eyebrow">INFORMASI WILAYAH</p><button class="map-detail__close" type="button" data-map-close-detail aria-label="Tutup detail wilayah" title="Tutup detail">×</button></div><h2 id="map-detail-title" tabindex="-1">Pilih wilayah</h2>
                <div data-map-detail><div class="map-detail-guide"><span aria-hidden="true">01 → 02</span><h3>Dari kabupaten ke desa</h3><p>Arahkan kursor untuk ringkasan cepat. Klik kabupaten, lalu pilih desa untuk melihat jumlah KK.</p></div><div class="detail-hint">Abu-abu berarti data belum tersedia atau belum cocok dengan batas desa. Angka nol KK belum berlistrik tidak menunjukkan jumlah KK yang sudah berlistrik.</div></div>
            </aside>
        </div>
        <div class="map-area-heading" data-map-area-status role="status">Memuat area kabupaten/kota…</div><div class="map-legend" data-area-legend aria-label="Legenda peta"><strong>Warna area berdasarkan data TiDB</strong><span>Ada KK belum berlistrik</span><span>Tidak tercatat KK belum berlistrik</span><span>Data belum tersedia</span></div>
        <div class="map-statusbar"><div class="map-status" role="status" aria-live="polite" aria-atomic="true"><span class="status-dot" aria-hidden="true"></span><span data-map-status>Memuat peta dasar…</span></div><button class="button button--retry" type="button" data-map-retry hidden>Coba lagi</button><p id="map-help">Klik kabupaten → desa · Arahkan kursor untuk ringkasan</p></div>
        <div class="boundary-status" role="status"><span data-boundary-status>Memuat batas administratif…</span><button class="button" type="button" data-boundary-retry hidden>Coba lagi batas</button></div>
    </section>
    <section class="map-list panel" aria-labelledby="map-list-title">
        <div class="panel-heading"><div><h2 id="map-list-title">Daftar wilayah</h2><p data-map-list-caption>Data mengikuti pencarian dan filter, termasuk wilayah tanpa lokasi.</p></div><span class="map-period" data-map-period>Periode data</span></div>
        <div class="table-scroll" tabindex="0" role="region" aria-label="Daftar wilayah pada peta"><table class="map-region-table"><thead><tr><th scope="col">Wilayah / alamat</th><th scope="col">Keluarga belum berlistrik</th><th scope="col">Berdasarkan jumlah KK</th><th scope="col">Lokasi</th><th scope="col">Informasi</th></tr></thead><tbody data-map-rows></tbody></table></div>
        <p class="map-empty" data-map-empty hidden></p>
        <div class="pagination"><span data-map-list-count>Menunggu data</span><div><button class="button" data-map-prev type="button" aria-label="Halaman wilayah sebelumnya" disabled>‹</button><span data-map-page>1</span><button class="button" data-map-next type="button" aria-label="Halaman wilayah berikutnya" disabled>›</button></div></div>
    </section>
    <aside class="data-note"><svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-10v1"/></svg><div><h2>Sumber data wilayah: database aplikasi</h2><p>Cakupan mengikuti data yang tersimpan. Satu desa dapat memiliki keluarga pengguna PLN sekaligus keluarga yang belum berlistrik. Nilai nol berarti tidak tercatat keluarga belum berlistrik pada data tersebut. Tahun dan sumber setiap wilayah tersedia pada detail.</p></div></aside>
    <details class="map-sources"><summary>Informasi data dan peta</summary><p>Poligon kabupaten dan desa berasal dari batas referensi BIG. Warna dan jumlah KK dibaca dari TiDB. Ringkasan kabupaten menunjukkan persentase desa dengan KK belum berlistrik di antara desa yang angkanya tersedia, bukan persentase rumah atau rasio elektrifikasi. Area tanpa catatan yang cocok tetap abu-abu. Batas bersifat referensi kartografis.</p><p>Batas kabupaten/desa: <a href="https://geoservices.big.go.id/rbi/rest/services/BATASWILAYAH" target="_blank" rel="noopener noreferrer">Badan Informasi Geospasial</a>, disederhanakan untuk tampilan.</p><p>Batas provinsi: <a href="https://www.geoboundaries.org/" target="_blank" rel="noopener noreferrer">geoBoundaries</a> / OpenStreetMap / Wambacher, referensi 2017, <a href="https://opendatacommons.org/licenses/odbl/1-0/" target="_blank" rel="noopener noreferrer">ODbL 1.0</a>. Batas disederhanakan untuk tampilan, bukan penetapan batas hukum.</p><a href="https://www.openstreetmap.org/fixthemap" target="_blank" rel="noopener noreferrer">Laporkan masalah peta dasar ↗</a></details>
@endsection
