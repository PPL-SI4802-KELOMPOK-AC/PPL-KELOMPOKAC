@extends('layouts.admin')
@section('title', 'Ringkasan operasional | Admin TerangDesa')
@section('breadcrumb', 'Ringkasan operasional')
@section('content')
<section class="admin-intro">
    <div><h1>Ringkasan operasional</h1><p>Pantau pengguna, data wilayah, dan pengajuan PLTS dalam satu tempat.</p></div>
    <div class="admin-intro-actions"><a class="button" href="{{ route('admin.dashboard') }}">Muat ulang</a><a class="button button--primary" href="{{ route('admin.users.create') }}">@include('admin.partials.icon', ['name' => 'add'])Tambah pengguna</a></div>
</section>
<section class="admin-stats admin-overview-stats" aria-label="Ringkasan data aplikasi">
    <article class="panel admin-stat"><span class="admin-stat-icon">@include('admin.partials.icon', ['name' => 'users'])</span><h2>Pengguna terdaftar</h2><strong>{{ number_format($roles->sum(), 0, ',', '.') }}</strong><p>{{ $activeUsers }} aktif · {{ $roles->sum() - $activeUsers }} nonaktif</p></article>
    <article class="panel admin-stat"><span class="admin-stat-icon">@include('admin.partials.icon', ['name' => 'database'])</span><h2>Wilayah referensi</h2><strong>{{ number_format($regionCount, 0, ',', '.') }}</strong><p>Wilayah tersimpan di aplikasi</p></article>
    <article class="panel admin-stat"><span class="admin-stat-icon">@include('admin.partials.icon', ['name' => 'grid'])</span><h2>Pengajuan terkirim</h2><strong>{{ number_format($submitted, 0, ',', '.') }}</strong><p>Seluruh pengajuan selain draft</p></article>
    <article class="panel admin-stat"><span class="admin-stat-icon">@include('admin.partials.icon', ['name' => 'history'])</span><h2>Dalam penanganan</h2><strong>{{ number_format($inProgress, 0, ',', '.') }}</strong><p>Diajukan hingga diproses</p></article>
</section>
<div class="admin-overview-grid">
    <section class="panel admin-overview-panel" aria-labelledby="submission-title">
        <div class="admin-section-title"><h2 id="submission-title">Status pengajuan</h2><p>Jumlah pengajuan berdasarkan status penanganan terkini.</p></div>
        <div class="admin-status-chart">
            @foreach($statusLabels as $value => $label)
            <div class="admin-status-row"><div><span>{{ $label }}</span><strong>{{ number_format($statuses[$value] ?? 0, 0, ',', '.') }}</strong></div><div class="admin-status-track" aria-hidden="true"><span data-status="{{ $value }}" style="width: {{ $submitted ? round(($statuses[$value] ?? 0) / $submitted * 100, 2) : 0 }}%"></span></div></div>
            @endforeach
        </div>
        <p class="admin-overview-note">{{ number_format($statuses['draft'] ?? 0, 0, ',', '.') }} draft belum dikirim dan belum termasuk pengajuan terkirim.</p>
    </section>
    <section class="panel admin-overview-panel" aria-labelledby="accounts-title">
        <div class="admin-section-title"><h2 id="accounts-title">Komposisi pengguna</h2><p>Akun terdaftar menurut peran layanan.</p></div>
        <dl class="admin-role-counts">@foreach($roleLabels as $value => $label)<div><dt><span class="admin-role-badge" data-role="{{ $value }}">{{ $label }}</span></dt><dd>{{ number_format($roles[$value] ?? 0, 0, ',', '.') }} <span>akun</span></dd></div>@endforeach</dl>
        <div class="admin-overview-links"><a href="{{ route('admin.users.index') }}">Kelola pengguna <span aria-hidden="true">→</span></a><a href="{{ route('admin.users.index', ['access' => 'inactive']) }}">Lihat akun nonaktif <span aria-hidden="true">→</span></a></div>
    </section>
    <section class="panel admin-overview-panel admin-overview-activity" aria-labelledby="recent-title">
        <div class="admin-section-heading"><div class="admin-section-title"><h2 id="recent-title">Aktivitas admin terbaru</h2><p>Perubahan akun yang terakhir dicatat oleh sistem.</p></div><a class="button" href="{{ route('admin.activities.index') }}">Lihat semua aktivitas</a></div>
        <div class="admin-activity-list">@forelse($recentActivities as $activity)@include('admin.partials.activity-item', ['activity' => $activity, 'detailed' => false])@empty<div class="admin-log-empty"><span class="admin-empty-icon" aria-hidden="true">@include('admin.partials.icon', ['name' => 'history'])</span><strong>Belum ada aktivitas tercatat.</strong><p>Aktivitas muncul setelah admin menambah atau mengubah akun.</p></div>@endforelse</div>
    </section>
</div>
<p class="admin-data-note">Sumber: database aplikasi. Data diperbarui setiap halaman dibuka · Waktu dalam WIB.</p>
@endsection
