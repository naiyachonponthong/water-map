@extends('layouts.app')
@section('title', 'บันทึกคำร้องเยียวยา')

@push('head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
@endpush

@section('content')
    <x-page-head title="บันทึกคำร้องเยียวยา" sub="รับคำร้องทางโทรศัพท์หรือที่ศูนย์ แทนผู้ประสบภัย">
        <a href="{{ route('recovery.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>กลับ</a>
    </x-page-head>
    @if($errors->any())<div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i><div>{{ $errors->first() }}</div></div>@endif
    <form method="POST" action="{{ route('recovery.store') }}" enctype="multipart/form-data" style="max-width:860px" novalidate>
        @csrf
        @include('partials.recovery-fields')
        <button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-check2 me-1"></i>บันทึก</button>
    </form>
@endsection
