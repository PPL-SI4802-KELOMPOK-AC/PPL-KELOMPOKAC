@extends('layouts.admin')
@section('title', 'Log aktivitas | Admin TerangDesa')
@section('breadcrumb', 'Log aktivitas')
@section('content')
<section class="admin-intro"><div><h1>Log aktivitas</h1><p>Telusuri perubahan akun, pelaku perubahan, dan waktu pencatatannya.</p></div><span class="admin-source">Riwayat administrasi</span></section>
<div class="admin-log-metrics"><span><strong>{{ number_format($totalActivities, 0, ',', '.') }}</strong> aktivitas tercatat</span><span><strong>{{ number_format($todayCount, 0, ',', '.') }}</strong> aktivitas hari ini</span></div>
<section class="panel admin-overview-panel" aria-labelledby="activity-title">
    <div class="admin-section-title"><h2 id="activity-title">Riwayat perubahan akun</h2><p>Log dimulai sejak fitur ini diaktifkan. Nilai kata sandi tidak dicatat.</p></div>
    <form action="{{ route('admin.activities.index') }}" method="GET" class="admin-log-filters" role="search">
        <div class="field"><label for="q">Cari aktivitas</label><input id="q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" placeholder="Nama admin atau pengguna" maxlength="150"></div>
        <div class="field"><label for="action">Tindakan</label><select id="action" name="action"><option value="">Semua tindakan</option>@foreach($actions as $value => $label)<option value="{{ $value }}" @selected(($filters['action'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="field"><label for="from">Dari tanggal (WIB)</label><input id="from" name="from" type="date" value="{{ $filters['from'] ?? '' }}" @error('from') aria-invalid="true" @enderror></div>
        <div class="field"><label for="until">Sampai tanggal (WIB)</label><input id="until" name="until" type="date" value="{{ $filters['until'] ?? '' }}" @error('until') aria-invalid="true" @enderror></div>
        <div class="admin-filter-actions"><button class="button" type="submit">Terapkan filter</button>@if(!empty($filters['q']) || !empty($filters['action']) || !empty($filters['from']) || !empty($filters['until']))<a href="{{ route('admin.activities.index') }}">Reset filter</a>@endif</div>
    </form>
    @if($errors->any())<div class="form-alert" role="alert">{{ $errors->first() }}</div>@endif
    <div class="admin-activity-list">@forelse($activities as $activity)@include('admin.partials.activity-item', ['activity' => $activity, 'detailed' => true])@empty<div class="admin-log-empty"><span class="admin-empty-icon" aria-hidden="true">@include('admin.partials.icon', ['name' => 'history'])</span><strong>{{ $totalActivities ? 'Tidak ada aktivitas yang cocok.' : 'Belum ada aktivitas tercatat.' }}</strong><p>{{ $totalActivities ? 'Coba nama, tindakan, atau rentang tanggal lain.' : 'Perubahan akun berikutnya akan muncul di sini.' }}</p>@if(!empty($filters['q']) || !empty($filters['action']) || !empty($filters['from']) || !empty($filters['until']))<a class="button" href="{{ route('admin.activities.index') }}">Hapus semua filter</a>@endif</div>@endforelse</div>
    <div class="admin-pagination"><p>Menampilkan {{ $activities->firstItem() ?? 0 }}–{{ $activities->lastItem() ?? 0 }} dari {{ $activities->total() }} aktivitas</p>@if($activities->hasPages())<nav aria-label="Halaman log aktivitas">@if($activities->onFirstPage())<span aria-disabled="true">Sebelumnya</span>@else<a href="{{ $activities->previousPageUrl() }}" rel="prev">Sebelumnya</a>@endif<span>{{ $activities->currentPage() }} / {{ $activities->lastPage() }}</span>@if($activities->hasMorePages())<a href="{{ $activities->nextPageUrl() }}" rel="next">Berikutnya</a>@else<span aria-disabled="true">Berikutnya</span>@endif</nav>@endif</div>
</section>
@endsection
