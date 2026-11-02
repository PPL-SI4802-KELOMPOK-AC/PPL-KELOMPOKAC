@extends('layouts.app')
@section('title', 'Peta Wilayah | TerangDesa')
@section('body-class', 'admin-page map-page')
@section('breadcrumb', 'Peta Wilayah')
@section('content')
    <section class="page-intro" aria-labelledby="page-title">
        <p class="eyebrow"><span aria-hidden="true"></span>REFERENSI WILAYAH · 2024</p>
        <h1 id="page-title">Peta Wilayah Jawa Barat</h1>
        <p>Jelajahi data desa dan angka keluarga dari sumber, dengan status verifikasi yang terpisah.</p>
    </section>
    <section class="map-panel" data-map-panel data-state="loading" aria-label="Peta wilayah Jawa Barat">
        <form class="map-filters" data-map-filters role="search">
            <div class="field"><label for="map-search">Cari wilayah</label><input id="map-search" name="q" type="search" maxlength="150" placeholder="Nama desa atau alamat…" autocomplete="off"></div>
            <div class="field"><label for="map-layer">Lapisan data</label><select id="map-layer" name="layer"><option value="all">Semua data wilayah</option><option value="csv_positive">Indikasi CSV: angka positif</option><option value="verified">Kondisi diisi Pemerintah</option><option value="unknown">Tanpa indikasi positif / belum diketahui</option></select></div>
            <div class="field"><label for="map-kabupaten">Kabupaten / kota</label><select id="map-kabupaten" name="kabupaten"><option value="">Semua dalam dataset</option></select></div>
            <div class="field"><label for="map-location-filter">Ketersediaan lokasi</label><select id="map-location-filter" name="location"><option value="all">Semua wilayah</option><option value="available">Lokasi tersedia</option><option value="missing">Lokasi belum tersedia</option></select></div>
            <button class="button" type="submit">Terapkan</button>
        </form>
        <div class="map-data-status" role="status" aria-live="polite" data-map-data-state="loading"><span data-map-data-status>Memuat data wilayah…</span><button class="button" type="button" data-map-data-retry hidden>Coba lagi data</button></div>
        <div class="map-toolbar">
            <div class="map-location"><span class="map-location__icon"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m3 5 6-2 6 2 6-2v16l-6 2-6-2-6 2Zm6-2v16m6-14v16"/></svg></span><div><h2>Jawa Barat</h2><span>Batas provinsi · Lokasi referensi desa</span></div></div>
            <button class="button button--return" type="button" data-map-reset disabled><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M3 10a9 9 0 1 1 2 8M3 4v6h6"/></svg>Kembali ke Jawa Barat</button>
        </div>
        <div class="map-workspace">
            <div class="map-stage" data-boundary-state="loading">
                <div id="region-map" data-region-map data-tile-url="{{ config('map.tile_url') }}" data-attribution="{{ config('map.attribution') }}" data-boundary-url="{{ asset('data/jawa-barat.geojson') }}" data-data-url="{{ route('wilayah.map.data') }}" role="region" aria-label="Peta interaktif Jawa Barat" aria-describedby="map-help" aria-busy="true"></div>
                <noscript><p class="noscript-message">Aktifkan JavaScript untuk membuka peta interaktif.</p></noscript>
            </div>
            <aside class="map-detail" aria-labelledby="map-detail-title" aria-live="polite">
                <p class="detail-eyebrow">INFORMASI WILAYAH</p><h2 id="map-detail-title" tabindex="-1">Kenali data sebelum memilih</h2>
                <div data-map-detail><p>Pilih penanda atau tombol “Lihat” pada daftar untuk membaca data wilayah.</p><div class="detail-hint">Satu titik adalah <strong>lokasi referensi desa</strong>, bukan lokasi rumah, kelompok keluarga, atau calon pemasangan PLTS.</div><p>Angka CSV dan kondisi listrik yang diisi Pemerintah ditampilkan secara terpisah.</p></div>
            </aside>
        </div>
        <div class="map-legend" aria-label="Legenda peta"><span><i class="legend-dot verified"></i>Kondisi diisi Pemerintah</span><span><i class="legend-dot csv_positive"></i>Indikasi CSV positif, belum diverifikasi</span><span><i class="legend-dot unknown"></i>Belum diketahui / CSV nol atau kosong</span><span><i class="legend-cluster">n</i>Kelompok lokasi desa</span></div>
        <div class="map-statusbar"><div class="map-status" role="status" aria-live="polite" aria-atomic="true"><span class="status-dot" aria-hidden="true"></span><span data-map-status>Memuat peta dasar…</span></div><button class="button button--retry" type="button" data-map-retry hidden>Coba lagi</button><p id="map-help">Geser untuk menjelajah · Gunakan + / − untuk zoom</p></div>
        <div class="boundary-status" role="status"><span data-boundary-status>Memuat batas administratif…</span><button class="button" type="button" data-boundary-retry hidden>Coba lagi batas</button></div>
    </section>
    <section class="map-list panel" aria-labelledby="map-list-title">
        <div class="panel-heading"><div><h2 id="map-list-title">Daftar wilayah</h2><p data-map-list-caption>Data mengikuti pencarian dan filter, termasuk wilayah tanpa lokasi.</p></div><span class="map-period">CSV 2024</span></div>
        <div class="table-scroll" tabindex="0" role="region" aria-label="Daftar wilayah pada peta"><table class="map-region-table"><thead><tr><th scope="col">Wilayah / alamat</th><th scope="col">Angka belum listrik dari CSV</th><th scope="col">Kondisi Pemerintah</th><th scope="col">Lokasi</th><th scope="col">Informasi</th></tr></thead><tbody data-map-rows></tbody></table></div>
        <p class="map-empty" data-map-empty hidden></p>
        <div class="pagination"><span data-map-list-count>Menunggu data</span><div><button class="button" data-map-prev type="button" aria-label="Halaman wilayah sebelumnya" disabled>‹</button><span data-map-page>1</span><button class="button" data-map-next type="button" aria-label="Halaman wilayah berikutnya" disabled>›</button></div></div>
    </section>
    <aside class="data-note"><svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-10v1"/></svg><div><h2>Angka dari CSV — belum diverifikasi</h2><p>Dataset referensi tahun 2024 tidak membuktikan semua desa belum berlistrik. Angka keluarga dari kolom berlabel belum listrik masih memerlukan verifikasi; nilai nol tidak membuktikan seluruh desa sudah berlistrik. Keluarga tidak otomatis sama dengan rumah atau KK terdampak.</p><p>Jumlah pengguna PLN ditampilkan terpisah. Data manual Pemerintah dan koordinat database tidak ditimpa oleh pencocokan CSV.</p></div></aside>
    <details class="map-sources"><summary>Sumber geografis dan metode pencocokan</summary><p>CSV: <strong>Dataset_Wilayah_2024_Bersih.csv</strong>, dicocokkan melalui kode Kemendagri. Kabupaten/kota berasal dari kolom administratif CSV, tanpa memecah alamat. Periode indikator: 2024.</p><p>Titik referensi: <a href="{{ config('map.village_service') }}" target="_blank" rel="noopener noreferrer">Badan Informasi Geospasial, batas desa skala 1:10.000, edisi Oktober 2020</a>. Titik dipilih di dalam poligon desa yang kodenya cocok. Koordinat referensi di database diprioritaskan; lokasi yang tidak valid tidak ditempatkan sembarang.</p><p>Batas provinsi: <a href="https://www.geoboundaries.org/" target="_blank" rel="noopener noreferrer">geoBoundaries</a> / OpenStreetMap / Wambacher, referensi 2017, <a href="https://opendatacommons.org/licenses/odbl/1-0/" target="_blank" rel="noopener noreferrer">ODbL 1.0</a>. Batas disederhanakan untuk tampilan, bukan penetapan batas hukum. Perbedaan edisi geografis dan dataset dapat menyebabkan sebagian lokasi belum tersedia.</p><p data-matching-report></p><a href="https://www.openstreetmap.org/fixthemap" target="_blank" rel="noopener noreferrer">Laporkan masalah peta dasar ↗</a></details>
@endsection
