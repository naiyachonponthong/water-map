@extends('layouts.app')
@section('title', 'ความสดของข้อมูล')
@push('head')<link rel="stylesheet" href="{{ asset('css/insights.css') }}?v={{ filemtime(public_path('css/insights.css')) }}">@endpush
@section('content')
@include('partials.data-health')
<p class="ia-note">ปัญหาต้นทางจะแสดงที่หน้าศูนย์สั่งการ โดยไม่สร้างเหตุเตือนภัยน้ำท่วมอัตโนมัติ</p>
<a href="{{ route('public.insights', $province) }}">เปิดหน้ากราฟสำหรับประชาชน →</a>
@endsection
@push('scripts')<script src="{{ asset('js/data-health.js') }}?v={{ filemtime(public_path('js/data-health.js')) }}" defer></script>@endpush
