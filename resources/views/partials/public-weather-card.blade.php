<section class="card mb-3 pw-home-card" data-weather-card data-url="{{ route('public.weather.forecast', $province) }}" data-reveal aria-label="พยากรณ์ฝน 24 ชั่วโมง">
    <div class="card-header"><i class="bi bi-cloud-rain text-primary" aria-hidden="true"></i> ฝนในพื้นที่ของคุณ <span class="pw-badge">24 ชั่วโมงข้างหน้า</span></div>
    <div class="card-body">
        <div class="pw-home-summary"><span class="pw-weather-icon"><i class="bi bi-cloud-rain" aria-hidden="true"></i></span><div><div class="pw-muted">พยากรณ์บริเวณตัวเมือง{{ $province->name_th }}</div><strong data-weather-category>กำลังโหลดพยากรณ์…</strong><p data-weather-summary role="status">ข้อมูลฝนและโอกาสฝนรายชั่วโมง</p></div></div>
        <a href="{{ route('public.weather', $province) }}" class="pw-detail-link">ดูเรดาร์ฝนและพยากรณ์ฝน–ลม <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
        <div class="pw-mini-chart" data-weather-chart hidden></div>
        <div class="pw-source" data-weather-updated></div>
        <button type="button" class="pw-retry" data-weather-retry hidden>ลองโหลดอีกครั้ง</button>
    </div>
</section>
