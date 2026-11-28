@extends('layouts.app')
@section('title', 'Dashboard Pemerintah | TerangDesa')
@section('breadcrumb', 'Dashboard')
@section('body-class', 'admin-page government-dashboard-page')
@section('content')
<section data-government-dashboard data-url="{{ route('pemerintah.dashboard.data') }}" data-administration-url="{{ route('pemerintah.dashboard.administrasi') }}"
    data-state="{{ $filterErrors ? 'error' : 'loading' }}">
    <div class="gov-intro">
        <h1>Gambaran layanan elektrifikasi</h1>
        <p>Ringkasan operasional <span data-gov-area>{{ $districts[$filters['kabupaten']] ?? 'Jawa Barat' }}</span> · <span data-gov-period>{{ $year ? 'Tahun '.$year : 'Semua periode' }}</span></p>
    </div>
    <div class="gov-metrics" data-gov-results aria-label="Ringkasan layanan elektrifikasi" aria-busy="{{ $filterErrors ? 'false' : 'true' }}">
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
    <section class="panel gov-filter-panel" aria-labelledby="gov-filter-title">
        <h2 id="gov-filter-title">Filter Dashboard</h2>
        <form data-gov-period-form action="{{ route('pemerintah.dashboard') }}" method="GET">
            <div class="gov-filter-fields">
                <div class="gov-filter-field">
                    <label for="dashboard-year">Periode</label>
                    <select id="dashboard-year" name="year" @if(isset($filterErrors['year'])) aria-invalid="true" @endif><option value="">Semua periode</option>@if($year)<option value="{{ $year }}" selected>{{ $year }}</option>@endif</select>
                </div>
                <div class="gov-filter-field">
                    <label for="dashboard-district">Kabupaten/kota</label>
                    <select id="dashboard-district" name="kabupaten" @if(isset($filterErrors['kabupaten'])) aria-invalid="true" @endif>
                        <option value="">Semua kabupaten/kota</option>
                        @foreach($districts as $code => $name)
                            <option value="{{ $code }}" @selected($filters['kabupaten'] === (string) $code)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="gov-filter-field">
                    <label for="dashboard-subdistrict">Kecamatan</label>
                    <select id="dashboard-subdistrict" name="kecamatan" aria-describedby="gov-administration-status" @disabled(!$filters['kabupaten']) @if(isset($filterErrors['kecamatan'])) aria-invalid="true" @endif>
                        <option value="">Semua kecamatan</option>
                        @foreach($options['subdistricts'] as $code => $name)
                            <option value="{{ $code }}" @selected($filters['kecamatan'] === $code)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="gov-filter-field">
                    <label for="dashboard-village">Desa/kelurahan</label>
                    <select id="dashboard-village" name="desa" aria-describedby="gov-administration-status" @disabled(!$filters['kecamatan']) @if(isset($filterErrors['desa'])) aria-invalid="true" @endif>
                        <option value="">Semua desa/kelurahan</option>
                        @foreach($options['villages'] as $village)
                            @if($village['subdistrict'] === $filters['kecamatan'])
                                <option value="{{ $village['code'] }}" @selected($filters['desa'] === $village['code'])>{{ $village['name'] }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <div class="gov-filter-field">
                    <label for="dashboard-condition">Kondisi listrik</label>
                    <select id="dashboard-condition" name="kondisi" @if(isset($filterErrors['kondisi'])) aria-invalid="true" @endif>
                        <option value="">Semua kondisi listrik</option>
                        <option value="unknown" @selected($filters['kondisi'] === 'unknown')>Data belum tersedia</option>
                        @foreach($conditions as $value => $label)
                            <option value="{{ $value }}" @selected($filters['kondisi'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="gov-filter-footer">
                <p class="gov-filter-draft" data-gov-draft-status role="status">Pilih filter, lalu tekan Terapkan.</p>
                <div class="gov-filter-actions">
                    <button class="button button--primary" type="submit">Terapkan</button>
                    <button class="button" data-gov-reset type="button">Reset</button>
                    <button class="button gov-filter-save" type="button" data-saved-create aria-controls="gov-saved-dialog" @disabled($filterErrors)>Simpan Tampilan</button>
                </div>
            </div>
            <div class="gov-filter-feedback"><span id="gov-administration-status" role="status"></span><button class="button" data-gov-administration-retry type="button" hidden>Coba lagi pilihan wilayah</button></div>
        </form>
        <div class="gov-filter-applied">
            <strong>Filter aktif</strong>
            <span data-gov-applied-summary>{{ $filterErrors ? 'Perbaiki pilihan untuk memuat hasil.' : ($year ? 'Tahun '.$year : 'Semua periode') }}</span>
        </div>
    </section>
    <div class="gov-dashboard-feedback">
        <div class="gov-load-status">
            <span class="gov-loading-spinner" aria-hidden="true"></span>
            <span data-gov-status role="status" aria-live="polite">{{ $filterErrors ? implode(' ', array_merge(...array_values($filterErrors))) : 'Memuat data dashboard…' }}</span>
            <button class="button" type="button" data-gov-retry hidden>Coba lagi data</button>
        </div>
        @if(Route::has('pemerintah.laporan.index'))
            <a class="button" data-gov-report href="{{ route('pemerintah.laporan.index', array_filter(['year' => $year])) }}">Lihat laporan</a>
        @else
            <button class="button" data-gov-report type="button" disabled aria-describedby="gov-destination-note">@include('admin.partials.icon', ['name' => 'grid']) Lihat laporan</button>
        @endif
    </div>
    <div class="gov-empty" data-gov-empty hidden><strong>Tidak ada hasil untuk filter aktif</strong><p>Ringkasan indikator kosong. Peta tetap menampilkan data master yang sesuai jika tersedia.</p></div>
    <section class="panel gov-map-panel" data-gov-results aria-busy="{{ $filterErrors ? 'false' : 'true' }}" aria-labelledby="gov-map-title">
        <div class="gov-map-heading"><h2 id="gov-map-title">Peta elektrifikasi wilayah</h2><button class="button" data-gov-area-back type="button" disabled>Kembali satu tingkat</button><button class="button" data-gov-map-home type="button">Lihat Jawa Barat</button></div>
        <p class="gov-map-scope">Data mengikuti <span data-gov-map-scope>filter aktif</span>. Cakupan peta <strong data-gov-area-level>Jawa Barat</strong>.</p>
        <div class="gov-area-tools">
            <label for="gov-area-search">Cari wilayah</label>
            <input id="gov-area-search" type="search" placeholder="Kabupaten, kecamatan, atau desa">
            <select data-gov-area-options aria-label="Pilih hasil pencarian atau wilayah"><option value="">Pilih wilayah</option></select>
            <label class="gov-area-points"><input type="checkbox" data-gov-area-points aria-describedby="gov-points-source"> Tampilkan penanda referensi</label>
        </div>
        <p class="gov-map-scope" id="gov-points-source">Titik memakai koordinat referensi master wilayah. Lokasi rumah warga dan PLTS perlu diverifikasi secara terpisah.</p>
        <div class="gov-area-layout">
            <div data-gov-map data-tile-url="{{ config('map.tile_url') }}" data-attribution="{{ config('map.attribution') }}" data-boundary-url="{{ asset('data/jawa-barat.geojson') }}" data-areas-url="{{ asset('data/map-boundaries') }}" data-detail-url="{{ route('pemerintah.dashboard.wilayah') }}" role="region" aria-label="Peta layanan elektrifikasi Jawa Barat"></div>
            <aside class="gov-area-detail" aria-label="Detail wilayah" aria-live="polite" data-gov-area-detail><h3>Detail wilayah</h3><p>Pilih desa atau kelurahan untuk melihat kondisi, pengajuan, dan progres.</p></aside>
        </div>
        <div class="gov-legend" aria-label="Legenda peta Pemerintah"><span><i data-category="unserved"></i>Ada kebutuhan akses listrik</span><span><i data-category="needs_data"></i>Perlu pendataan</span><span><i data-category="outside"></i>Tidak cocok dengan filter aktif</span><span><i data-category="missing_master"></i>Master wilayah belum tersedia</span><span><i data-submission-outline aria-hidden="true"></i>Ada pengajuan terhubung pada periode aktif</span></div>
        <p class="gov-map-scope">Warna kabupaten dan kecamatan merangkum desa sesuai filter. Oranye berarti setidaknya satu desa memiliki kebutuhan akses listrik.</p>
        <p class="gov-map-scope" data-gov-area-status role="status"></p>
        <div class="gov-map-status" role="status" aria-live="polite"><span data-gov-tile-status>Memuat peta dasar…</span><button class="button" data-gov-tile-retry type="button" hidden>Coba lagi peta</button></div>
        <div class="gov-selected" data-gov-selected aria-live="polite">Pilih penanda untuk melihat nama wilayah dan KK sasaran. Nol KK belum berlistrik bukan bukti pemasangan PLTS.</div>
        <details class="gov-map-list"><summary>Daftar wilayah terpetakan</summary><label for="gov-village">Pilih wilayah</label><select id="gov-village" data-gov-village><option value="">Pilih wilayah</option></select></details>
    </section>
    @include('pemerintah.dashboard-saved-views')
    <div class="gov-grid" data-gov-results aria-busy="{{ $filterErrors ? 'false' : 'true' }}">
        <section class="panel gov-chart" aria-labelledby="gov-chart-title">
            <h2 id="gov-chart-title">Sebaran status pengajuan</h2><p><span data-gov-chart-total>—</span> pengajuan · 6 kategori status</p>
            <div class="gov-bars" data-gov-bars aria-label="Grafik batang status pengajuan"></div>
            <p class="gov-chart-focus" data-gov-chart-focus aria-live="polite">Arahkan kursor atau fokus ke batang untuk membaca jumlah.</p>
            <table class="gov-status-table"><caption>Rekap status pengajuan</caption><thead><tr><th scope="col">Status</th><th scope="col">Jumlah</th></tr></thead><tbody data-gov-status-rows></tbody></table>
        </section>
            <section class="panel gov-follow" aria-labelledby="gov-follow-title">
                <h2 id="gov-follow-title">Rekap &amp; tindak lanjut</h2>
                <div class="gov-follow-numbers"><p><strong data-gov-follow="unserved">—</strong> wilayah masih memiliki kebutuhan akses listrik</p><p><strong data-gov-follow="installed">—</strong> wilayah PLTS terpasang</p></div>
                <p><strong data-gov-follow="waiting">—</strong> pengajuan menunggu pemeriksaan (status Diajukan).</p>
                <p><strong data-gov-follow="unlocated">—</strong> wilayah belum memiliki lokasi valid dan tidak ditampilkan sebagai penanda.</p>
                <p data-gov-unlinked-note hidden><strong data-gov-follow="unlinked_submissions_global">—</strong> pengajuan pada periode terpilih belum terhubung ke master wilayah. Jumlah ini mencakup semua wilayah.</p>
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
    <details class="gov-data-notes"><summary>Kelengkapan dan sumber indikator</summary><ul data-gov-notes></ul><p>Penanda hijau memerlukan bukti pemasangan PLTS. Merah menunjukkan informasi layanan yang masih perlu dilengkapi. Sumber: database aplikasi.</p></details>
    <script type="application/json" data-gov-administration-options>@json($options)</script>
    <noscript><p>Aktifkan JavaScript untuk memuat ringkasan dan peta interaktif.</p></noscript>
</section>
@endsection
