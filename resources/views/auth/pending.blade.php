@extends('layouts.guest')
@section('title', 'รออนุมัติ')

@section('content')
    <div class="text-center">
        <div class="em-ico mx-auto mb-3" style="width:76px;height:76px;border-radius:24px;background:#fff7e6;color:#d97706;display:grid;place-items:center;font-size:2rem">
            <i class="bi bi-hourglass-split"></i>
        </div>
        <h2 class="fw-bold mb-2">ส่งคำขอแล้ว รออนุมัติ</h2>
        <p class="text-muted">
            คุณ{{ $user->name }} ขอใช้งานในบทบาท <b>{{ config('floodthai.roles.'.$user->requested_role, 'เจ้าหน้าที่') }}</b>
            ของ{{ $user->province?->fullName() }}<br>
            เมื่อผู้อำนวยการศูนย์อนุมัติแล้ว กลับมาเข้าสู่ระบบได้ทันที
        </p>
    </div>

    <div class="card my-4">
        <div class="card-body small">
            <div class="d-flex justify-content-between py-1"><span class="text-muted">เบอร์โทร</span><span>{{ phone_format($user->phone) }}</span></div>
            <div class="d-flex justify-content-between py-1"><span class="text-muted">หน่วยงาน</span><span>{{ $user->organization }}</span></div>
            <div class="d-flex justify-content-between py-1"><span class="text-muted">ส่งคำขอเมื่อ</span><span>{{ thai_date($user->created_at, 'datetime') }}</span></div>
        </div>
    </div>

    <div class="d-grid gap-2">
        <a href="{{ route('pending') }}" class="btn btn-soft"><i class="bi bi-arrow-clockwise me-1"></i>ตรวจสถานะอีกครั้ง</a>
        <form method="POST" action="{{ route('logout') }}">@csrf
            <button class="btn btn-light w-100" type="submit">ออกจากระบบ</button>
        </form>
    </div>
@endsection
