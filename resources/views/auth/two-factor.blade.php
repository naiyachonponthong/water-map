@extends('layouts.guest')
@section('title', 'ยืนยันตัวตน 2 ชั้น')

@section('content')
    <h2 class="fw-bold mb-1" style="letter-spacing:-.02em">ยืนยันตัวตน 2 ชั้น</h2>
    <p class="text-muted mb-4">เปิดแอป Authenticator บนมือถือ แล้วกรอกรหัส 6 หลัก</p>

    <form method="POST" action="{{ route('two-factor.verify') }}" novalidate>
        @csrf
        <div class="mb-3">
            <input name="code" class="form-control form-control-lg mono text-center @error('code') is-invalid @enderror" inputmode="numeric" autocomplete="one-time-code" maxlength="20" placeholder="123456" autofocus required style="letter-spacing:.3em">
            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <button class="btn btn-primary btn-lg w-100" type="submit">ยืนยัน</button>
    </form>

    <details class="mt-4 small text-muted">
        <summary>ไม่มีมือถือ / ทำมือถือหาย</summary>
        <div class="mt-2">กรอกรหัสสำรองที่ได้ตอนเปิดใช้งาน (รูปแบบ xxxxxx-xxxxxx) ในช่องด้านบนแทนได้ ถ้าไม่มีรหัสสำรอง ให้ผู้ดูแลระบบล้างการยืนยัน 2 ชั้นให้</div>
    </details>
    <div class="text-center mt-3"><a href="{{ route('login') }}" class="small">กลับไปหน้าเข้าสู่ระบบ</a></div>
@endsection
