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
        <p>Status pengajuan: <strong dusk="status-terkini">{{ ucfirst($pengajuan->status_terkini) }}</strong></p>
        @if ($pengajuan->status_diperbarui_pada)
            @php($pembaruan = \Illuminate\Support\Carbon::parse($pengajuan->status_diperbarui_pada))
            <p>Pembaruan terakhir: <time datetime="{{ $pembaruan->toIso8601String() }}">{{ $pembaruan->format('d/m/Y H:i') }}</time></p>
        @endif
        <p class="form-help">Data lokasi dan foto bukti tersimpan bersama pengajuan ini.</p>
        <a class="button" href="{{ route('pengajuan.riwayat') }}">Kembali ke riwayat pengajuan</a>
        <a class="button" href="{{ route('wilayah.map') }}">Kembali ke peta wilayah</a>
    </section>
    <section class="panel review-card submission-receipt" aria-labelledby="riwayat-status-title">
        <h2 id="riwayat-status-title">Riwayat status pengajuan</h2>
        @if ($riwayatStatus->isEmpty())
            <p>Belum ada catatan perubahan status.</p>
        @else
            <ol data-status-history>
                @foreach ($riwayatStatus as $perubahan)
                    @php($waktu = \Illuminate\Support\Carbon::parse($perubahan->created_at))
                    <li>
                        <strong>{{ ucfirst($perubahan->status_baru) }}</strong>
                        <time datetime="{{ $waktu->toIso8601String() }}">{{ $waktu->format('d/m/Y H:i') }}</time>
                        @if (filled($perubahan->catatan))
                            <p>{{ $perubahan->catatan }}</p>
                        @endif
                    </li>
                @endforeach
            </ol>
        @endif
    </section>
@endsection
