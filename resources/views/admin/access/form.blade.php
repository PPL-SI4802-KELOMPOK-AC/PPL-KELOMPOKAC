@extends('layouts.admin')
@section('title', 'Formulir profil | Admin TerangDesa')
@section('content')
<section class="admin-intro"><div><h1>{{ $profile->exists ? 'Edit profil hak akses' : 'Tambah profil hak akses' }}</h1><p>Pilih izin layanan, lalu tinjau dampaknya sebelum menyimpan.</p></div><a class="button" href="{{ route('admin.access.index') }}">Kembali ke profil</a></section>
@include('admin.access.messages')
<div class="access-form-grid"><section class="panel"><form class="access-form" method="POST" action="{{ route('admin.access.preview') }}" data-access-submit>
@csrf
@if($profile->exists)<input type="hidden" name="profile_id" value="{{ $profile->id }}"><input type="hidden" name="expected_version" value="{{ $profile->version }}"><input type="hidden" name="expected_usage_count" value="{{ $usage }}">@endif
<input type="hidden" name="permissions_present" value="1">
<div class="field"><label for="profile-name">Nama profil *</label><input id="profile-name" name="name" value="{{ old('name', $profile->name) }}" required maxlength="150" placeholder="Contoh: Petugas Verifikasi" @error('name') aria-invalid="true" @enderror></div>
<div class="field"><label for="profile-description">Deskripsi tugas</label><textarea id="profile-description" name="description" maxlength="2000" rows="3">{{ old('description', $profile->description) }}</textarea></div>
<div class="field"><label for="profile-active">Status profil *</label><select name="is_active" id="profile-active"><option value="1" @selected((string) old('is_active', $profile->exists ? (int) $profile->is_active : 1) === '1')>Aktif</option><option value="0" @selected((string) old('is_active', $profile->exists ? (int) $profile->is_active : 1) === '0')>Nonaktif</option></select></div>
<fieldset class="access-permissions"><legend>Izin modul dan tindakan</legend><p>Izin yang tidak dipilih akan ditolak. Tindakan pengelolaan wilayah memerlukan izin melihat wilayah.</p>
@foreach(collect($catalog)->groupBy('module', preserveKeys: true) as $module => $items)<div class="access-permission-module"><h3>{{ $module }}</h3>@foreach($items as $code => $permission)<label class="access-check"><input type="checkbox" name="permissions[]" value="{{ $code }}" @checked(in_array($code, old('permissions', $permissions), true))><span>{{ $permission['label'] }}@if($permission['requires'])<small>Memerlukan izin melihat Data wilayah</small>@endif</span></label>@endforeach</div>@endforeach
</fieldset><div class="access-form-actions"><a class="button" href="{{ route('admin.access.index') }}">Batal</a><button class="button button--primary" type="submit">Tinjau dampak</button></div><p data-access-loading role="status"></p>
</form></section><aside class="panel access-aside"><h2>Pengaruh profil</h2><strong>{{ $usage }} petugas terkait</strong><p>Penambahan dan pencabutan izin berlaku pada permintaan berikutnya, termasuk sesi yang sudah terbuka.</p><p>Profil nonaktif tidak memberikan izin. Akun dan riwayat petugas tetap disimpan.</p><p>Daftar izin mengikuti modul yang sudah tersedia. Profil ini tidak memberikan akses pengelolaan akun Admin.</p></aside></div>
@endsection
