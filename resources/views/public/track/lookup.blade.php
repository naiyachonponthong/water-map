@extends('layouts.public')
@section('title', 'ติดตามคำขอความช่วยเหลือ')

@section('content')
    <header class="pub-head">
        <div class="inner d-flex align-items-center gap-2">
            <a href="{{ route('public.provinces') }}" class="text-white fs-4 me-1" aria-label="กลับ"><i class="bi bi-arrow-left"></i></a>
            <div class="fw-bold">ติดตามคำขอความช่วยเหลือ</div>
        </div>
    </header>
    <div class="pub-wrap">
        <form method="POST" action="{{ route('public.track.find') }}" class="card pub-float">
            @csrf
            <div class="card-body">
                <p class="text-muted small">กรอกเลขคำขอที่ได้รับหลังส่งฟอร์ม และ 4 ตัวท้ายของเบอร์ที่ใช้แจ้ง</p>
                <label class="form-label">เลขคำขอ</label>
                <input name="code" class="form-control form-control-lg mono text-uppercase mb-3 @error('code') is-invalid @enderror" value="{{ old('code') }}" placeholder="FL24-6910-0001" required>
                @error('code')<div class="invalid-feedback mt-n2 mb-3">{{ $message }}</div>@enderror
                <label class="form-label">4 ตัวท้ายเบอร์โทร</label>
                <input name="last4" class="form-control form-control-lg mono mb-4" inputmode="numeric" maxlength="4" value="{{ old('last4') }}" required>
                <button class="btn btn-primary btn-lg w-100" type="submit">ดูสถานะ</button>
            </div>
        </form>
    </div>
@endsection
