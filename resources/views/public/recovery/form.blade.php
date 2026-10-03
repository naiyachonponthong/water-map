@extends('layouts.public')
@section('title', 'ขอรับความช่วยเหลือหลังน้ำลด '.$province->fullName())

@push('head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
@endpush

@section('content')
    <header class="pub-head">
        <div class="inner d-flex align-items-center gap-2">
            <a href="{{ route('public.province', $province) }}" class="text-white fs-4 me-1" aria-label="กลับ"><i class="bi bi-arrow-left"></i></a>
            <div><div class="fw-bold lh-sm">ขอรับความช่วยเหลือหลังน้ำลด</div><div class="small opacity-75">{{ $province->fullName() }}</div></div>
        </div>
    </header>

    <div class="pub-wrap">
        <div class="pub-float">
            @if(! $open)
                <div class="card mb-3"><div class="card-body track-status">
                    <div class="ts-ico tint-primary"><i class="bi bi-hourglass-split"></i></div>
                    <h1 class="h5 fw-bold">ยังไม่เปิดรับคำร้องเยียวยา</h1>
                    <p class="text-muted small mb-0">ศูนย์จะเปิดรับเมื่อระดับน้ำลดและเริ่มสำรวจความเสียหาย ติดตามประกาศจากศูนย์ หรือโทร {{ $hotline }}</p>
                </div></div>
            @else
                @if($errors->any())<div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i><div>{{ $errors->first() }}</div></div>@endif
                <form method="POST" action="{{ route('public.recovery.store', $province) }}" enctype="multipart/form-data" novalidate>
                    @csrf
                    <input type="text" name="website" class="d-none" tabindex="-1" autocomplete="off" aria-hidden="true">
                    @include('partials.recovery-fields')
                    @include('partials.privacy-note', ['text' => 'ข้อมูลใช้เพื่อสำรวจความเสียหายและจ่ายความช่วยเหลือเท่านั้น เจ้าหน้าที่จะติดต่อนัดสำรวจตามเบอร์ที่ให้ไว้'])
                    <button class="btn btn-primary btn-lg w-100" type="submit"><i class="bi bi-send me-1"></i>ยื่นคำร้อง</button>
                </form>
            @endif

            <form method="POST" action="{{ route('public.recovery.lookup', $province) }}" class="card mt-3">
                @csrf
                <div class="card-body">
                    <div class="fw-600 mb-2">ติดตามคำร้องที่ยื่นแล้ว</div>
                    <div class="row g-2">
                        <div class="col-6"><input name="code" class="form-control mono" placeholder="เลขคำร้อง RC..." value="{{ old('code') }}" required></div>
                        <div class="col-6"><input name="phone" type="tel" class="form-control mono" placeholder="เบอร์โทร" required></div>
                    </div>
                    <button class="btn btn-light w-100 mt-2" type="submit">ดูสถานะ</button>
                </div>
            </form>
        </div>
    </div>
@endsection
