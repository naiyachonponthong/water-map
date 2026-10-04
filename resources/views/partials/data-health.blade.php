<section class="ia-health" data-data-health data-status-url="{{ route('public.data-status.json', $province, false) }}" data-water-url="{{ route('public.water.data', $province, false) }}" data-forecast-url="{{ route('public.weather.forecast', $province, false) }}" data-radar-url="{{ route('public.weather.radar', $province, false) }}">
    <div class="ia-section-heading"><div><span class="ia-eyebrow">DATA TRANSPARENCY</span><h2>ความสดของข้อมูล · {{ $province->name_th }}</h2></div><button class="ia-primary" type="button" data-health-refresh><i class="bi bi-arrow-clockwise"></i> ตรวจต้นทางอีกครั้ง</button></div>
    <p>แยกเวลาที่ระบบดึงข้อมูลออกจากเวลาที่สถานีตรวจวัด ข้อมูลที่แสดงจากแคชอาจยังเป็นข้อมูลเก่า</p>
    <p class="ia-caption">สถานะนี้เป็นการตรวจการเชื่อมต่อและอายุข้อมูล ไม่ใช่การประกาศเตือนน้ำท่วม · เวลาไทย (UTC+7)</p>
    <div data-health-cards class="ia-health-grid" aria-live="polite">กำลังอ่านสถานะ…</div>
    <p data-health-message class="ia-caption" role="status"></p>
</section>
