@extends('layouts.public')
@section('title', 'เรดาร์ฝนและพยากรณ์อากาศ '.$province->name_th)
@section('body-class', 'flood-weather')
@push('head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="{{ asset('css/public-weather.css') }}?v={{ filemtime(public_path('css/public-weather.css')) }}">
@endpush
@section('content')
<main class="pw-page" id="weatherPage" data-forecast-url="{{ route('public.weather.forecast', $province) }}" data-radar-url="{{ route('public.weather.radar', $province) }}" data-has-center="{{ $center ? '1' : '0' }}" data-lat="{{ $center[0] ?? 13.7563 }}" data-lng="{{ $center[1] ?? 100.5018 }}" data-province="{{ $province->name_th }}">
    <header class="pw-header">
        <a href="{{ route('public.province', $province) }}" class="pw-back" aria-label="กลับหน้าจังหวัด"><i class="bi bi-arrow-left" aria-hidden="true"></i></a>
        <div><span class="pw-kicker">รู้ทันฝน · เตรียมพร้อมก่อนเดินทาง</span><h1>พยากรณ์อากาศ <span>{{ $province->name_th }}</span></h1></div>
        <a class="pw-change" href="{{ route('public.provinces') }}"><i class="bi bi-geo-alt" aria-hidden="true"></i> เปลี่ยนจังหวัด</a>
    </header>
    <section class="pw-day-cards" aria-label="พยากรณ์ 3 วัน" id="weatherDays" aria-busy="true">
        @foreach(['วันนี้', 'พรุ่งนี้', 'มะรืนนี้'] as $label)
            <article class="pw-day"><div class="pw-day-label">{{ $label }}</div><strong>กำลังโหลด…</strong><div class="pw-skeleton"></div></article>
        @endforeach
    </section>
    <div class="pw-data-note"><span id="weatherUpdated" role="status">พยากรณ์บริเวณตัวเมือง ไม่ใช่ข้อมูลวัดระดับน้ำ</span><button type="button" class="pw-retry" id="forecastRetry" hidden>ลองโหลดอีกครั้ง</button><a href="https://open-meteo.com/" target="_blank" rel="noopener">Open-Meteo <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a></div>
    <div class="pw-tabs" role="tablist" aria-label="ประเภทข้อมูลอากาศ">
        <button type="button" id="radarTab" role="tab" aria-controls="radarPanel" aria-selected="true"><i class="bi bi-broadcast" aria-hidden="true"></i> ฝนล่าสุด (เรดาร์ย้อนหลัง)</button>
        <button type="button" id="forecastTab" role="tab" aria-controls="forecastPanel" aria-selected="false" tabindex="-1"><i class="bi bi-wind" aria-hidden="true"></i> พยากรณ์ฝน–ลม</button>
    </div>
    <section class="pw-radar-panel" id="radarPanel" role="tabpanel" aria-labelledby="radarTab">
        <div class="pw-map-wrap">
            <div id="weatherMap" class="pw-map" aria-label="แผนที่เรดาร์ฝน"></div>
            <div class="pw-map-caption"><span class="pw-live-dot"></span> ภาพฝนย้อนหลัง <span class="pw-map-tag">RainViewer</span></div>
            <button type="button" class="pw-map-center" id="radarCenter" aria-label="กลับจุดศูนย์กลางจังหวัด"><i class="bi bi-crosshair" aria-hidden="true"></i></button>
            <div class="pw-map-message" id="radarMessage" role="status">กำลังโหลดภาพเรดาร์…</div>
            <div class="pw-radar-legend"><span>สีฟ้า: บริเวณที่เรดาร์ตรวจพบฝน</span><small>ภาพว่างอาจเกิดจากไม่มีฝนหรืออยู่นอกพื้นที่ครอบคลุม</small></div>
        </div>
        <div class="pw-timeline">
            <button type="button" id="radarPlay" class="pw-play" aria-label="เล่นเรดาร์ย้อนหลัง" aria-pressed="false" disabled><i class="bi bi-play-fill" aria-hidden="true"></i></button>
            <div class="pw-track"><input id="radarSlider" type="range" min="0" max="0" value="0" aria-label="เลือกเวลาเรดาร์" disabled><div class="pw-track-labels"><span id="radarStart">—</span><span>ภาพย้อนหลัง · เวลาไทย</span><span id="radarEnd">—</span></div></div>
            <div class="pw-selected-time"><strong id="radarTime">—</strong><small id="radarDate">เวลาไทย (UTC+7)</small></div>
            <button type="button" id="radarRefresh" class="pw-icon-button" aria-label="โหลดเรดาร์ใหม่"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i></button>
        </div>
        <div class="pw-radar-note" id="radarNote">เรดาร์ไม่ใช่พยากรณ์อนาคต · บางพื้นที่อาจไม่มีข้อมูล · ภาพเรดาร์ขยายจากความละเอียดสูงสุดของผู้ให้บริการ</div>
    </section>
    <section class="pw-forecast-panel" id="forecastPanel" role="tabpanel" aria-labelledby="forecastTab" hidden>
        <div class="pw-forecast-heading"><div><span class="pw-kicker">วางแผนล่วงหน้า</span><h2>ฝนและลมรายชั่วโมง</h2><p>ปริมาณฝนเป็นค่าคาดการณ์ ไม่ใช่ระดับน้ำท่วม</p></div><div><label for="forecastDay">เลือกวัน</label><select id="forecastDay" disabled></select></div></div>
        <div class="pw-hourly-chart" id="forecastChart"></div>
        <div class="pw-hourly-list" id="forecastHours" role="status">กำลังโหลดพยากรณ์…</div>
    </section>
    <footer class="pw-footer">ข้อมูลใช้ประกอบการเตรียมพร้อม โปรดติดตามประกาศจากหน่วยงานในพื้นที่ · <a href="https://www.rainviewer.com/" target="_blank" rel="noopener">RainViewer</a> / <a href="https://open-meteo.com/" target="_blank" rel="noopener">Open-Meteo</a></footer>
</main>
@endsection
@push('scripts')
    <script id="weatherBoundaries" type="application/json">{!! json_encode($boundaries, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="{{ asset('js/public-weather.js') }}?v={{ filemtime(public_path('js/public-weather.js')) }}" defer></script>
@endpush
