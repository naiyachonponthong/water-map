@extends('layouts.public')
@section('title', 'ความสดของข้อมูล · '.$province->name_th)
@section('body-class', 'flood-home')
@push('head')<link rel="stylesheet" href="{{ asset('css/public-home.css') }}?v={{ filemtime(public_path('css/public-home.css')) }}"><link rel="stylesheet" href="{{ asset('css/insights.css') }}?v={{ filemtime(public_path('css/insights.css')) }}">@endpush
@section('content')
@include('partials.public-home-nav')
<main class="ia-shell ia-status-page">@include('partials.data-health')<nav class="ia-bottom-links"><a href="{{ route('public.insights', $province) }}">← พื้นที่ของฉันและกราฟ</a><a href="{{ route('public.province', $province) }}">หน้าหลักจังหวัด</a></nav></main>
@endsection
@push('scripts')<script src="{{ asset('js/data-health.js') }}?v={{ filemtime(public_path('js/data-health.js')) }}" defer></script>@endpush
