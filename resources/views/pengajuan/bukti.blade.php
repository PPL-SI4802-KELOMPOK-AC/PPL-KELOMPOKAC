@extends('layouts.app')
@section('title', 'Bukti Foto Pengajuan | TerangDesa')
@section('body-class', 'admin-page location-page evidence-page')
@section('breadcrumb', 'Bukti foto pengajuan')
@section('content')
    <section class="page-intro" aria-labelledby="evidence-title">
        <div>
            <h1 id="evidence-title">Tunjukkan kondisi di lapangan</h1>
            <p>Dokumentasikan kondisi rumah dan akses lokasi yang diajukan.</p>
        </div>
        <span class="draft-badge">Draft tersimpan</span>
    </section>
    @if(session('location_saved'))<div class="notice" role="status">{{ session('location_saved') }}</div>@endif
    @if(session('evidence_saved'))<div class="notice" role="status">{{ session('evidence_saved') }}</div>@endif
    @if($errors->any())
        <div class="notice is-error" role="alert"><strong>Periksa foto berikut:</strong><ul>
            @foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach
        </ul></div>
    @endif
    @include('pengajuan.partials.steps', ['step' => 'bukti'])
    @include('pengajuan.partials.bukti')
@endsection
