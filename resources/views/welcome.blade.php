@extends('layouts.app')
@section('title', 'TerangDesa | Beranda')
@section('body-class', 'admin-page home-dashboard')
@section('content')
<div data-dashboard data-summary-url="{{ route('dashboard.data') }}">
    <div class="dashboard-pointer-glow" data-dashboard-glow aria-hidden="true"></div>
    <section class="home-hero" aria-labelledby="home-title">
        <div class="home-hero-copy">
            <p class="eyebrow"><span aria-hidden="true"></span>TERANGDESA</p>
            <h1 id="home-title">Kenali kebutuhan listrik desa.<br><span>Dukung akses energi yang lebih merata.</span></h1>
            <p class="home-description">TerangDesa membantu pendataan wilayah, pengajuan kebutuhan PLTS oleh kepala desa atau perwakilan desa, serta pemantauan penanganan oleh Pemerintah.</p>
            <div class="home-hero-actions"><a class="button button--primary" href="{{ route('wilayah.map') }}">Jelajahi Peta Wilayah <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></a><a class="button home-how-link" href="#cara-kerja">Lihat Cara Kerja <span aria-hidden="true">↓</span></a></div>
            <p class="home-sdg-label"><span aria-hidden="true">7</span> Mendukung SDG 7 — Energi Bersih dan Terjangkau</p>
        </div>
        <figure class="solar-scene" data-solar-scene aria-labelledby="solar-caption">
            <div class="solar-scene-label" aria-hidden="true"><span></span> ENERGI UNTUK DESA</div>
            <svg class="solar-art" viewBox="0 0 560 410" xmlns="http://www.w3.org/2000/svg" aria-label="Ilustrasi konseptual panel surya, matahari, dan permukiman">
                <defs>
                    <linearGradient id="solar-sky" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#f5fbff" stop-opacity=".65"/><stop offset="1" stop-color="#d8e8f2" stop-opacity=".65"/></linearGradient>
                    <linearGradient id="solar-surface" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#23617e"/><stop offset="1" stop-color="#0d3f5b"/></linearGradient>
                    <clipPath id="solar-panel-clip"><path d="m158 203 224-43 84 107-242 56Z"/></clipPath>
                </defs>
                <g class="solar-layer solar-layer--land" data-solar-layer="land" aria-hidden="true">
                    <ellipse cx="310" cy="221" rx="230" ry="161" fill="url(#solar-sky)"/>
                    <path d="M12 297c87-71 143-24 213-49 109-40 183-26 327 24v119H12Z" fill="#d0e2eb"/>
                    <path d="M-10 344c119-54 189-49 260-31 130 32 201-32 326 4v94H-10Z" fill="#bcd4de"/>
                    <path d="M-5 380c90-35 199-28 301-7 88 18 157-4 275-18v59H-5Z" fill="#e9f3f6"/>
                    <path d="M62 338c52-22 96-13 141 5m174 11c49-19 89-21 132-11" stroke="#9ab8c7" stroke-width="1.5" fill="none"/>
                    <g transform="translate(50 215)">
                        <path d="m0 54 47-35 45 35v67H0Z" fill="#f7faf8"/><path d="m-10 58 57-46 55 46" fill="none" stroke="#315e73" stroke-width="8" stroke-linejoin="round"/>
                        <path d="M34 121V81h25v40" fill="#b7d2df"/><rect x="10" y="74" width="15" height="20" rx="2" fill="#f5ac00"/><rect x="69" y="74" width="15" height="20" rx="2" fill="#d7e7ed"/>
                    </g>
                    <g transform="translate(419 224)">
                        <path d="m0 39 34-25 36 25v66H0Z" fill="#fffdf4"/><path d="m-8 42 42-34 45 34" fill="none" stroke="#315e73" stroke-width="7" stroke-linejoin="round"/>
                        <rect x="13" y="57" width="17" height="20" rx="2" fill="#f5ac00"/><path d="M44 105V70h16v35" fill="#afcdd9"/>
                    </g>
                    <path d="M32 355v-35m-11 22 11-15 11 15m463 14v-38m-12 22 12-18 12 18" stroke="#729eac" stroke-width="4" fill="none"/>
                </g>
                <g class="solar-layer solar-layer--sun" data-solar-layer="sun" aria-hidden="true">
                    <circle cx="381" cy="108" r="59" fill="#f5ac00" opacity=".09"/><circle cx="381" cy="108" r="44" fill="#f5ac00" opacity=".17"/>
                    <circle cx="381" cy="108" r="31" fill="#f5ac00"/>
                    <path d="M381 59V49m0 118v-10m49-49h10m-118 0h10m84-35 7-7m-83 83 7-7m69 0 7 7m-83-83 7 7" stroke="#e3a000" stroke-width="3" fill="none"/>
                    <path d="m342 149-19 22m22-8-12 15m-3-31-14 16" stroke="#dfab37" stroke-width="2" opacity=".65" fill="none"/>
                </g>
                <g class="solar-layer solar-layer--panel" data-solar-layer="panel">
                    <g aria-hidden="true"><ellipse cx="297" cy="354" rx="111" ry="15" fill="#7296a5" opacity=".17"/><path d="m254 293-10 57m119-83 15 75m-134 8 134-8" stroke="#426b7d" stroke-width="9" fill="none"/><path d="m257 301 109 41" stroke="#8caeba" stroke-width="4" fill="none"/></g>
                    <g class="solar-panel-control" data-solar-panel role="button" tabindex="0" aria-label="Pelajari cara kerja panel surya" aria-describedby="solar-tooltip" aria-controls="solar-tooltip" aria-expanded="false">
                        <path d="m158 203 224-43 84 107-242 56Z" fill="url(#solar-surface)" stroke="#0d3f5b" stroke-width="6" stroke-linejoin="round"/>
                        <g clip-path="url(#solar-panel-clip)" stroke="#8bc1d9" stroke-width="1.3" opacity=".65" fill="none"><path d="m188 194 70 130m-24-138 75 130m-27-138 82 128m-32-138 86 127M160 230l263-52m-244 82 266-56m-244 87 264-60"/></g>
                        <path class="solar-panel-highlight" d="m158 203 224-43 84 107-242 56Z" fill="#bdeeff" opacity="0" stroke="#f5ac00" stroke-width="4"/>
                        <path d="m171 206 209-40" stroke="#90c4d9" stroke-width="2" fill="none"/>
                    </g>
                    <path d="M378 316c17 12 28 12 36 0 8-11 17-9 24 2" stroke="#d69a10" stroke-width="3" fill="none" aria-hidden="true"/>
                </g>
            </svg>
            <div id="solar-tooltip" class="solar-tooltip" role="tooltip" hidden>PLTS memanfaatkan energi matahari</div>
            <figcaption id="solar-caption">Ilustrasi konseptual energi surya · bukan data lokasi</figcaption>
        </figure>
    </section>

    <section class="home-metrics" aria-label="Ringkasan data wilayah">
        <article><span class="metric-index" aria-hidden="true">01 / REFERENSI</span><strong data-metric="total">—</strong><h2>Total wilayah referensi tersimpan</h2><p>Jumlah seluruh wilayah pada aplikasi.</p></article>
        <article><span class="metric-index" aria-hidden="true">02 / KEBUTUHAN LISTRIK</span><strong data-metric="unserved_kk">—</strong><h2>KK belum berlistrik</h2><p>Jumlah KK dari wilayah yang angkanya tersedia.</p></article>
        <article><span class="metric-index" aria-hidden="true">03 / AKSES LISTRIK</span><strong data-metric="served_kk">—</strong><h2>KK sudah berlistrik</h2><p>Data jumlah KK sudah berlistrik belum tersedia.</p></article>
    </section>
    <div class="dashboard-load-status" role="status" aria-live="polite"><span data-dashboard-status>Memuat ringkasan dari database…</span><button class="button" type="button" data-dashboard-retry hidden>Coba lagi ringkasan</button></div>

    <section class="home-chart panel" aria-labelledby="condition-chart-title" data-chart-state="loading">
        <div class="home-section-heading"><div><p class="section-kicker">GAMBARAN DATA</p><h2 id="condition-chart-title">Sebaran KK Belum Berlistrik</h2><p>Jumlah wilayah berdasarkan catatan KK belum berlistrik. Grafik menghitung wilayah, bukan jumlah KK.</p></div><button class="button" type="button" data-chart-reset disabled>Reset sorotan <span aria-hidden="true">↺</span></button></div>
        <div class="chart-placeholder" data-chart-placeholder>Menyiapkan visualisasi…</div>
        <div class="condition-chart-layout" data-chart-content hidden>
            <div class="condition-chart-visual"><svg viewBox="0 0 260 260" role="group" aria-label="Grafik jumlah wilayah berdasarkan jumlah KK belum berlistrik" data-condition-chart><circle cx="130" cy="130" r="94" fill="none" stroke="#edf2f5" stroke-width="28"/><g data-chart-segments></g><text x="130" y="127" text-anchor="middle" class="chart-total" data-chart-total aria-hidden="true"></text><text x="130" y="151" text-anchor="middle" class="chart-unit" aria-hidden="true">wilayah</text></svg><p data-chart-selection>Semua kategori</p></div>
            <div class="condition-chart-explainer"><p class="section-kicker">CATATAN KK PADA WILAYAH</p><div class="condition-legend" data-condition-legend aria-label="Sorot kelompok jumlah KK"></div><p class="chart-state-note" data-chart-note></p><div class="condition-tooltip" id="condition-tooltip" role="tooltip" aria-live="polite" data-condition-tooltip>Arahkan kursor atau fokus ke grafik untuk membaca arti kategori. Pilih legenda untuk menyorot.</div></div>
        </div>
        <div class="condition-table-wrap" data-chart-table-wrap hidden><table class="condition-table"><caption>Ringkasan wilayah dan KK belum berlistrik</caption><thead><tr><th scope="col">Catatan KK</th><th scope="col">Jumlah wilayah</th><th scope="col">KK belum berlistrik</th><th scope="col">Keterangan</th></tr></thead><tbody data-condition-table></tbody></table></div>
        <p class="chart-source-note">Sumber: jumlah KK terdampak pada tabel wilayah di database aplikasi. Jumlah KK sudah berlistrik belum tersedia. Wilayah dengan angka nol tidak digunakan untuk menebak jumlah KK yang sudah berlistrik.</p>
    </section>

    <div class="home-bottom-grid">
        <section class="home-workflow panel" id="cara-kerja" tabindex="-1" aria-labelledby="workflow-title"><p class="section-kicker">DARI INFORMASI KE TINDAK LANJUT</p><h2 id="workflow-title">Bagaimana TerangDesa bekerja?</h2><ol class="workflow-steps">
            <li><span aria-hidden="true">01</span><div><h3>Pendataan wilayah</h3><p>Pemerintah mengelola informasi lokasi dan kondisi akses listrik.</p></div></li>
            <li><span aria-hidden="true">02</span><div><h3>Pengajuan kebutuhan</h3><p>Kepala desa atau perwakilan desa menyampaikan lokasi, jumlah KK terdampak, kondisi, dan bukti pendukung.</p></div></li>
            <li><span aria-hidden="true">03</span><div><h3>Pemeriksaan dan verifikasi</h3><p>Pemerintah memeriksa laporan dan memperbarui status pengajuan.</p></div></li>
            <li><span aria-hidden="true">04</span><div><h3>Pemantauan penanganan</h3><p>Perkembangan dicatat agar tindak lanjut dapat ditelusuri.</p></div></li>
        </ol><p class="workflow-note">Satu lokasi pengajuan dapat mewakili kelompok warga, bukan setiap rumah.</p><a class="home-text-link" href="{{ route('wilayah.index') }}">Buka Data Wilayah <span aria-hidden="true">→</span></a></section>
        <section class="home-sdg" aria-labelledby="sdg-title"><div class="sdg-typographic-badge" aria-label="SDG 7"><span>SDG</span><strong>7</strong></div><p class="section-kicker">ENERGI UNTUK SEMUA</p><h2 id="sdg-title">Mendukung SDG 7:<br>Energi Bersih dan Terjangkau</h2><p>TerangDesa mendukung pendataan kebutuhan akses energi melalui informasi wilayah, pengajuan kebutuhan PLTS, dan pemantauan penanganan. Data yang lebih terstruktur membantu Pemerintah memahami kebutuhan masyarakat dan menindaklanjuti laporan.</p><p class="sdg-definition">SDG 7 berkaitan dengan akses energi yang terjangkau, andal, berkelanjutan, dan modern bagi semua.</p></section>
    </div>
    <noscript><p class="notice">Aktifkan JavaScript untuk memuat ringkasan database dan menggunakan interaksi ilustrasi. Navigasi serta penjelasan alur tetap tersedia.</p></noscript>
</div>
@endsection
