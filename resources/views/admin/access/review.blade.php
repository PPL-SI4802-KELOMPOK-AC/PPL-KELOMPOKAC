@extends('layouts.admin')
@section('content')
<section class="admin-intro"><div><h1>Tinjau perubahan profil</h1><p>Pastikan izin dan dampak pada petugas sudah sesuai.</p></div></section>
<section class="panel access-review"><h2>{{ $input['name'] }}</h2><p>{{ $input['description'] ?? 'Tanpa deskripsi.' }}</p><p>Status: <strong>{{ $input['is_active'] ? 'Aktif' : 'Nonaktif' }}</strong></p><p data-access-impact><strong>{{ $usage }} petugas terkait</strong>. {{ $input['is_active'] ? 'Petugas akan memakai izin berikut pada permintaan selanjutnya.' : 'Profil berhenti memberikan seluruh izinnya pada permintaan berikutnya. Akun dan riwayat tetap disimpan.' }}</p>
<div class="access-review-columns"><div><h3>Izin sebelumnya</h3><ul>@forelse($before['permission_codes'] ?? [] as $code)<li>{{ $catalog[$code]['label'] ?? $code }}</li>@empty<li>Belum ada izin.</li>@endforelse</ul></div><div><h3>Izin setelah disimpan</h3><ul>@forelse($input['permissions'] as $code)<li>{{ $catalog[$code]['label'] }}</li>@empty<li>Tidak ada izin. Akses operasional akan ditolak.</li>@endforelse</ul></div></div>
<form action="{{ $profile ? route('admin.access.update', $profile) : route('admin.access.store') }}" method="POST" data-access-submit>@csrf @if($profile)@method('PUT')@endif
@foreach(['name', 'description', 'is_active', 'expected_version', 'expected_usage_count'] as $key)@if(isset($input[$key]))<input type="hidden" name="{{ $key }}" value="{{ $input[$key] }}">@endif @endforeach
<input type="hidden" name="permissions_present" value="1">@foreach($input['permissions'] as $code)<input type="hidden" name="permissions[]" value="{{ $code }}">@endforeach
<label class="access-check"><input type="checkbox" name="confirmed" value="1" required><span>Saya menyetujui perubahan izin dan dampaknya.</span></label><div class="access-form-actions"><a class="button" href="{{ $profile ? route('admin.access.show', $profile) : route('admin.access.index') }}">Batalkan perubahan</a><button type="submit" class="button button--primary">Konfirmasi dan simpan</button></div><p data-access-loading role="status"></p></form>
</section>
@endsection
