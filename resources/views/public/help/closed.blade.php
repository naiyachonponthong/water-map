@extends('layouts.public')
@section('title', 'ขอความช่วยเหลือ '.$province->fullName())

@section('content')
    <header class="pub-head">
        <div class="inner d-flex align-items-center gap-2">
            <a href="{{ route('public.province', $province) }}" class="text-white fs-4 me-1" aria-label="กลับ"><i class="bi bi-arrow-left"></i></a>
            <div>
                <div class="fw-bold lh-sm">ขอความช่วยเหลือ</div>
                <div class="small opacity-75">{{ $province->fullName() }}</div>
            </div>
        </div>
    </header>

    <div class="pub-wrap">
        <div class="pub-float">
            <div class="card mb-3">
                <div class="card-body track-status">
                    <div class="ts-ico tint-danger"><i class="bi bi-telephone-fill"></i></div>
                    <h1 class="h5 fw-bold">ขณะนี้ยังไม่รับแจ้งทางเว็บ</h1>
                    <p class="text-muted small mb-0">หากต้องการความช่วยเหลือ โทรเบอร์ฉุกเฉินด้านล่างได้ทันที</p>
                </div>
            </div>
            <div class="card">
                @foreach($contacts as $c)
                    <a href="tel:{{ preg_replace('/\D+/', '', $c['phone']) }}" class="contact-row">
                        <span class="st-icon tint-danger d-grid" style="width:38px;height:38px;border-radius:12px;place-items:center"><i class="bi bi-telephone"></i></span>
                        <span class="small fw-600">{{ $c['label'] }}</span>
                        <span class="num mono">{{ phone_format($c['phone']) }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
    <a href="tel:{{ preg_replace('/\D+/', '', $hotline) }}" class="btn btn-danger call-fab"><i class="bi bi-telephone-fill me-1"></i>โทรกู้ภัย {{ $hotline }}</a>
@endsection
