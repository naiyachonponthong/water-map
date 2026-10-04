@extends('layouts.public')
@section('title', 'พื้นที่ของฉัน · สถานการณ์น้ำและฝน')
@section('body-class', 'flood-home')
@push('head')
<link rel="stylesheet" href="{{ asset('css/public-home.css') }}?v={{ filemtime(public_path('css/public-home.css')) }}">
<link rel="stylesheet" href="{{ asset('css/insights.css') }}?v={{ filemtime(public_path('css/insights.css')) }}">
@endpush
@section('content')
@include('partials.public-home-nav')
<main class="ia-shell" data-insights data-initial="{{ $province?->slug }}">
    <header class="ia-hero">
        <div><span class="ia-eyebrow">MY AREA / พื้นที่ที่คุณห่วงใย</span><h1>รู้ทันน้ำและฝน<br><span>ใกล้บ้านคุณ</span></h1><p>เลือกพื้นที่ครั้งเดียว กลับมาเช็กสถานการณ์ได้ทุกวัน</p></div>
        <div class="ia-hero-symbol" aria-hidden="true"><i class="bi bi-house-heart"></i><span><i class="bi bi-droplet-fill"></i></span></div>
    </header>
    <section class="ia-selection" aria-label="เลือกพื้นที่ของฉัน">
        <div class="ia-fields">
            <label>จังหวัด<select data-province><option value="">เลือกจังหวัด</option>@foreach($locations as $p)<option value="{{ $p['slug'] }}">{{ $p['name'] }}</option>@endforeach</select></label>
            <label>อำเภอ / เขต<select data-district disabled><option value="">ทั้งจังหวัด</option></select></label>
            <label>ตำบล / แขวง<select data-subdistrict disabled><option value="">ทั้งอำเภอ</option></select></label>
            <button class="ia-primary" type="button" data-save disabled><i class="bi bi-bookmark-plus"></i> บันทึกพื้นที่</button>
        </div>
        <div class="ia-saved-row"><div data-saved aria-label="พื้นที่ที่บันทึก"></div><button class="ia-text-button" type="button" data-forget>ล้างพื้นที่ที่บันทึก</button></div>
        <p class="ia-caption" data-selection-message role="status">จำเฉพาะพื้นที่ที่เลือกในเบราว์เซอร์นี้ ไม่ส่งพิกัดส่วนตัว · บันทึกได้ 5 พื้นที่</p>
    </section>
    <div class="ia-section-heading"><div><span class="ia-eyebrow">ภาพรวมที่เลือก</span><h2 data-area-title>เลือกพื้นที่เพื่อเริ่มต้น</h2></div><button type="button" class="ia-secondary" data-refresh disabled><i class="bi bi-arrow-clockwise"></i> อัปเดตข้อมูล</button></div>
    <div class="ia-grid">
        <section class="ia-card ia-rain"><header><div><span class="ia-icon"><i class="bi bi-cloud-rain"></i></span><h2>ฝน 24 ชั่วโมงข้างหน้า</h2></div><a data-link="weather">ดูพยากรณ์ <i class="bi bi-arrow-up-right"></i></a></header><div class="ia-card-body"><p class="ia-caption">พยากรณ์บริเวณตัวเมืองของจังหวัด ไม่ใช่พยากรณ์รายตำบล</p><div class="ia-metric"><strong data-rain-value>—</strong><span>มม.</span></div><p data-rain-caption role="status">ยังไม่ได้เลือกจังหวัด</p><div data-rain-chart class="ia-chart"></div><p class="ia-caption" data-rain-time></p><details><summary>ดูข้อมูลรายชั่วโมง</summary><div class="ia-table-scroll" data-rain-table></div></details></div></section>
        <section class="ia-card ia-reports"><header><div><span class="ia-icon ia-orange"><i class="bi bi-chat-square-text"></i></span><h2>รายงานน้ำท่วมในพื้นที่</h2></div><a data-link="map">ดูแผนที่ <i class="bi bi-arrow-up-right"></i></a></header><div class="ia-card-body"><div class="ia-metric"><strong data-report-value>—</strong><span>รายงาน</span></div><p data-report-caption role="status">ยังไม่ได้เลือกจังหวัด</p><div data-report-chart class="ia-chart"></div><p class="ia-caption">รายงานที่ยังเผยแพร่ แยกตามเวลาอัปเดตใน 24 ชั่วโมง ไม่ใช่จำนวนเหตุใหม่</p><p class="ia-note">ไม่พบรายงาน ≠ ไม่มีน้ำท่วม ข้อมูลอาจยังไม่ครอบคลุมพื้นที่</p><details><summary>ดูจำนวนรายชั่วโมง</summary><div class="ia-table-scroll" data-report-table></div></details></div></section>
        <section class="ia-card ia-water"><header><div><span class="ia-icon ia-teal"><i class="bi bi-water"></i></span><h2>แนวโน้มระดับน้ำสถานี</h2></div><a data-link="waterMap">แผนที่สถานี <i class="bi bi-arrow-up-right"></i></a></header><div class="ia-card-body"><div class="ia-water-heading"><label>สถานีภายในจังหวัด<select data-station disabled><option value="">ยังไม่มีข้อมูล</option></select></label><p class="ia-caption">ระดับน้ำลำน้ำ ไม่ใช่ความลึกน้ำท่วมหน้าบ้าน<br>ช่วง 72 ชั่วโมง · ช่องว่างหมายถึงไม่มีข้อมูล</p></div><p data-water-caption role="status">ยังไม่ได้เลือกจังหวัด</p><div data-water-chart class="ia-water-chart"></div><p class="ia-caption" data-water-note></p><details><summary>ดูค่าตรวจวัดและเวลา</summary><div class="ia-table-scroll" data-water-table></div></details></div></section>
        <aside class="ia-card ia-status"><div class="ia-card-body"><span class="ia-eyebrow">แหล่งข้อมูลที่ตรวจสอบได้</span><h2>ข้อมูลนี้ใหม่แค่ไหน?</h2><div data-health-mini role="status">เลือกจังหวัดเพื่อดูสถานะ</div><a class="ia-secondary" data-link="status"><i class="bi bi-activity"></i> ดูเวลาตรวจวัดและต้นทาง</a><p class="ia-caption">ThaiWater · Open-Meteo · รายงานในพื้นที่<br>ค่าที่ขาดหายจะไม่ถูกแทนด้วยศูนย์</p></div></aside>
    </div>
    <nav class="ia-bottom-links" aria-label="บริการในจังหวัด"><a data-link="home"><i class="bi bi-house"></i> หน้าหลักจังหวัด</a><a href="{{ route('public.guide') }}"><i class="bi bi-book"></i> คู่มือการใช้งาน</a></nav>
    <noscript><p class="ia-note">หน้านี้ต้องเปิด JavaScript เพื่อเลือกพื้นที่และแสดงกราฟ ใช้หน้าหลักจังหวัดได้จากเมนูด้านบน</p></noscript>
    <script type="application/json" data-insights-config>{!! \Illuminate\Support\Js::encode($locations) !!}</script>
</main>
@endsection
@push('scripts')<script src="{{ asset('js/insights.js') }}?v={{ filemtime(public_path('js/insights.js')) }}" defer></script>@endpush
