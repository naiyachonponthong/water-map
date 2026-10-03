@extends('layouts.public')
@section('title', 'แผนที่สถานการณ์น้ำ '.$province->fullName())
@section('description', 'ระดับน้ำสถานี ThaiWater รายงานจากประชาชน จุดเสี่ยงและศูนย์พักพิง แยกข้อมูลชัดเจนในจังหวัดที่เลือก')
@section('body-class', 'situation-body')
@push('head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="{{ asset('css/situation-map.css') }}?v={{ filemtime(public_path('css/situation-map.css')) }}">
@endpush
@section('content')
<main class="situation-page">
    <header class="sm-header">
        <a class="sm-back" href="{{ route('public.province', $province) }}" aria-label="กลับหน้าจังหวัด"><i class="bi bi-arrow-left"></i></a>
        <div class="sm-heading"><small>FLOODTHAI · SITUATION MAP</small><h1>แผนที่สถานการณ์น้ำ</h1><p>{{ $province->fullName() }} · ข้อมูลหลายแหล่งในแผนที่เดียว</p></div>
        <label class="sm-province">เลือกจังหวัด<select id="pmProvince" aria-label="เลือกจังหวัด">
            @foreach($provinceOptions as $p)<option value="{{ route('public.map', $p->slug) }}" @selected($p->slug === $province->slug)>{{ $p->name_th }}</option>@endforeach
        </select></label>
        <a class="sm-hotline" href="tel:{{ preg_replace('/\D+/', '', $hotline) }}"><i class="bi bi-telephone-fill"></i> {{ $hotline }}</a>
    </header>
    <section class="sm-intro sm-art-intro"><div class="sm-intro-copy"><span class="sm-live"><i></i> ดูสถานการณ์ในพื้นที่</span><h2>รู้ระดับน้ำ เห็นจุดเสี่ยง<br class="d-md-none"> เตรียมพร้อมได้ทัน</h2><p>ตัวเลขในวงกลมคือระดับน้ำสถานี · จุดสีคือความลึกน้ำที่ประชาชนรายงาน ไม่ใช่ข้อมูลชนิดเดียวกัน</p><div class="sm-intro-actions"><a href="{{ route('public.water-map', $province) }}#nearby"><i class="bi bi-crosshair" aria-hidden="true"></i> น้ำท่วมใกล้บ้านฉันไหม? <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a><a class="sm-guide-link" href="{{ route('public.province.guide', $province) }}#map"><i class="bi bi-book" aria-hidden="true"></i> วิธีอ่านแผนที่</a></div></div><div class="sm-intro-art"><img src="{{ asset('images/situation-riverside-hero.png') }}" alt="" width="2048" height="768" fetchpriority="high"><small>ภาพประกอบ ไม่ใช่สถานการณ์จริง</small></div></section>
    <section class="sm-stats" aria-label="สรุปสถานการณ์จังหวัด">
        <div><i class="bi bi-broadcast"></i><span>สถานี ThaiWater<strong id="pmWaterCount">—</strong></span></div>
        <div><i class="bi bi-chat-square-text"></i><span>รายงานประชาชน<strong id="pmReports">{{ $summary['reports'] }}</strong></span></div>
        <div><i class="bi bi-exclamation-octagon"></i><span>รายงานระดับอกขึ้นไป<strong id="pmDeep">{{ $summary['deep'] }}</strong></span></div>
        <div><i class="bi bi-exclamation-triangle"></i><span>จุดเสี่ยงที่มีคำเตือน<strong id="pmRiskCount">{{ $summary['risks'] }}</strong></span></div>
    </section>
    @foreach($alerts as $a)<div class="alert-bar mb-2" style="--ac:{{ $a->color() }}"><i class="bi bi-{{ $a->icon() }}"></i><div><b>{{ $a->levelLabel() }}:</b> {{ $a->title }}</div></div>@endforeach
    <nav class="sm-mobile-nav" aria-label="ทางลัดแผนที่"><a href="#pmMapPanel"><i class="bi bi-map"></i> ดูแผนที่</a><a href="#pmLayersPanel"><i class="bi bi-layers"></i> เลือกชั้นข้อมูล</a><a href="#pmStationsPanel"><i class="bi bi-broadcast"></i> รายการสถานี</a></nav>
    <div class="sm-workspace">
        <aside class="sm-sidebar" id="pmLayersPanel">
            <section class="sm-panel"><div class="sm-panel-head"><h2><i class="bi bi-layers"></i> เลือกข้อมูลบนแผนที่</h2><button id="pmRefresh" class="sm-icon-btn" type="button" aria-label="อัปเดตข้อมูล"><i class="bi bi-arrow-clockwise"></i></button></div>
                <div class="pmap-legend sm-layers">
                    <label class="sm-main-layer"><input type="checkbox" checked data-layer="thaiwater"><i class="bi bi-broadcast"></i><span>ระดับน้ำจาก ThaiWater<small>ตัวเลขสถานี · ม. รทก. / ม.</small></span></label>
                    <details open><summary>รายงานความลึกน้ำจากประชาชน</summary><div class="sm-levels">
                        @foreach($levels as $k => $lv)<label><input type="checkbox" checked data-level="{{ $k }}"><span class="lv-dot" style="background:{{ $lv['color'] }}"></span>{{ $lv['short'] }}</label>@endforeach
                    </div></details>
                    <div class="sm-other-layers">
                        <label><input type="checkbox" checked data-layer="risks"><i class="bi bi-exclamation-triangle-fill text-warning"></i> จุดเสี่ยง</label>
                        <label><input type="checkbox" checked data-layer="stations"><i class="bi bi-diamond-fill text-primary"></i> สถานีของหน่วยงาน</label>
                        <label><input type="checkbox" checked data-layer="shelters"><i class="bi bi-house-heart-fill text-success"></i> ศูนย์พักพิง</label>
                        <label><input type="checkbox" checked data-layer="cameras"><i class="bi bi-camera-video-fill"></i> กล้อง CCTV</label>
                    </div>
                </div>
                <p class="sm-source-status" id="pmDataStatus" role="status">กำลังโหลดรายงานในพื้นที่…</p>
            </section>
            <section class="sm-panel sm-stations" id="pmStationsPanel"><div class="sm-panel-head"><h2>ระดับน้ำในจังหวัด</h2><span class="sm-pill">ThaiWater</span></div><label class="sm-search"><i class="bi bi-search"></i><input id="pmWaterSearch" type="search" placeholder="ค้นหาสถานี / อำเภอ" aria-label="ค้นหาสถานี ThaiWater"></label><p id="pmWaterStatus" class="sm-source-status" role="status">กำลังโหลดสถานี…</p><div id="pmWaterList" class="sm-water-list">กำลังโหลดข้อมูลระดับน้ำ…</div><a class="sm-source-link" href="https://www.thaiwater.net/" target="_blank" rel="noopener">ข้อมูลระดับน้ำ: ThaiWater สสน. ↗</a></section>
        </aside>
        <section class="sm-map-panel" id="pmMapPanel" aria-label="แผนที่สถานการณ์ในจังหวัดที่เลือก">
            <div class="sm-map-top"><div><small>จังหวัดที่เลือก</small><h2>{{ $province->fullName() }}</h2></div><button id="pmFit" type="button"><i class="bi bi-arrows-fullscreen"></i> ดูทั้งจังหวัด</button></div>
            <div class="sm-map-stage"><div id="pubMap" aria-label="แผนที่จังหวัด{{ $province->name_th }}"></div><div id="pmBoundaryMessage" class="sm-map-message" role="status">กำลังโหลดขอบเขตจังหวัด…</div><div class="sm-map-chip"><span></span> {{ $province->name_th }} · ขอบเขตสีเขียวเข้ม</div><div class="sm-map-credit">FloodThai · {{ \App\Support\Attribution::AUTHOR }}</div></div>
            <div class="sm-map-legend"><b>สีสถานี ThaiWater</b><span style="--tone:#a76317">น้ำน้อยวิกฤติ</span><span style="--tone:#b38a12">น้ำน้อย</span><span style="--tone:#16875c">ปกติ</span><span style="--tone:#2676d6">น้ำมาก</span><span style="--tone:#df4058">ล้นตลิ่ง</span><span style="--tone:#87949c">ข้อมูลเก่า / ไม่มีเกณฑ์</span></div>
            <footer class="sm-map-footer"><p>รายงานในพื้นที่อัปเดต <span id="pmTime">—</span> · ไม่พบรายงานไม่ได้หมายความว่าไม่มีน้ำท่วม</p><p id="pmBoundarySource">ขอบเขตอ้างอิง geoBoundaries / OpenStreetMap (ODbL), ปี 2017 ไม่ใช่ขอบเขตทางกฎหมาย</p></footer>
        </section>
    </div>
    <div class="sm-actions">@if($open)<a class="sm-report-action" href="{{ route('public.reports.create', $province) }}"><i class="bi bi-droplet-half"></i> แจ้งระดับน้ำในพื้นที่</a>@endif<a class="sm-help-action" href="{{ route('public.help', $province) }}"><i class="bi bi-life-preserver"></i> ขอความช่วยเหลือ</a><a class="sm-detail-action" href="{{ route('public.water-map', $province) }}">ดูรายละเอียดระดับน้ำ <i class="bi bi-arrow-right"></i></a></div>
    <p class="sm-safety"><i class="bi bi-shield-check"></i> ใช้ประกอบการเฝ้าระวัง ไม่ใช่การรับรองความปลอดภัย · เหตุฉุกเฉินโทร {{ $hotline }}</p>
</main>
@endsection
@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="{{ asset('js/map.js') }}?v={{ filemtime(public_path('js/map.js')) }}"></script>
    <script src="{{ asset('js/risks.js') }}?v={{ filemtime(public_path('js/risks.js')) }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.15/dist/hls.min.js"></script>
    <script src="{{ asset('js/stations.js') }}?v={{ filemtime(public_path('js/stations.js')) }}"></script>
    <script src="{{ asset('js/situation-water.js') }}?v={{ filemtime(public_path('js/situation-water.js')) }}"></script>
    <script src="{{ asset('js/public-map.js') }}?v={{ filemtime(public_path('js/public-map.js')) }}"></script>
    <script>
        FloodPublicMap.init({
            el: document.getElementById('pubMap'), center: [13.5, 101], zoom: 6,
            dataUrl: @json(route('public.map.geojson', $province)),
            contextUrl: @json(route('public.water.context', $province)),
            waterUrl: @json(route('public.water.data', $province)),
            voteUrl: @json(url($province->slug.'/reports/__ID__/vote')),
            focus: @json($focus), votes: @json(\App\Support\ReportOptions::VOTES),
        });
    </script>
@endpush
