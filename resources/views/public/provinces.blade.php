@extends('layouts.public')
@section('title', 'เลือกจังหวัด')
@section('body-class', 'flood-home')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/public-home.css') }}?v={{ filemtime(public_path('css/public-home.css')) }}">
@endpush

@section('content')
    @include('partials.public-home-nav')
    <header class="fh-hero">
        <div class="fh-container fh-hero-grid">
            <div class="fh-hero-copy" data-reveal>
                <div class="fh-eyebrow"><span class="fh-dot"></span> ศูนย์ข้อมูลและความช่วยเหลือน้ำท่วม</div>
                <h1>ไม่ว่าจะอยู่ที่ไหน<br><span>เราพร้อมเคียงข้างคุณ</span></h1>
                <p>ติดตามสถานการณ์น้ำ ค้นหาศูนย์พักพิง และเข้าถึงความช่วยเหลือ<br class="fh-desktop-break"> เริ่มต้นด้วยการเลือกจังหวัดที่คุณอยู่</p>
                <div class="fh-hero-buttons">
                    <a href="#choose-province" class="fh-button fh-button-orange"><i class="bi bi-geo-alt" aria-hidden="true"></i> เลือกจังหวัดของคุณ <i class="bi bi-arrow-down" aria-hidden="true"></i></a>
                    <a href="tel:1784" class="fh-button fh-button-outline"><i class="bi bi-telephone" aria-hidden="true"></i> สายด่วน 1784</a>
                </div>
                <div class="fh-hero-footnote"><i class="bi bi-shield-check" aria-hidden="true"></i> เหตุฉุกเฉินที่ต้องการความช่วยเหลือทันที โทร 1784</div>
            </div>
            @include('partials.public-home-art')
        </div>
        <div class="fh-waves" aria-hidden="true"><span></span><span></span></div>
    </header>

    <main class="fh-container fh-picker" id="choose-province">
        <div class="fh-section-heading" data-reveal><div><span class="fh-kicker">เริ่มจากพื้นที่ของคุณ</span><h2>เลือกจังหวัดของคุณ</h2></div><span class="small text-muted">ครอบคลุม {{ $byRegion->flatten(1)->count() }} จังหวัด</span></div>
        @if($open->isNotEmpty())
            <div data-reveal>
                <div class="small fw-bold"><span class="chip chip-dot chip-success">เปิดศูนย์สั่งการ</span></div>
                <div class="fh-open-provinces">
                    @foreach($open as $p)
                        <a href="{{ route('public.province', $p) }}" class="fh-province-link is-open">{{ $p->name_th }} <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                    @endforeach
                </div>
            </div>
        @endif
        <div class="fh-picker-panel" data-reveal>
            <label class="fh-search" for="provinceSearch"><i class="bi bi-search" aria-hidden="true"></i><input type="search" id="provinceSearch" class="form-control" placeholder="ค้นหาจังหวัด เช่น ตรัง เชียงใหม่ กรุงเทพ" aria-label="ค้นหาจังหวัด" autocomplete="off"></label>
            <div class="fh-region-grid">
                @foreach($byRegion as $region => $list)
                    <section class="fh-region" data-region>
                        <h3>{{ $region }}</h3>
                        <div class="fh-province-list">
                            @foreach($list->sortBy('name_th') as $p)
                                <a href="{{ route('public.province', $p) }}" class="fh-province-link {{ $p->command_open ? 'is-open' : '' }}" data-pname="{{ $p->name_th }}">{{ $p->name_th }}</a>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
            <div id="provinceNoResults" class="fh-no-results" role="status" hidden>ไม่พบจังหวัดที่ค้นหา ลองตรวจสอบชื่อจังหวัดอีกครั้ง</div>
        </div>
        <footer class="fh-footer"><span><i class="bi bi-droplet-fill" aria-hidden="true"></i> ศูนย์ช่วยเหลือน้ำท่วม</span><a href="{{ route('login') }}">เจ้าหน้าที่และทีมกู้ภัย เข้าสู่ระบบ <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a></footer>
    </main>
    <a href="tel:1784" class="btn btn-danger call-fab"><i class="bi bi-telephone-fill me-1" aria-hidden="true"></i>โทรกู้ภัย 1784</a>
@endsection

@push('scripts')
    <script src="{{ asset('js/public-home.js') }}?v={{ filemtime(public_path('js/public-home.js')) }}" defer></script>
@endpush
