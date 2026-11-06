@extends('layouts.app')
@section('title', 'Pengajuan Terkirim | TerangDesa')
@section('body-class', 'admin-page location-page')
@section('breadcrumb', 'Pengajuan terkirim')
@section('content')
    <section class="page-intro"><div><h1>Pengajuan berhasil dikirim</h1><p>Pengajuan Anda sudah tercatat. Simpan nomor berikut sebagai referensi.</p></div></section>
    <section class="panel review-card submission-receipt" aria-label="Bukti pengiriman pengajuan">
        <span class="review-success-icon" aria-hidden="true">&#10003;</span>
        <h2>Nomor pengajuan</h2>
        <p class="submission-ticket">{{ $pengajuan->nomor_tiket }}</p>
        <p>Status pengajuan: <strong>{{ ucfirst($pengajuan->status_terkini) }}</strong></p>
        <p class="form-help">Data lokasi dan foto bukti tersimpan bersama pengajuan ini.</p>
        <a class="button" href="{{ route('wilayah.map') }}">Kembali ke peta wilayah</a>
    </section>
@endsection
