@extends('layouts.guest')
@section('title', 'เข้าสู่ระบบ')

@section('content')
    <div class="d-mobile text-center mb-4">
        <span class="app-ico primary mx-auto mb-2"><i class="bi bi-droplet-fill"></i></span>
        <div class="fw-bold">ศูนย์ช่วยเหลือน้ำท่วม</div>
    </div>

    <h2 class="fw-bold mb-1" style="letter-spacing:-.02em">เข้าสู่ระบบเจ้าหน้าที่</h2>
    <p class="text-muted mb-4">สำหรับศูนย์สั่งการ ทีมกู้ภัย และเจ้าหน้าที่ศูนย์พักพิง</p>

    @if($lineEnabled)
        <a href="{{ route('line.redirect') }}" class="btn btn-line btn-lg w-100 mb-3">
            <i class="bi bi-line me-2"></i>เข้าสู่ระบบด้วย LINE
        </a>
        <div class="d-flex align-items-center gap-3 my-3 text-muted small">
            <hr class="flex-grow-1 m-0"><span>หรือใช้เบอร์โทร</span><hr class="flex-grow-1 m-0">
        </div>
    @endif

    <form method="POST" action="{{ route('login.attempt') }}" novalidate>
        @csrf
        <div class="mb-3">
            <label class="form-label" for="phone">เบอร์โทร</label>
            <input type="tel" inputmode="numeric" autocomplete="username" class="form-control form-control-lg @error('phone') is-invalid @enderror"
                   id="phone" name="phone" value="{{ old('phone') }}" placeholder="08x-xxx-xxxx" required autofocus>
            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label class="form-label" for="password">รหัสผ่าน</label>
            <div class="input-group">
                <input type="password" autocomplete="current-password" class="form-control form-control-lg @error('password') is-invalid @enderror"
                       id="password" name="password" required>
                <button class="btn btn-light" type="button" onclick="const i=document.getElementById('password');i.type=i.type==='password'?'text':'password';this.firstElementChild.classList.toggle('bi-eye');this.firstElementChild.classList.toggle('bi-eye-slash')"><i class="bi bi-eye"></i></button>
            </div>
        </div>
        <div class="d-flex align-items-center mb-4">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1" checked>
                <label class="form-check-label small" for="remember">จดจำการเข้าสู่ระบบ</label>
            </div>
        </div>
        <button type="submit" class="btn btn-primary btn-lg w-100">เข้าสู่ระบบ</button>
    </form>

    <div class="text-center mt-4 small">
        ยังไม่มีบัญชี? <a href="{{ route('register') }}" class="fw-600">ขอใช้งานระบบ</a>
    </div>
    <div class="text-center mt-2 small">
        <a href="{{ route('public.provinces') }}" class="text-muted"><i class="bi bi-arrow-left"></i> กลับหน้าเว็บประชาชน</a>
    </div>
@endsection
