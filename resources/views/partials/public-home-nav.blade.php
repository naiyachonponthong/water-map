<nav class="fh-nav" aria-label="เมนูหลัก">
    <div class="fh-container fh-nav-inner">
        <a class="fh-brand" href="{{ route('public.provinces') }}">
            <span class="fh-brand-icon"><i class="bi bi-droplet-fill" aria-hidden="true"></i></span>
            <span><strong>ศูนย์ช่วยเหลือน้ำท่วม</strong><small>เคียงข้างทุกคน ในทุกสถานการณ์</small></span>
        </a>
        <div class="fh-nav-links">
            @isset($province)
                <div class="fh-desktop-nav"><a href="{{ route('public.province', $province) }}#local-info">ภาพรวมพื้นที่</a><a href="{{ route('public.water-map', $province) }}">ระดับน้ำสถานี</a><a href="{{ route('public.weather', $province) }}">ฝนและพยากรณ์</a></div>
            @endisset
            @isset($provinceOptions)
                <div class="fh-province-control"><i class="bi bi-geo-alt" aria-hidden="true"></i><select aria-label="เลือกจังหวัดบนหน้าหลัก" data-home-province>
                    @foreach($provinceOptions as $p)<option value="{{ route('public.province', $p->slug) }}" @selected($p->slug === $province->slug)>{{ $p->name_th }}</option>@endforeach
                </select></div>
            @else
            <a href="{{ route('public.provinces') }}" class="fh-location"><i class="bi bi-geo-alt" aria-hidden="true"></i> {{ isset($province) ? $province->name_th : 'เลือกจังหวัด' }} <i class="bi bi-chevron-down" aria-hidden="true"></i></a>
            @endisset
            <a href="{{ route('login') }}" class="fh-staff" aria-label="เข้าสู่ระบบสำหรับเจ้าหน้าที่"><i class="bi bi-person-badge" aria-hidden="true"></i><span>สำหรับเจ้าหน้าที่</span></a>
            <a href="{{ isset($province) ? route('public.province.guide', $province) : route('public.guide') }}" class="fh-guide" aria-label="คู่มือการใช้งาน"><i class="bi bi-book" aria-hidden="true"></i><span>คู่มือการใช้งาน</span></a>
        </div>
    </div>
</nav>
