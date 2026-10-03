<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0e2233">
    <title>@yield('title', 'เข้าสู่ระบบ') | {{ $appTitle }}</title>
    @include('partials.head')
</head>
<body>
<div class="auth-wrap">
    <section class="auth-art">
        <div class="d-flex align-items-center gap-2">
            <span class="rail-logo m-0" style="width:44px;height:44px;border-radius:14px;display:grid;place-items:center;background:linear-gradient(135deg,var(--sb-grad-from),var(--sb-grad-to));font-size:1.3rem"><i class="bi bi-droplet-fill"></i></span>
            <div>
                <div class="fw-bold">ศูนย์ช่วยเหลือน้ำท่วม</div>
                <div class="small opacity-75">ระบบศูนย์สั่งการสำหรับเจ้าหน้าที่</div>
            </div>
        </div>
        <div>
            <div class="text-uppercase small fw-bold opacity-75" style="letter-spacing:.12em">Flood Command Center</div>
            <h1 class="fw-bold mt-2 mb-3" style="font-size:2.3rem;letter-spacing:-.02em;line-height:1.25">รับเคส สั่งทีม<br>ติดตามจนทุกคนปลอดภัย</h1>
            <ul class="list-unstyled opacity-90 mb-0" style="line-height:2">
                <li><i class="bi bi-check2-circle me-2"></i>เห็นเคสขอความช่วยเหลือและทีมกู้ภัยบนแผนที่เดียว</li>
                <li><i class="bi bi-check2-circle me-2"></i>เตือนจุดเสี่ยงและกลุ่มเปราะบางก่อนน้ำมาถึง</li>
                <li><i class="bi bi-check2-circle me-2"></i>ทีมภาคสนามรับงานและอัปเดตจากมือถือ</li>
            </ul>
        </div>
        <div class="small opacity-75">มีปัญหาการใช้งาน ติดต่อผู้อำนวยการศูนย์ของจังหวัด</div>
        <svg class="wave" viewBox="0 0 1440 180" preserveAspectRatio="none"><path fill="#fff" d="M0 96c120-32 240-48 360-32s240 64 360 64 240-48 360-64 240 0 360 32v84H0z"/></svg>
    </section>
    <section class="auth-form">
        <div class="auth-card">
            @yield('content')
        </div>
    </section>
</div>
@include('partials.flash')
@include('partials.creator-credit')
@include('partials.scripts')
@stack('scripts')
</body>
</html>
