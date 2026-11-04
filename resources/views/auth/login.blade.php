@extends('layouts.app')
@section('title', 'Masuk | TerangDesa')
@section('body-class', 'admin-page')
@section('breadcrumb', 'Masuk')
@section('content')
    <section class="login-panel panel">
        <p class="eyebrow">AKSES PENGELOLA</p>
        <h1>Masuk ke TerangDesa</h1>
        <p class="muted">Gunakan akun Pemerintah untuk mengelola data wilayah.</p>
        <form action="{{ route('login.store') }}" method="POST" class="region-form">
            @csrf
            <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username" maxlength="254" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>@error('email')<span class="field-error" id="email-error">{{ $message }}</span>@enderror</div>
            <div class="field"><label for="password">Kata sandi</label><input id="password" name="password" type="password" required autocomplete="current-password" maxlength="255" @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>@error('password')<span class="field-error" id="password-error">{{ $message }}</span>@enderror</div>
            <button class="button button--primary" type="submit">Masuk</button>
        </form>
    </section>
@endsection
