@extends('layouts.admin')
@section('content')
<section class="admin-intro">
    <div><h1>Kelola data operasional</h1><p>Atur akses pengguna, peran layanan, dan data master TerangDesa.</p></div>
    <div class="admin-intro-actions"><span class="admin-source">Data aplikasi</span><a class="button button--primary" href="{{ route('admin.users.create') }}">@include('admin.partials.icon', ['name' => 'add'])Tambah pengguna</a></div>
</section>
@if(session('notice'))<div class="notice" role="status">{{ session('notice') }}</div>@endif
<section class="admin-stats" aria-label="Ringkasan administrasi">
    <article class="panel admin-stat"><span class="admin-stat-icon">@include('admin.partials.icon', ['name' => 'users'])</span><h2>Pengguna terdaftar</h2><strong>{{ number_format($counts->sum(), 0, ',', '.') }}</strong><p>{{ $counts['masyarakat'] ?? 0 }} masyarakat · {{ $counts['pemerintah'] ?? 0 }} pemerintah · {{ $counts['admin'] ?? 0 }} admin</p></article>
    <article class="panel admin-stat"><span class="admin-stat-icon">@include('admin.partials.icon', ['name' => 'shield'])</span><h2>Peran layanan</h2><strong>{{ count($roles) }}</strong><p>Masyarakat, Pemerintah, Admin</p></article>
    <article class="panel admin-stat"><span class="admin-stat-icon">@include('admin.partials.icon', ['name' => 'database'])</span><h2>Data master</h2><strong>{{ number_format($regionCount, 0, ',', '.') }} <span>wilayah</span></strong><p>Wilayah referensi · 6 status penanganan</p></article>
</section>
<div class="admin-content-grid">
    <section class="panel admin-users-panel" aria-labelledby="users-title">
        <div class="admin-section-title"><h2 id="users-title">Pengguna &amp; akses <span class="admin-count">{{ $users->total() }}</span></h2><p>Kelola akun dan tentukan akses layanan.</p></div>
        <form class="admin-filters" method="GET" action="{{ route('admin.users.index') }}" role="search">
            <div class="field"><label for="q">Cari pengguna</label><input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nama atau email" maxlength="150"></div>
            <div class="field"><label for="role">Peran</label><select id="role" name="role"><option value="">Semua peran</option>@foreach($roles as $value => $label)<option value="{{ $value }}" @selected(($filters['role'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="field"><label for="access">Akses akun</label><select id="access" name="access"><option value="">Semua akses</option><option value="active" @selected(($filters['access'] ?? '') === 'active')>Aktif</option><option value="inactive" @selected(($filters['access'] ?? '') === 'inactive')>Nonaktif</option></select></div>
            <div class="admin-filter-actions"><button class="button" type="submit">Terapkan filter</button>@if(!empty($filters['q']) || !empty($filters['role']) || !empty($filters['access']))<a href="{{ route('admin.users.index') }}">Reset filter</a>@endif</div>
        </form>
        @if($errors->any())<div class="form-alert" role="alert">{{ $errors->first() }}</div>@endif
        <div class="admin-table-wrap" tabindex="0" role="region" aria-label="Daftar pengguna">
            <table class="admin-user-table" role="table"><thead role="rowgroup"><tr><th scope="col">Pengguna</th><th scope="col">Email</th><th scope="col">Peran</th><th scope="col">Akses</th><th scope="col">Aksi</th></tr></thead><tbody role="rowgroup">
            @forelse($users as $user)
                <tr role="row">
                    <th scope="row" role="rowheader"><div class="admin-person"><span class="admin-person-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span><span>{{ $user->name }}@if($user->id === auth()->id())<small>Anda</small>@endif</span></div></th>
                    <td role="cell" class="admin-email">{{ $user->email }}</td>
                    <td role="cell" data-label="Peran"><span class="admin-role-badge" data-role="{{ $user->role }}">{{ $roles[$user->role] ?? $user->role }}</span></td>
                    <td role="cell" data-label="Akses"><span class="admin-access {{ $user->is_active ? '' : 'is-inactive' }}"><span aria-hidden="true"></span>{{ $user->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                    <td role="cell" class="admin-action-cell"><a class="admin-edit-link" href="{{ route('admin.users.edit', $user) }}" aria-label="Edit {{ $user->name }}">Edit<span class="admin-edit-arrow" aria-hidden="true"> ↗</span></a></td>
                </tr>
            @empty
                <tr role="row" class="admin-empty-row"><td role="cell" colspan="5" class="admin-empty"><span class="admin-empty-icon" aria-hidden="true">@include('admin.partials.icon', ['name' => 'users'])</span><strong>{{ $counts->sum() ? 'Tidak ada pengguna yang cocok.' : 'Belum ada pengguna terdaftar.' }}</strong><span>{{ $counts->sum() ? 'Coba kata pencarian, peran, atau akses lain.' : 'Tambahkan pengguna pertama untuk memulai.' }}</span>@if(!empty($filters['q']) || !empty($filters['role']) || !empty($filters['access']))<a class="button" href="{{ route('admin.users.index') }}">Hapus semua filter</a>@endif</td></tr>
            @endforelse
            </tbody></table>
        </div>
        <div class="admin-pagination"><p>Menampilkan {{ $users->firstItem() ?? 0 }}–{{ $users->lastItem() ?? 0 }} dari {{ $users->total() }} pengguna</p>@if($users->hasPages())<nav aria-label="Halaman daftar pengguna">@if($users->onFirstPage())<span aria-disabled="true">Sebelumnya</span>@else<a href="{{ $users->previousPageUrl() }}" rel="prev">Sebelumnya</a>@endif<span>{{ $users->currentPage() }} / {{ $users->lastPage() }}</span>@if($users->hasMorePages())<a href="{{ $users->nextPageUrl() }}" rel="next">Berikutnya</a>@else<span aria-disabled="true">Berikutnya</span>@endif</nav>@endif</div>
        @if($latestUser)<p class="admin-last-update">Akun terakhir diperbarui: {{ $latestUser->name }} · {{ $latestUser->updated_at->timezone('Asia/Jakarta')->format('d M Y, H.i') }} WIB.</p>@endif
    </section>
    <aside class="admin-info" aria-label="Informasi peran dan data master">
        <section class="panel admin-info-panel" id="hak-akses" aria-labelledby="roles-title">
            <h2 id="roles-title">Pengaturan peran</h2>
            <dl class="admin-role-list"><div><dt>Masyarakat</dt><dd>Peta, pengajuan, riwayat pribadi</dd></div><div><dt>Pemerintah</dt><dd>Kelola data wilayah dan akses peta</dd></div><div><dt>Admin</dt><dd>Kelola akun, peran, dan akses pengguna</dd></div></dl>
            <details class="admin-details"><summary>@include('admin.partials.icon', ['name' => 'shield'])Lihat hak akses</summary><p>Peran menentukan halaman yang dapat diakses. Admin dapat mengganti peran melalui tombol Edit pada daftar pengguna. Peran admin yang sedang digunakan tidak dapat diturunkan atau dinonaktifkan.</p></details>
        </section>
        <section class="panel admin-info-panel" id="data-master" tabindex="-1" aria-labelledby="master-title">
            <h2 id="master-title">Data master</h2>
            <dl class="admin-master-list"><div><dt>Wilayah referensi</dt><dd>{{ number_format($regionCount, 0, ',', '.') }} wilayah</dd></div><div><dt>Peran pengguna</dt><dd>{{ count($roles) }} peran layanan</dd></div><div class="admin-master-full"><dt>Status penanganan</dt><dd>6 status layanan</dd></div></dl>
            <p class="admin-status-list">Diajukan · Diperiksa · Diverifikasi · Diproses · Selesai · Ditolak</p>
            <details class="admin-details"><summary>@include('admin.partials.icon', ['name' => 'database'])Lihat data master</summary><p>Jumlah wilayah dihitung dari database aplikasi. Status di atas merupakan tahapan penanganan; draft belum termasuk pengajuan yang dikirim. Pengelolaan data master tersedia pada tahap berikutnya.</p></details>
        </section>
    </aside>
</div>
@endsection
