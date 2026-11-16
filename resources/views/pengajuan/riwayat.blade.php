@extends('layouts.app')
@section('title', 'Riwayat Pengajuan | TerangDesa')
@section('body-class', 'admin-page location-page history-page')
@section('breadcrumb', 'Riwayat pengajuan')
@section('content')
    @php
        $locationCatalog = app(\App\Services\Pengajuan\LocationCatalog::class);
    @endphp

    <section class="page-intro manage-intro" aria-labelledby="history-title">
        <div>
            <h1 id="history-title">Riwayat Pengajuan Anda</h1>
            <p>Pantau seluruh usulan PLTS yang Anda kirim sebagai perwakilan wilayah.</p>
        </div>
        <a class="button button--primary" href="{{ route('pengajuan.lokasi') }}">
            <span aria-hidden="true">+</span> Pengajuan Baru
        </a>
    </section>

    <section class="history-summary" aria-label="Ringkasan pengajuan Anda">
        <article class="history-summary-card">
            <div class="history-summary-heading">
                <p>Total Pengajuan</p>
                <span class="history-summary-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><rect x="8" y="5" width="12" height="15" rx="2"/><path d="M5 16V4a2 2 0 0 1 2-2h9M12 9h4m-4 4h4m-4 4h2"/></svg>
                </span>
            </div>
            <strong class="history-summary-value">{{ number_format($summary['total'], 0, ',', '.') }}</strong>
            <span class="history-summary-caption">Seluruh pengajuan yang sudah dikirim</span>
        </article>
        <article class="history-summary-card">
            <div class="history-summary-heading">
                <p>Dalam Tindak Lanjut</p>
                <span class="history-summary-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                </span>
            </div>
            <strong class="history-summary-value">{{ number_format($summary['in_follow_up'], 0, ',', '.') }}</strong>
            <span class="history-summary-caption">Diajukan hingga diproses</span>
        </article>
        <article class="history-summary-card">
            <div class="history-summary-heading">
                <p>Selesai</p>
                <span class="history-summary-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/></svg>
                </span>
            </div>
            <strong class="history-summary-value">{{ number_format($summary['completed'], 0, ',', '.') }}</strong>
            <span class="history-summary-caption">Status pengajuan selesai</span>
        </article>
    </section>

    <section class="panel list-panel" aria-label="Filter dan daftar riwayat pengajuan">
        <form class="history-filters" method="GET" action="{{ route('pengajuan.riwayat') }}">
            <div class="history-filter-field">
                <label for="history-search">Cari pengajuan</label>
                <input
                    id="history-search"
                    type="search"
                    name="q"
                    value="{{ $filters['q'] }}"
                    placeholder="Nomor pengajuan atau nama desa"
                    maxlength="150"
                >
            </div>
            <div class="history-filter-field">
                <label for="history-status">Status</label>
                <select id="history-status" name="status">
                    <option value="">Semua status</option>
                    @foreach($statusOptions as $statusOption)
                        <option value="{{ $statusOption }}" @selected($filters['status'] === $statusOption)>
                            {{ ucfirst(str_replace('_', ' ', $statusOption)) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="history-filter-field">
                <label for="history-period">Periode</label>
                <input
                    id="history-period"
                    type="month"
                    name="periode"
                    value="{{ $filters['periode'] }}"
                    aria-describedby="history-period-help"
                >
                <small id="history-period-help">Pilih bulan pengajuan dikirim (YYYY-MM).</small>
            </div>
            <div class="history-filter-actions">
                <button class="button button--primary" type="submit">Terapkan</button>
                @if($filters['q'] !== '' || $filters['status'] !== '' || $filters['periode'] !== '')
                    <a class="button" href="{{ route('pengajuan.riwayat') }}">Reset</a>
                @endif
            </div>
        </form>
        <div class="table-scroll" tabindex="0" role="region" aria-label="Tabel riwayat pengajuan">
            <table class="region-table">
                <thead>
                    <tr>
                        <th scope="col">Nomor Pengajuan</th>
                        <th scope="col">Tanggal</th>
                        <th scope="col">Desa</th>
                        <th scope="col">Kabupaten</th>
                        <th scope="col">Status</th>
                        <th scope="col">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pengajuans as $pengajuan)
                        @php
                            $address = trim((string) ($pengajuan->lokasi?->alamat_deskriptif ?? ''));
                            $addressFields = $address !== '' ? $locationCatalog->addressFields($address) : null;
                            $village = $addressFields && $addressFields['kabupaten'] !== ''
                                ? $locationCatalog->selection($addressFields['kabupaten'], $addressFields['kecamatan'], $addressFields['desa'])
                                : null;
                            $district = $village
                                ? ($locationCatalog->districts()[$addressFields['kabupaten']] ?? null)
                                : null;
                        @endphp
                        <tr>
                            <td><strong>{{ $pengajuan->nomor_tiket }}</strong></td>
                            <td>
                                @if($pengajuan->tanggal_pengiriman_pertama)
                                    {{ \Illuminate\Support\Carbon::parse($pengajuan->tanggal_pengiriman_pertama)->locale('id')->translatedFormat('d M Y') }}
                                @else
                                    Tanggal tidak tersedia
                                @endif
                            </td>
                            <td>
                                @if($village)
                                    {{ $village['name'] }}
                                @elseif($address !== '')
                                    <span class="row-address">{{ $address }}</span>
                                @else
                                    &mdash;
                                @endif
                            </td>
                            <td>{{ $district ?: 'Tidak tersedia' }}</td>
                            <td>
                                @if($pengajuan->status_terkini)
                                    <span class="condition-badge" data-history-status="{{ $pengajuan->status_terkini }}">{{ ucfirst(str_replace('_', ' ', $pengajuan->status_terkini)) }}</span>
                                @else
                                    &mdash;
                                @endif
                                <small class="history-status-updated">
                                    Diperbarui:
                                    @if(! empty($pengajuan->status_diperbarui_pada))
                                        {{ \Illuminate\Support\Carbon::parse($pengajuan->status_diperbarui_pada)->locale('id')->translatedFormat('d M Y, H:i') }}
                                    @else
                                        Waktu pembaruan tidak tersedia
                                    @endif
                                </small>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a class="button" href="{{ route('pengajuan.terkirim', ['pengajuan' => $pengajuan->id]) }}">Lihat detail</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">Belum ada riwayat pengajuan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="history-pagination-row">
            <p class="history-results" aria-live="polite">
                @if($pengajuans->total() > 0)
                    Menampilkan {{ $pengajuans->firstItem() }}–{{ $pengajuans->lastItem() }} dari {{ $pengajuans->total() }} pengajuan
                @else
                    Menampilkan 0 pengajuan
                @endif
            </p>
            @if($pengajuans->total() > 0)
                <nav class="history-pagination" aria-label="Navigasi halaman riwayat pengajuan">
                    @if($pengajuans->onFirstPage())
                        <span class="history-page-link is-disabled" aria-disabled="true">Sebelumnya</span>
                    @else
                        <a class="history-page-link" href="{{ $pengajuans->previousPageUrl() }}" rel="prev">Sebelumnya</a>
                    @endif

                    @php
                        $firstVisiblePage = max(1, $pengajuans->currentPage() - 2);
                        $lastVisiblePage = min($pengajuans->lastPage(), $pengajuans->currentPage() + 2);
                    @endphp
                    @if($firstVisiblePage > 1)
                        <a class="history-page-link" href="{{ $pengajuans->url(1) }}" aria-label="Halaman 1">1</a>
                        @if($firstVisiblePage > 2)<span class="history-page-ellipsis" aria-hidden="true">…</span>@endif
                    @endif
                    @for($page = $firstVisiblePage; $page <= $lastVisiblePage; $page++)
                        @if($page === $pengajuans->currentPage())
                            <span class="history-page-link is-current" aria-current="page">{{ $page }}</span>
                        @else
                            <a class="history-page-link" href="{{ $pengajuans->url($page) }}" aria-label="Halaman {{ $page }}">{{ $page }}</a>
                        @endif
                    @endfor
                    @if($lastVisiblePage < $pengajuans->lastPage())
                        @if($lastVisiblePage < $pengajuans->lastPage() - 1)<span class="history-page-ellipsis" aria-hidden="true">…</span>@endif
                        <a class="history-page-link" href="{{ $pengajuans->url($pengajuans->lastPage()) }}" aria-label="Halaman {{ $pengajuans->lastPage() }}">{{ $pengajuans->lastPage() }}</a>
                    @endif

                    @if($pengajuans->hasMorePages())
                        <a class="history-page-link" href="{{ $pengajuans->nextPageUrl() }}" rel="next">Berikutnya</a>
                    @else
                        <span class="history-page-link is-disabled" aria-disabled="true">Berikutnya</span>
                    @endif
                </nav>
            @endif
        </div>
    </section>
@endsection
