@extends('layouts.app')
@section('title', 'Dashboard Pemerintah | TerangDesa')
@section('breadcrumb', 'Dashboard')
@section('body-class', 'admin-page government-dashboard-page')
@section('content')
<section data-government-dashboard data-url="{{ route('pemerintah.dashboard.data') }}" data-state="loading" aria-busy="true">
    <div class="gov-intro">
        <div><h1>Gambaran layanan elektrifikasi</h1><p>Ringkasan operasional Jawa Barat · <span data-gov-period>{{ $year ? 'Tahun '.$year : 'Semua periode' }}</span></p></div>
        <div class="gov-intro-actions">
            <form data-gov-period-form action="{{ route('pemerintah.dashboard') }}" method="GET">
                <label for="dashboard-year">Periode</label>
                <select id="dashboard-year" name="year"><option value="">Semua periode</option>@if($year)<option value="{{ $year }}" selected>{{ $year }}</option>@endif</select>
                <button class="button" type="submit">Terapkan</button>
            </form>
            @if(Route::has('pemerintah.laporan.index'))
                <a class="button" data-gov-report href="{{ route('pemerintah.laporan.index', array_filter(['year' => $year])) }}">Lihat laporan</a>
            @else
                <button class="button" data-gov-report type="button" disabled aria-describedby="gov-destination-note">@include('admin.partials.icon', ['name' => 'grid']) Lihat laporan</button>
            @endif
        </div>
    </div>
    <div class="gov-load-status" role="status" aria-live="polite"><span data-gov-status>Memuat data dashboard…</span><button class="button" type="button" data-gov-retry hidden>Coba lagi data</button></div>
    <div class="gov-empty" data-gov-empty hidden>Belum ada data pengajuan atau wilayah pada periode ini. Peta dasar tetap dapat digunakan.</div>
    <div class="gov-metrics" aria-label="Ringkasan layanan elektrifikasi">
        @foreach([
            ['submitted', 'Total pengajuan', 'Pengajuan terkirim, tanpa draft', 'grid'],
            ['mapped', 'Wilayah terpetakan', 'Wilayah unik berkoordinat valid', 'database'],
            ['served_kk', 'KK terlayani', 'Realisasi terhadap KK sasaran', 'users'],
            ['completed_plts', 'PLTS selesai', 'Berdasarkan catatan pemasangan', 'sun'],
        ] as [$key, $title, $help, $icon])
        <article class="panel gov-metric"><span class="gov-metric-icon">@include('admin.partials.icon', ['name' => $icon])</span><h2>{{ $title }}</h2><strong data-gov-metric="{{ $key }}">—</strong><p data-gov-help="{{ $key }}">{{ $help }}</p></article>
        @endforeach
    </div>
    <p class="gov-scope" data-gov-scope>Indikator akan ditampilkan setelah data berhasil dimuat.</p>
    <div class="gov-grid">
        <section class="panel gov-chart" aria-labelledby="gov-chart-title">
            <h2 id="gov-chart-title">Sebaran status pengajuan</h2><p><span data-gov-chart-total>—</span> pengajuan · 6 kategori status</p>
            <div class="gov-bars" data-gov-bars aria-label="Grafik batang status pengajuan"></div>
            <p class="gov-chart-focus" data-gov-chart-focus aria-live="polite">Arahkan kursor atau fokus ke batang untuk membaca jumlah.</p>
            <table class="gov-status-table"><caption>Rekap status pengajuan</caption><thead><tr><th scope="col">Status</th><th scope="col">Jumlah</th></tr></thead><tbody data-gov-status-rows></tbody></table>
        </section>
        <div class="gov-right">
            <section class="panel gov-map-panel" aria-labelledby="gov-map-title">
                <div class="gov-map-heading"><h2 id="gov-map-title">Jawa Barat</h2><span>Lokasi referensi wilayah</span><button class="button" data-gov-map-home type="button">Lihat Jawa Barat</button></div>
                <div data-gov-map data-tile-url="{{ config('map.tile_url') }}" data-attribution="{{ config('map.attribution') }}" data-boundary-url="{{ asset('data/jawa-barat.geojson') }}" role="region" aria-label="Peta layanan elektrifikasi Jawa Barat"></div>
                <div class="gov-legend" aria-label="Legenda peta"><span><i data-category="unserved"></i>Belum berlistrik</span><span><i data-category="installed"></i>PLTS terpasang</span><span><i data-category="needs_data"></i>Perlu pendataan</span></div>
                <div class="gov-map-status" role="status" aria-live="polite"><span data-gov-tile-status>Memuat peta dasar…</span><button class="button" data-gov-tile-retry type="button" hidden>Coba lagi peta</button></div>
                <div class="gov-selected" data-gov-selected aria-live="polite">Pilih penanda untuk melihat nama wilayah dan KK sasaran. Nol KK belum berlistrik bukan bukti pemasangan PLTS.</div>
                <details class="gov-map-list"><summary>Daftar wilayah terpetakan</summary><label for="gov-village">Pilih wilayah</label><select id="gov-village" data-gov-village><option value="">Pilih wilayah</option></select></details>
            </section>
            <section class="panel gov-follow" aria-labelledby="gov-follow-title">
                <h2 id="gov-follow-title">Rekap &amp; tindak lanjut</h2>
                <div class="gov-follow-numbers"><p><strong data-gov-follow="unserved">—</strong> wilayah masih memiliki kebutuhan akses listrik</p><p><strong data-gov-follow="installed">—</strong> wilayah PLTS terpasang</p></div>
                <p><strong data-gov-follow="waiting">—</strong> pengajuan menunggu pemeriksaan (status Diajukan).</p>
                <p><strong data-gov-follow="unlocated">—</strong> wilayah belum memiliki lokasi valid dan tidak ditampilkan sebagai penanda.</p>
                <p data-gov-unlinked-note hidden><strong data-gov-follow="unlinked_submissions">—</strong> pengajuan belum terhubung ke master wilayah dan belum dapat dipetakan sebagai wilayah pada periode terpilih.</p>
                @if(Route::has('pemerintah.pemeriksaan.index'))
                    <a class="button" data-gov-examination href="{{ route('pemerintah.pemeriksaan.index', array_filter(['year' => $year])) }}">Buka pemeriksaan pengajuan →</a>
                @else
                    <button class="button" type="button" data-gov-examination disabled aria-describedby="gov-destination-note">Buka pemeriksaan pengajuan →</button>
                @endif
                @unless(Route::has('pemerintah.laporan.index') && Route::has('pemerintah.pemeriksaan.index'))
                    <p class="gov-muted" id="gov-destination-note">Halaman laporan atau pemeriksaan belum tersedia pada proyek ini. Tautan akan aktif setelah halaman terkait tersedia.</p>
                @endunless
            </section>
        </div>
    </div>
    <details class="gov-data-notes"><summary>Kelengkapan dan sumber indikator</summary><ul data-gov-notes></ul><p>Penanda hijau memerlukan bukti pemasangan PLTS. Merah menunjukkan informasi layanan yang masih perlu dilengkapi. Sumber: database aplikasi.</p></details>
    <noscript><p>Aktifkan JavaScript untuk memuat ringkasan dan peta interaktif.</p></noscript>
</section>
@endsection
