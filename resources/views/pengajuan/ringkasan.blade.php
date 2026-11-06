@extends('layouts.app')
@section('title', 'Ringkasan Pengajuan | TerangDesa')
@section('body-class', 'admin-page location-page review-page')
@section('breadcrumb', 'Ringkasan pengajuan')
@section('content')
    <section class="page-intro" aria-labelledby="review-title">
        <div>
            <h1 id="review-title">Periksa sebelum mengirim</h1>
            <p>Ringkasan pengajuan baru &middot; Desa {{ $village['name'] }}, {{ $district }}</p>
        </div>
        <span class="draft-badge">Draft tersimpan</span>
    </section>
    @include('pengajuan.partials.steps', ['step' => 'ringkasan'])
    @include('pengajuan.partials.validation-summary')
    <div class="review-grid">
        <div class="review-column">
            <section class="panel review-card" aria-labelledby="review-location-title">
                <h2 id="review-location-title">Data lokasi</h2>
                <dl class="review-details">
                    <div><dt>Desa / kelurahan</dt><dd>{{ $village['name'] }}</dd></div>
                    <div><dt>Kecamatan / kabupaten</dt><dd>{{ $location['kecamatan'] }} / {{ $district }}</dd></div>
                    <div><dt>Alamat lengkap</dt><dd>{{ $location['alamat'] }}</dd></div>
                    <div><dt>Koordinat</dt><dd>@if($draft->lokasi->latitude !== null && $draft->lokasi->longitude !== null){{ number_format($draft->lokasi->latitude, 7, ',', '') }} &bull; {{ number_format($draft->lokasi->longitude, 7, ',', '') }}@else Tidak diisi (opsional) @endif</dd></div>
                    <div class="review-detail-wide"><dt>Ringkasan pengajuan</dt><dd>{{ $draft->ringkasan_pengajuan }}</dd></div>
                </dl>
                <a class="review-edit" href="{{ route('pengajuan.lokasi') }}">Ubah data lokasi</a>
            </section>
            <section class="panel review-card" aria-labelledby="review-evidence-title">
                <h2 id="review-evidence-title">Bukti lokasi</h2>
                <div class="review-evidence">
                    @foreach($draft->bukti as $bukti)
                        <figure>
                            <img src="{{ route('pengajuan.bukti.show', $bukti->id) }}" alt="Bukti kondisi wilayah: {{ $bukti->nama_file_asli }}" loading="lazy">
                            <figcaption>{{ $bukti->nama_file_asli }} &bull; {{ number_format($bukti->ukuran_byte / 1048576, 1, ',', '.') }} MB</figcaption>
                        </figure>
                    @endforeach
                </div>
            </section>
        </div>
        <div class="review-column">
            <section class="panel review-card" aria-labelledby="review-owner-title">
                <h2 id="review-owner-title">Identitas pengusul</h2>
                <dl class="review-details review-details--single">
                    <div><dt>Nama</dt><dd>{{ auth()->user()->name }}</dd></div>
                    <div><dt>Peran</dt><dd>{{ ucfirst(auth()->user()->role) }}</dd></div>
                    <div><dt>Surel</dt><dd>{{ auth()->user()->email }}</dd></div>
                </dl>
            </section>
            <p class="review-ready" role="status"><span aria-hidden="true">&#10003;</span> Data lokasi dan {{ $draft->bukti->count() }} foto bukti sudah tersimpan.</p>
            <section class="panel review-card">
                <form class="review-submit" action="{{ route('pengajuan.kirim') }}" method="POST" data-submission-form>
                    @csrf
                    <input type="hidden" name="draft_id" value="{{ $draft->id }}">
                    <label class="review-declaration" for="pernyataan">
                        <input id="pernyataan" type="checkbox" name="pernyataan" value="1" required @checked(old('pernyataan') === '1') @error('pernyataan') aria-invalid="true" @enderror>
                        <span>Saya menyatakan data yang disampaikan sesuai kondisi wilayah dan bersedia dihubungi untuk verifikasi.</span>
                    </label>
                    <button class="button button--primary" type="submit" data-submission-button><span aria-hidden="true">&#10148;</span> Kirim pengajuan</button>
                    <a class="button" href="{{ route('pengajuan.bukti') }}">Kembali ke bukti foto</a>
                    <p class="form-help" data-submission-status role="status">Nomor pengajuan diterbitkan setelah dikirim.</p>
                </form>
            </section>
        </div>
    </div>
@endsection
