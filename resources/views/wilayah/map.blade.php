@extends('layouts.app')
@section('title', 'Peta Wilayah | TerangDesa')
@section('body-class', 'admin-page map-page')
@section('breadcrumb', 'Peta Wilayah')
@section('content')
    @include('wilayah.partials.map-content')
@endsection
