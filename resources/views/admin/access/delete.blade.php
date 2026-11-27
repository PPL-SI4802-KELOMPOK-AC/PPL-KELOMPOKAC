@extends('layouts.admin')
@section('content')
<section class="admin-intro"><div><h1>Konfirmasi hapus profil</h1><p>Penghapusan hanya tersedia untuk profil yang belum pernah ditetapkan.</p></div></section>
@include('admin.access.messages')
<section class="panel access-review"><h2>{{ $profile->name }}</h2><p>{{ $usage }} petugas terkait.</p>
@if($usage || $profile->ever_assigned)<div class="form-alert" role="alert">Profil masih digunakan atau memiliki riwayat penetapan. Profil tidak dapat dihapus. Gunakan penonaktifan untuk mempertahankan riwayat.</div><div class="access-form-actions"><a class="button" href="{{ route('admin.access.show', $profile) }}">Kembali</a><a class="button button--primary" href="{{ route('admin.access.edit', $profile) }}">Atur penonaktifan</a></div>
@else<p>Profil dan konfigurasi izinnya akan dihapus. Akun, data operasional dan audit tetap dipertahankan.</p><form method="POST" action="{{ route('admin.access.destroy', $profile) }}" data-access-submit>@csrf @method('DELETE')<input type="hidden" name="expected_version" value="{{ $profile->version }}"><input type="hidden" name="expected_usage_count" value="{{ $usage }}"><label class="access-check"><input name="confirmed" type="checkbox" value="1" required><span>Saya menyetujui penghapusan profil ini.</span></label><div class="access-form-actions"><a class="button" href="{{ route('admin.access.show', $profile) }}">Batal</a><button class="button access-danger" type="submit">Hapus profil</button></div><p data-access-loading role="status"></p></form>@endif
</section>
@endsection
