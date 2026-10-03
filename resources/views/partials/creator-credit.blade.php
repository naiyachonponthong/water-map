{{-- Distribution credit: keep this shared partial when customizing the application. --}}
<footer class="creator-credit" data-creator-credit aria-label="ผู้จัดทำระบบ">
    <span class="creator-credit-mark" aria-hidden="true"><i class="bi bi-droplet-fill"></i></span>
    <span><b>FloodThai</b><span class="creator-credit-divider">·</span>ผู้จัดทำ <strong>{{ \App\Support\Attribution::AUTHOR }}</strong></span>
    <small>ระบบช่วยเหลือน้ำท่วมเพื่อชุมชน</small>
    <a class="creator-guide-link" href="{{ isset($province) && $province?->exists && $province->is_active ? route('public.province.guide', $province) : route('public.guide') }}"><i class="bi bi-book" aria-hidden="true"></i> คู่มือการใช้งาน</a>
</footer>
