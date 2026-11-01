@extends('layouts.app')
@section('title', 'TerangDesa | Beranda')
@section('content')
    <section class="page-intro home-intro" aria-labelledby="home-title">
        <p class="eyebrow"><span aria-hidden="true"></span>TERANGDESA</p>
        <h1 id="home-title">Mengenal wilayah.<br>Mendukung kebutuhan energi desa.</h1>
        <p>Pendataan, pengajuan, dan monitoring kebutuhan PLTS untuk kelompok warga.</p>
        <a class="button" href="{{ route('wilayah.map') }}">Jelajahi Peta Wilayah <span aria-hidden="true">↗</span></a>
    </section>
@endsection
