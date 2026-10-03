@extends('layouts.public')
@section('title', 'พยากรณ์อากาศ '.$province->name_th)
@section('body-class', 'flood-weather weather-dashboard')
@push('head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="{{ asset('css/public-weather.css') }}?v={{ filemtime(public_path('css/public-weather.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/weather-dashboard.css') }}?v={{ filemtime(public_path('css/weather-dashboard.css')) }}">
@endpush
@section('content')
<main class="pw-page" id="weatherPage" data-slug="{{ $province->slug }}" data-forecast-url="{{ route('public.weather.forecast', $province, false) }}" data-radar-url="{{ route('public.weather.radar', $province, false) }}" data-has-center="{{ $center ? '1' : '0' }}" data-lat="{{ $center[0] ?? 13.7563 }}" data-lng="{{ $center[1] ?? 100.5018 }}" data-province="{{ $province->name_th }}">
    <header class="pw-header">
        <a id="weatherHome" href="{{ route('public.province', $province) }}" class="pw-back" aria-label="กลับหน้าจังหวัด"><i class="bi bi-arrow-left" aria-hidden="true"></i></a>
        <div class="pw-brand"><span class="pw-kicker">FLOODTHAI · WEATHER CENTER</span><h1>อากาศและฝน <span id="weatherProvinceTitle">{{ $province->name_th }}</span></h1></div>
        <div class="pw-header-actions"><div class="pw-location-picker"><i class="bi bi-geo-alt" aria-hidden="true"></i><div><label for="weatherProvince">เปลี่ยนจังหวัด · ดูพยากรณ์ทันที</label><select id="weatherProvince">@foreach($locations as $p)<option value="{{ $p['slug'] }}" @selected($p['slug'] === $province->slug)>{{ $p['name'] }}</option>@endforeach</select></div></div><button type="button" class="pw-refresh-all" id="forecastRefresh" aria-label="อัปเดตพยากรณ์"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i><span>อัปเดต</span></button></div>
    </header>
    <div class="pw-dashboard-content">
        <a class="pw-related-water" id="weatherWaterLink" href="{{ route('public.water-map', $province) }}"><i class="bi bi-water" aria-hidden="true"></i><span><strong>ดูระดับน้ำในจังหวัด</strong><small>สถานี ThaiWater และรายงานน้ำท่วมใกล้บ้าน</small></span><i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
        <div class="pw-overview">
            <section class="pw-current-card" aria-label="สภาพอากาศจากแบบจำลอง">
                <div class="pw-current-top"><span><i class="bi bi-geo-alt" aria-hidden="true"></i> ตัวเมือง<span id="weatherCity">{{ $province->name_th }}</span></span><span class="pw-model-tag">สภาพอากาศ</span></div>
                <div class="pw-current-main"><div><div class="pw-temperature"><span id="currentTemperature">—</span><small>°C</small></div><strong id="currentCondition">กำลังโหลดข้อมูล…</strong></div><i id="currentIcon" class="bi bi-cloud-sun" aria-hidden="true"></i></div>
                <p id="currentTime">ข้อมูลจากแบบจำลอง ไม่ใช่การวัดภาคสนาม</p>
                <div class="pw-current-details"><div><i class="bi bi-thermometer-half" aria-hidden="true"></i><small>รู้สึกเหมือน</small><strong id="currentFeels">— °C</strong></div><div><i class="bi bi-droplet" aria-hidden="true"></i><small>ความชื้น</small><strong id="currentHumidity">— %</strong></div><div><i class="bi bi-wind" aria-hidden="true"></i><small>ลม</small><strong id="currentWind">— กม./ชม.</strong></div></div>
            </section>
            <section class="pw-outlook-card" aria-label="พยากรณ์ฝน 24 ชั่วโมงข้างหน้า">
                <div class="pw-card-title"><div><span class="pw-kicker">เตรียมพร้อมล่วงหน้า</span><h2>ฝนใน 24 ชั่วโมงข้างหน้า</h2></div><span class="pw-outlook-category" id="summaryCategory">กำลังโหลด…</span></div>
                <div class="pw-summary-metrics"><div><small>ปริมาณฝนรวม</small><strong><span id="summaryRain">—</span> <em>มม.</em></strong></div><div><small>โอกาสฝนสูงสุด</small><strong><span id="summaryChance">—</span> <em>%</em></strong></div><div><small>ช่วงแรกที่คาดว่ามีฝน</small><strong class="pw-summary-start" id="summaryStart">—</strong></div></div>
                <div id="summaryChart" class="pw-summary-chart"></div>
                <div class="pw-data-note"><span id="weatherUpdated" role="status">กำลังโหลดพยากรณ์ของ{{ $province->name_th }}…</span><button type="button" class="pw-retry" id="forecastRetry" hidden>ลองโหลดอีกครั้ง</button><a href="https://open-meteo.com/" target="_blank" rel="noopener">Open-Meteo <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a></div>
            </section>
        </div>
        <section class="pw-days-section" aria-label="พยากรณ์รายวัน"><div class="pw-section-title"><div><h2>วางแผนได้ทั้งสัปดาห์</h2><p>เลือกวันเพื่อดูรายละเอียดฝน อุณหภูมิ และลมด้านล่าง</p></div><span class="pw-soft-label">พยากรณ์สูงสุด 7 วัน</span></div><div class="pw-day-cards" id="weatherDays" aria-busy="true">@foreach(['วันนี้', 'พรุ่งนี้', 'มะรืนนี้', 'อีก 3 วัน', 'อีก 4 วัน', 'อีก 5 วัน', 'อีก 6 วัน'] as $label)<article class="pw-day"><div class="pw-day-label">{{ $label }}</div><div class="pw-skeleton"></div><strong>—</strong></article>@endforeach</div></section>
        <div class="pw-workspace-heading"><div><h2>รายละเอียดในพื้นที่</h2><p>พยากรณ์เป็นค่าคาดการณ์ · เรดาร์เป็นภาพฝนย้อนหลัง</p></div><div class="pw-tabs" role="tablist" aria-label="ประเภทข้อมูลอากาศ"><button type="button" id="forecastTab" role="tab" aria-controls="forecastPanel" aria-selected="true"><i class="bi bi-bar-chart" aria-hidden="true"></i> พยากรณ์ฝน–ลม</button><button type="button" id="radarTab" role="tab" aria-controls="radarPanel" aria-selected="false" tabindex="-1"><i class="bi bi-broadcast" aria-hidden="true"></i> เรดาร์ย้อนหลัง</button></div></div>
        <div class="pw-workspace" id="weatherWorkspace">
            <section class="pw-forecast-panel" id="forecastPanel" role="tabpanel" aria-labelledby="forecastTab">
                <div class="pw-forecast-heading"><div><h2 id="forecastSelectedDay">พยากรณ์รายชั่วโมง</h2><p>เวลาไทย · ปริมาณฝนสะสมในชั่วโมงก่อนเวลาที่ระบุ</p></div><div><label for="forecastDay">เลือกวัน</label><select id="forecastDay" disabled></select></div></div>
                <div class="pw-selected-metrics" id="forecastDayMetrics"></div>
                <div class="pw-hourly-chart" id="forecastChart"></div>
                <div class="pw-hour-table-wrap"><table class="pw-hour-table"><thead><tr><th scope="col">เวลา</th><th scope="col">สภาพอากาศ</th><th scope="col">ฝน / มม.</th><th scope="col">โอกาสฝน</th><th scope="col">อุณหภูมิ</th><th scope="col">ลม / กม./ชม.</th></tr></thead><tbody id="forecastHours"><tr><td colspan="6">กำลังโหลดพยากรณ์…</td></tr></tbody></table></div>
            </section>
            <section class="pw-radar-panel" id="radarPanel" aria-label="เรดาร์ฝนย้อนหลัง">
                <div class="pw-radar-heading"><div><h2><i class="bi bi-broadcast" aria-hidden="true"></i> เรดาร์ฝน</h2><p>ภาพย้อนหลัง · เลื่อนแถบเวลาเพื่อดูการเคลื่อนตัว</p></div><span class="pw-soft-label">RainViewer</span></div>
                <div class="pw-map-wrap"><div id="weatherMap" class="pw-map" aria-label="แผนที่เรดาร์ฝน"></div><div class="pw-map-caption"><span class="pw-live-dot"></span> ภาพฝนย้อนหลัง</div><button type="button" class="pw-map-center" id="radarCenter" aria-label="กลับจุดศูนย์กลางจังหวัด"><i class="bi bi-crosshair" aria-hidden="true"></i></button><div class="pw-map-message" id="radarMessage" role="status">กำลังโหลดภาพเรดาร์…</div><div class="pw-radar-legend"><span>บริเวณที่เรดาร์ตรวจพบฝน</span><small>ภาพว่างอาจหมายถึงไม่มีฝนหรือไม่มีข้อมูลครอบคลุม</small></div></div>
                <div class="pw-timeline"><button type="button" id="radarPlay" class="pw-play" aria-label="เล่นเรดาร์ย้อนหลัง" aria-pressed="false" disabled><i class="bi bi-play-fill" aria-hidden="true"></i></button><div class="pw-track"><input id="radarSlider" type="range" min="0" max="0" value="0" aria-label="เลือกเวลาเรดาร์" disabled><div class="pw-track-labels"><span id="radarStart">—</span><span id="radarEnd">—</span></div></div><div class="pw-selected-time"><strong id="radarTime">—</strong><small id="radarDate">เวลาไทย</small></div><button type="button" id="radarRefresh" class="pw-icon-button" aria-label="โหลดเรดาร์ใหม่"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i></button></div>
                <div class="pw-radar-note" id="radarNote">เรดาร์ไม่ใช่พยากรณ์อนาคต · บางพื้นที่อาจไม่มีข้อมูล</div>
                <div class="pw-radar-help"><i class="bi bi-info-circle" aria-hidden="true"></i><p>ใช้ประกอบการวางแผน ไม่ใช้ยืนยันระดับน้ำท่วม หากเกิดเหตุฉุกเฉิน โปรดติดต่อหน่วยงานในพื้นที่</p></div>
            </section>
        </div>
        <footer class="pw-footer">ข้อมูลบริเวณตัวเมืองของแต่ละจังหวัด ไม่ใช่ค่าเฉลี่ยทั้งจังหวัด · <a href="https://open-meteo.com/" target="_blank" rel="noopener">Open-Meteo</a> · <a href="https://www.rainviewer.com/" target="_blank" rel="noopener">RainViewer</a> · พิกัด <a href="https://www.geonames.org/" target="_blank" rel="noopener">GeoNames</a></footer>
    </div>
</main>
@endsection
@push('scripts')
    <script id="weatherBoundaries" type="application/json">{!! json_encode($boundaries, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    <script id="weatherLocations" type="application/json">{!! json_encode($locations, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="{{ asset('js/public-weather.js') }}?v={{ filemtime(public_path('js/public-weather.js')) }}" defer></script>
@endpush
