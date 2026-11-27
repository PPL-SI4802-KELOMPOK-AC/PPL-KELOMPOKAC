@extends('layouts.admin')
@section('content')
<section class="admin-intro"><div><h1>Tetapkan profil petugas</h1><p>{{ $staff->name }} · {{ $staff->email }}</p></div><a class="button" href="{{ route('admin.users.index', ['role' => 'pemerintah']) }}">Kembali ke pengguna</a></section>
@include('admin.access.messages')
<section class="panel access-review"><h2>Profil saat ini</h2><p>{{ $assignment?->profile?->name ?? 'Belum memiliki profil' }}</p>
@if($staff->role !== 'pemerintah' || !$staff->is_active)<div class="form-alert" role="alert">Profil hanya dapat ditetapkan kepada akun Pemerintah yang aktif. Penetapan lama tetap dipertahankan untuk riwayat, tetapi tidak memberikan akses pada akun yang tidak memenuhi syarat.</div>
@else<form method="POST" action="{{ route('admin.access.assignment.preview') }}" data-access-submit>@csrf<input type="hidden" name="user_id" value="{{ $staff->id }}"><div class="field"><label for="assign-profile">Profil aktif *</label><select name="profile_id" id="assign-profile" required><option value="">Pilih profil</option>@foreach($profiles as $profile)<option value="{{ $profile->id }}" @selected((string) old('profile_id', $assignment?->profile_id) === (string) $profile->id)>{{ $profile->name }}</option>@endforeach</select></div><p>Satu petugas memiliki satu profil. Penetapan baru menggantikan profil sebelumnya.</p><div class="access-form-actions"><a class="button" href="{{ route('admin.access.index') }}">Batal</a><button class="button button--primary" type="submit">Tinjau penetapan</button></div><p data-access-loading role="status"></p></form>@endif
</section>
@endsection
