@extends('layouts.admin')
@section('title', ($managedUser->exists ? 'Edit pengguna' : 'Tambah pengguna').' | Admin TerangDesa')
@section('content')
<section class="admin-intro">
    <div><a class="admin-back" href="{{ route('admin.users.index') }}">← Pengguna &amp; peran</a><h1>{{ $managedUser->exists ? 'Edit pengguna' : 'Tambah pengguna' }}</h1><p>{{ $managedUser->exists ? 'Perbarui identitas, peran, dan akses akun pengguna.' : 'Buat akun baru dan tentukan akses yang sesuai.' }}</p></div>
    <span class="admin-source">Administrasi akun</span>
</section>
@php($editingSelf = $managedUser->exists && $managedUser->id === auth()->id())
<div class="admin-account-grid">
<section class="panel admin-form-panel" aria-labelledby="user-form-title">
    <div class="admin-section-title"><h2 id="user-form-title">Informasi akun</h2><p>Isian dengan tanda <span aria-hidden="true">*</span> wajib dilengkapi.</p></div>
    <form action="{{ $managedUser->exists ? route('admin.users.update', $managedUser) : route('admin.users.store') }}" method="POST" class="admin-user-form" data-admin-user-form>
        @csrf
        @if($managedUser->exists)@method('PUT')@endif
        @if($errors->any())<div class="form-alert" role="alert">Perubahan belum disimpan. Periksa isian yang ditandai.</div>@endif
        <div class="form-pair">
            <div class="field"><label for="name">Nama lengkap <span aria-hidden="true">*</span></label><input id="name" name="name" value="{{ old('name', $managedUser->name) }}" placeholder="Nama lengkap pengguna" required maxlength="150" autocomplete="name" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>@error('name')<span class="field-error" id="name-error">{{ $message }}</span>@enderror</div>
            <div class="field"><label for="email">Email <span aria-hidden="true">*</span></label><input id="email" name="email" type="email" value="{{ old('email', $managedUser->email) }}" placeholder="nama@contoh.id" required maxlength="254" autocomplete="email" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>@error('email')<span class="field-error" id="email-error">{{ $message }}</span>@enderror</div>
        </div>
        @if($editingSelf)<p class="admin-self-note">Ini akun yang sedang Anda gunakan. Peran Admin dan akses Aktif harus dipertahankan.</p>@endif
        <div class="form-pair">
            <div class="field"><label for="user-role">Peran <span aria-hidden="true">*</span></label><select id="user-role" name="role" required @error('role') aria-invalid="true" aria-describedby="role-error" @enderror>@foreach($roles as $value => $label)<option value="{{ $value }}" @selected(old('role', $managedUser->role ?? 'masyarakat') === $value) @disabled($editingSelf && $value !== 'admin')>{{ $label }}</option>@endforeach</select>@error('role')<span class="field-error" id="role-error">{{ $message }}</span>@enderror</div>
            <div class="field"><label for="is_active">Akses akun <span aria-hidden="true">*</span></label><select id="is_active" name="is_active" required @error('is_active') aria-invalid="true" aria-describedby="is-active-error" @enderror><option value="1" @selected((string) old('is_active', $managedUser->exists ? (int) $managedUser->is_active : 1) === '1')>Aktif</option><option value="0" @selected((string) old('is_active', $managedUser->exists ? (int) $managedUser->is_active : 1) === '0') @disabled($editingSelf)>Nonaktif</option></select>@error('is_active')<span class="field-error" id="is-active-error">{{ $message }}</span>@enderror</div>
        </div>
        <div class="admin-password-heading"><h3>{{ $managedUser->exists ? 'Ubah kata sandi' : 'Kata sandi akun' }}</h3><p id="password-help">Minimal 8 dan maksimal 72 karakter.{{ $managedUser->exists ? ' Kosongkan kedua kolom untuk mempertahankan kata sandi saat ini.' : '' }}</p></div>
        <div class="form-pair">
            <div class="field"><label for="password">{{ $managedUser->exists ? 'Kata sandi baru' : 'Kata sandi' }}@unless($managedUser->exists) <span aria-hidden="true">*</span>@endunless</label><div class="admin-password-input"><input id="password" name="password" type="password" minlength="8" maxlength="72" autocomplete="new-password" @required(!$managedUser->exists) aria-describedby="password-help @error('password') password-error @enderror" @error('password') aria-invalid="true" @enderror><button type="button" data-password-toggle="password" aria-controls="password" aria-pressed="false" aria-label="Tampilkan kata sandi" hidden>Lihat</button></div>@error('password')<span class="field-error" id="password-error">{{ $message }}</span>@enderror</div>
            <div class="field"><label for="password_confirmation">Konfirmasi kata sandi @unless($managedUser->exists) <span aria-hidden="true">*</span>@endunless</label><div class="admin-password-input"><input id="password_confirmation" name="password_confirmation" type="password" minlength="8" maxlength="72" autocomplete="new-password" @required(!$managedUser->exists) aria-describedby="password-match"><button type="button" data-password-toggle="password_confirmation" aria-controls="password_confirmation" aria-pressed="false" aria-label="Tampilkan konfirmasi kata sandi" hidden>Lihat</button></div><span id="password-match" class="admin-password-match" aria-live="polite"></span></div>
        </div>
        <div class="admin-save-actions"><p data-admin-save-status role="status"></p><a class="button" href="{{ route('admin.users.index') }}">Batal</a><button class="button button--primary" type="submit" data-admin-save>{{ $managedUser->exists ? 'Simpan perubahan' : 'Tambah pengguna' }}</button></div>
    </form>
</section>
<aside class="admin-account-info" aria-label="Panduan akun">
    <section class="panel admin-info-panel"><span class="admin-guide-icon">@include('admin.partials.icon', ['name' => 'shield'])</span><h2>Akses sesuai peran</h2><p class="admin-guide-copy">Pilih peran sesuai tanggung jawab pengguna.</p>
        <dl class="admin-role-list admin-role-guide"><div data-role-guide="masyarakat"><dt>Masyarakat</dt><dd>Melihat peta, mengajukan PLTS, dan memantau riwayat pribadi.</dd></div><div data-role-guide="pemerintah"><dt>Pemerintah</dt><dd>Mengelola informasi wilayah dan melihat peta kebutuhan listrik.</dd></div><div data-role-guide="admin"><dt>Admin</dt><dd>Mengelola akun, menetapkan peran, dan mengatur akses pengguna.</dd></div></dl>
    </section>
    <section class="panel admin-info-panel"><h2>Status akses akun</h2><p class="admin-guide-copy"><strong>Aktif</strong> — pengguna dapat masuk sesuai perannya.</p><p class="admin-guide-copy"><strong>Nonaktif</strong> — pengguna tidak dapat masuk dan sesi yang terbuka berakhir pada permintaan berikutnya.</p></section>
</aside>
</div>
@endsection
