@extends('layouts.public')
@section('title', 'ศูนย์ช่วยเหลือน้ำท่วม'.$province->fullName())
@section('description', 'ศูนย์ช่วยเหลือน้ำท่วม'.$province->fullName().' ระดับน้ำ พยากรณ์ฝน ศูนย์พักพิง และเบอร์ฉุกเฉิน')
@section('body-class', 'flood-home fh-home-dashboard')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/public-home.css') }}?v={{ filemtime(public_path('css/public-home.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/public-weather.css') }}?v={{ filemtime(public_path('css/public-weather.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/home-dashboard.css') }}?v={{ filemtime(public_path('css/home-dashboard.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/insights.css') }}?v={{ filemtime(public_path('css/insights.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/public-water.css') }}?v={{ filemtime(public_path('css/public-water.css')) }}">
    @if($risks->isNotEmpty())<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">@endif
@endpush

@section('content')
    @include('partials.public-home-nav')
    <header class="fh-hero">
        <div class="fh-container fh-hero-grid">
            <div class="fh-hero-copy" data-reveal>
                <div class="fh-eyebrow"><span class="fh-dot"></span> ศูนย์ข้อมูลและความช่วยเหลือ · {{ $province->fullName() }}</div>
                <div class="fh-hero-status"><span class="{{ $province->command_open ? 'is-open' : '' }}"><i class="bi bi-circle-fill" aria-hidden="true"></i> {{ $province->command_open ? 'ศูนย์สั่งการเปิดอยู่' : 'ข้อมูลสาธารณะประจำจังหวัด' }}</span><a href="{{ route('public.weather', $province) }}"><i class="bi bi-cloud-sun" aria-hidden="true"></i> เช็กฝนก่อนออกเดินทาง</a></div>
                <h1>รู้ทันสถานการณ์น้ำ<br><span>อุ่นใจ ใกล้ความช่วยเหลือ</span></h1>
                <p>ดูสถานการณ์ในพื้นที่ แจ้งระดับน้ำ และค้นหาศูนย์พักพิง<br class="fh-desktop-break"> ข้อมูลที่คุณส่ง ช่วยให้เราดูแลกันได้เร็วขึ้น</p>
                <div class="fh-hero-buttons">
                    <a href="{{ route('public.map', $province) }}" class="fh-button fh-button-orange"><i class="bi bi-map" aria-hidden="true"></i> ดูแผนที่สถานการณ์ <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                    @if($province->command_open && $province->web_help_open)
                        <a href="{{ route('public.help', $province) }}" class="fh-button fh-button-outline"><i class="bi bi-life-preserver" aria-hidden="true"></i> ขอความช่วยเหลือ</a>
                    @else
                        <a href="tel:{{ preg_replace('/\D+/', '', $hotline) }}" class="fh-button fh-button-outline"><i class="bi bi-telephone" aria-hidden="true"></i> สายด่วน {{ $hotline }}</a>
                    @endif
                </div>
                <div class="fh-hero-footnote"><i class="bi bi-shield-check" aria-hidden="true"></i> เหตุฉุกเฉินที่ต้องการความช่วยเหลือทันที โทร {{ $hotline }}</div>
            </div>
            @include('partials.public-home-art')
        </div>
        <div class="fh-container">
            @if($notice)
                <div class="fh-notice"><i class="bi bi-megaphone" aria-hidden="true"></i> {{ $notice }}</div>
            @endif
        </div>
        <div class="fh-waves" aria-hidden="true"><span></span><span></span></div>
    </header>

    <main class="fh-container fh-main" id="local-info">
        <div class="fh-section-heading" data-reveal><div><span class="fh-kicker">ข้อมูลใกล้ตัวคุณ</span><h2>สถานการณ์และบริการใน{{ $province->fullName() }}</h2></div><a href="{{ route('public.provinces') }}"><i class="bi bi-geo-alt" aria-hidden="true"></i> เปลี่ยนจังหวัด <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div>
        <div class="fh-services" data-reveal>
            <a href="{{ route('public.water-map', $province) }}"><span class="fh-service-icon cyan"><i class="bi bi-water" aria-hidden="true"></i></span><div><strong>ระดับน้ำสถานี</strong><small>ThaiWater · เทียบตลิ่ง</small></div><i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
            <a href="{{ route('public.map', $province) }}"><span class="fh-service-icon blue"><i class="bi bi-map" aria-hidden="true"></i></span><div><strong>แผนที่น้ำ</strong><small>ดูสถานการณ์ในพื้นที่</small></div><i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
            <a href="{{ $reportsOpen ? route('public.reports.create', $province) : route('public.track.lookup') }}"><span class="fh-service-icon cyan"><i class="bi bi-droplet-half" aria-hidden="true"></i></span><div><strong>{{ $reportsOpen ? 'แจ้งระดับน้ำ' : 'ติดตามคำขอ' }}</strong><small>{{ $reportsOpen ? 'แบ่งปันข้อมูลจากจุดที่คุณอยู่' : 'ตรวจสอบความคืบหน้า' }}</small></div><i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
            <a href="{{ route('public.shelters', $province) }}"><span class="fh-service-icon green"><i class="bi bi-house-heart" aria-hidden="true"></i></span><div><strong>ศูนย์พักพิง</strong><small>ค้นหาสถานที่ปลอดภัย</small></div><i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
            <a href="{{ route('public.weather', $province) }}"><span class="fh-service-icon violet"><i class="bi bi-cloud-sun" aria-hidden="true"></i></span><div><strong>พยากรณ์อากาศ</strong><small>ฝน ลม และเรดาร์ย้อนหลัง</small></div><i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
            <a href="#emergency-contacts"><span class="fh-service-icon orange"><i class="bi bi-telephone" aria-hidden="true"></i></span><div><strong>เบอร์ฉุกเฉิน</strong><small>ติดต่อความช่วยเหลือ</small></div><i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
        </div>
        <section class="ia-home-promo"><div><strong><i class="bi bi-house-heart me-2"></i>พื้นที่ของฉัน · รู้ทันน้ำและฝนใกล้บ้าน</strong><small>บันทึกพื้นที่ที่สนใจ ดูกราฟฝน ระดับน้ำ และรายงาน พร้อมตรวจความสดของข้อมูล</small></div><a href="{{ route('public.insights', $province) }}">เปิดภาพรวมและกราฟ <i class="bi bi-arrow-up-right"></i></a></section>
        <div class="fh-content-grid">
            @include('partials.nearby-flood')
            <section class="fh-water-promo"><span class="wm-kicker">รู้ทันระดับน้ำ</span><h2>เห็นทั้งจังหวัด<br>เข้าใจได้ในแผนที่เดียว</h2><p>สถานีตรวจวัด · ระดับน้ำเทียบตลิ่ง · รายงานในพื้นที่</p><a href="{{ route('public.water-map', $province) }}">เปิดแผนที่ระดับน้ำ {{ $province->name_th }} <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a><small class="wm-art-label">ภาพประกอบ</small></section>
            @foreach($alerts as $a)
                <div class="alert-bar shadow-sm" style="--ac:{{ $a->color() }}">
                    <i class="bi bi-{{ $a->icon() }} fs-5"></i>
                    <div class="min-w-0"><div class="fw-bold">{{ $a->levelLabel() }}: {{ $a->title }}</div>@if($a->body)<div class="small opacity-90">{{ $a->body }}</div>@endif</div>
                </div>
            @endforeach
            @include('partials.public-weather-card')
            <div class="card mb-3 fh-status-card" data-reveal>
                <div class="card-body">
                    <span class="fh-center-icon"><i class="bi bi-shield-check" aria-hidden="true"></i></span>
                    @if($province->command_open)
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="chip chip-dot chip-success">ศูนย์สั่งการเปิดอยู่</span>
                        </div>
                        <div class="text-muted small">
                            @if($province->web_help_open)
                                ติดอยู่ในน้ำ หรือต้องการอพยพ อาหาร ยา แจ้งศูนย์ได้ทันที
                            @else
                                ขณะนี้ยังไม่รับแจ้งขอความช่วยเหลือทางเว็บ หากต้องการความช่วยเหลือ โทรเบอร์ฉุกเฉินด้านล่าง
                            @endif
                        </div>
                        @if($province->web_help_open)
                            <a href="{{ route('public.help', $province) }}" class="btn btn-danger btn-lg w-100 mt-3"><i class="bi bi-life-preserver me-1"></i>ขอความช่วยเหลือ</a>
                            <a href="{{ route('public.track.lookup') }}" class="btn btn-light w-100 mt-2">ติดตามคำขอที่ส่งแล้ว</a>
                        @endif
                    @else
                        <div class="fw-600 mb-1">ยังไม่เปิดศูนย์สั่งการใน{{ $province->fullName() }}</div>
                        <div class="text-muted small">หากต้องการความช่วยเหลือ โทรเบอร์ฉุกเฉินด้านล่าง</div>
                        @if($centerContact)
                            <div class="small mt-2">หน่วยงานที่ต้องการเปิดศูนย์อำนวยการ ติดต่อ <b>{{ $centerContact }}</b></div>
                        @endif
                    @endif
                    <a class="fh-center-contact" href="tel:{{ preg_replace('/\D+/', '', $hotline) }}"><i class="bi bi-telephone" aria-hidden="true"></i> สายด่วน {{ $hotline }} <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                </div>
            </div>

            @if($recoveryOpen)
                <a href="{{ route('public.recovery', $province) }}" class="card card-lift text-reset mb-3" style="border-left:5px solid #0d9488">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="app-ico teal flex-shrink-0" style="width:44px;height:44px;border-radius:14px"><i class="bi bi-hammer"></i></span>
                        <div class="flex-grow-1"><div class="fw-bold">ขอรับความช่วยเหลือหลังน้ำลด</div><div class="small text-muted">บ้านหรือทรัพย์สินเสียหาย ยื่นคำร้องและติดตามสถานะออนไลน์</div></div>
                        <i class="bi bi-chevron-right text-muted"></i>
                    </div>
                </a>
            @endif

            @if($news->isNotEmpty())
                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-megaphone-fill text-primary"></i> ประกาศจากศูนย์ <a href="{{ route('public.news', $province) }}" class="ch-actions small">ทั้งหมด</a></div>
                    @foreach($news as $a)
                        <div class="list-row">
                            <i class="bi bi-{{ $a->icon() }} mt-1" style="color:{{ $a->color() }}"></i>
                            <div class="min-w-0">
                                <div class="fw-600">{{ $a->title }}</div>
                                <div class="small text-muted" style="white-space:pre-line">{{ \Illuminate\Support\Str::limit($a->body, 160) }}</div>
                                <div class="small text-muted">{{ thai_date($a->published_at, 'ago') }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-house-heart-fill text-success"></i> ศูนย์พักพิงที่เปิดรับ <a href="{{ route('public.shelters', $province) }}" class="ch-actions small">ทั้งหมด</a></div>
                @forelse($shelters as $sh)
                    <a href="{{ route('public.shelters', $province) }}" class="list-row text-reset text-decoration-none">
                        <div class="flex-grow-1 min-w-0"><div class="fw-600">{{ $sh->name }}</div><div class="small text-muted">{{ $sh->contact_phone }}</div></div>
                        <div class="text-end"><span class="chip chip-dot {{ $sh->statusChip() }}">{{ $sh->statusLabel() }}</span>@if($sh->capacity)<div class="small">ว่าง {{ number_format($sh->available()) }}</div>@endif</div>
                    </a>
                @empty
                    <div class="card-body small text-muted">ยังไม่มีศูนย์ที่เปิดรับ</div>
                @endforelse
                <div class="card-body pt-2 small"><a href="{{ route('public.find', $province) }}"><i class="bi bi-search me-1"></i>ค้นหาญาติในศูนย์พักพิง</a></div>
            </div>

            @if($lineOaId)
                <a href="https://line.me/R/ti/p/{{ urlencode(\Illuminate\Support\Str::start($lineOaId, '@')) }}" target="_blank" rel="noopener" class="btn btn-success w-100 mb-3"><i class="bi bi-line me-1"></i>เพิ่มเพื่อน LINE รับประกาศเตือนภัย</a>
            @endif

            @if($week->isNotEmpty())
                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-cloud-rain text-primary"></i> พยากรณ์ฝน 7 วัน</div>
                    <div class="card-body">@include('partials.forecast', ['week' => $week])</div>
                </div>
            @endif

            @if($stations->isNotEmpty())
                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-water text-primary"></i> ระดับน้ำที่สถานี
                        <a href="{{ route('public.map', $province) }}" class="ch-actions small">ดูบนแผนที่</a>
                    </div>
                    @foreach($stations as $st)
                        <div class="list-row">
                            <span class="st-gauge"><span style="height:{{ $st->fillPercent() ?? 0 }}%;background:{{ $st->color() }}"></span></span>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-600">{{ $st->name }}</div>
                                <div class="small text-muted">{{ $st->river ? 'ลำน้ำ'.$st->river.' · ' : '' }}{{ $st->last_at ? thai_date($st->last_at, 'ago') : 'ยังไม่มีข้อมูล' }}</div>
                            </div>
                            <div class="text-end">
                                <span class="chip" style="background:{{ $st->color() }};color:#fff">{{ $st->statusLabel() }}</span>
                                @if($st->toBank() !== null)<div class="small {{ $st->toBank() >= 0 ? 'text-danger' : 'text-muted' }}">{{ $st->toBank() >= 0 ? 'เกินตลิ่ง +' : 'ต่ำกว่าตลิ่ง ' }}{{ number_format(abs($st->toBank()), 2) }} ม.</div>@endif
                                @if($st->trendLabel())<div class="small text-muted">{{ $st->trendLabel() }}</div>@endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @php $threats = $risks->where('status', 'threatened'); @endphp
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-exclamation-triangle-fill text-warning"></i> จุดเสี่ยงที่ควรระวัง
                    @if($threats->isNotEmpty())<span class="chip chip-danger ms-1">เตือน {{ $threats->count() }} จุด</span>@endif
                </div>
                @if($risks->isEmpty())
                    <div class="card-body small text-muted">ยังไม่มีจุดเสี่ยงที่ประกาศ</div>
                @else
                    <div class="card-body p-2"><div id="pubRiskMap" class="map-box" style="height:260px"></div></div>
                    @foreach($threats->take(8) as $r)
                        <div class="list-row">
                            <span class="risk-marker threat flex-shrink-0" style="background:#dc2626"><i class="bi bi-{{ $r->icon() }}"></i></span>
                            <div class="min-w-0">
                                <div class="fw-600">{{ $r->name }}</div>
                                <div class="small text-muted">{{ $r->typeLabel() }}{{ $r->subdistrict ? ' · '.$r->subdistrict->shortName() : '' }}</div>
                                @if($r->description)<div class="small">{{ $r->description }}</div>@endif
                            </div>
                        </div>
                    @endforeach
                @endif
                <div class="card-body pt-2 small">
                    <a href="{{ route('public.risks.propose', $province) }}"><i class="bi bi-plus-circle me-1"></i>แจ้งจุดเสี่ยงที่เจ้าหน้าที่ควรรู้</a>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header" id="emergency-contacts"><i class="bi bi-telephone-fill text-danger"></i> เบอร์ฉุกเฉิน</div>
                @foreach($contacts as $c)
                    <a href="tel:{{ preg_replace('/\D+/', '', $c['phone']) }}" class="contact-row">
                        <span class="st-icon tint-danger d-grid" style="width:38px;height:38px;border-radius:12px;place-items:center"><i class="bi bi-telephone"></i></span>
                        <span class="small fw-600">{{ $c['label'] }}</span>
                        <span class="num mono">{{ phone_format($c['phone']) }}</span>
                    </a>
                @endforeach
            </div>

            @if($links->isNotEmpty())
                <div class="card fh-resources-card">
                    <div class="card-header"><i class="bi bi-water text-primary"></i> ดูข้อมูลน้ำเพิ่มเติม</div>
                    <div class="list-group list-group-flush" style="border-radius:0 0 16px 16px;overflow:hidden">
                        @foreach($links as $l)
                            <a href="{{ $l->url }}" target="_blank" rel="noopener" class="list-group-item list-group-item-action py-3">
                                <div class="fw-600">{{ $l->title }} <i class="bi bi-box-arrow-up-right small text-muted"></i></div>
                                <div class="small text-muted">{{ $l->description }}@if($l->source_name) · {{ $l->source_name }}@endif</div>
                            </a>
                        @endforeach
                    </div>
                    <div class="card-body small text-muted pt-2">เปิดในแท็บใหม่ ข้อมูลเป็นของเจ้าของเว็บแต่ละแห่ง</div>
                </div>
            @endif

        </div>
        <footer class="fh-footer">
            <span><i class="bi bi-droplet-fill" aria-hidden="true"></i> ศูนย์ช่วยเหลือน้ำท่วม · {{ $province->fullName() }}</span>
            <div>
                <a href="{{ route('public.privacy', $province) }}" class="text-muted">ประกาศความเป็นส่วนตัว</a> ·
                <a href="{{ route('public.open-data', $province) }}" class="text-muted">ข้อมูลเปิด</a> ·
                <a href="{{ route('login') }}" class="text-muted">เจ้าหน้าที่และทีมกู้ภัย เข้าสู่ระบบ</a>
            </div>
        </footer>
    </main>

    <a href="tel:{{ preg_replace('/\D+/', '', $hotline) }}" class="btn btn-danger call-fab"><i class="bi bi-telephone-fill me-1"></i>โทรกู้ภัย {{ $hotline }}</a>
@endsection

@push('scripts')
    <script src="{{ asset('js/nearby-flood.js') }}?v={{ filemtime(public_path('js/nearby-flood.js')) }}" defer></script>
    <script src="{{ asset('js/public-home.js') }}?v={{ filemtime(public_path('js/public-home.js')) }}" defer></script>
    <script src="{{ asset('js/public-weather.js') }}?v={{ filemtime(public_path('js/public-weather.js')) }}" defer></script>
    @if($risks->isNotEmpty())
        @php
            $riskGeo = ['type' => 'FeatureCollection', 'features' => $risks->map(fn ($r) => [
                'type' => 'Feature',
                'geometry' => $r->zone ? $r->zoneArray() : ['type' => 'Point', 'coordinates' => [$r->lng, $r->lat]],
                'properties' => ['id' => $r->id, 'name' => $r->name, 'type' => $r->typeLabel(), 'icon' => $r->icon(), 'color' => $r->color(), 'status' => $r->status, 'radius' => null, 'lat' => $r->lat, 'lng' => $r->lng, 'description' => $r->description],
            ])->values()];
        @endphp
        <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
        <script src="{{ asset('js/map.js') }}?v={{ filemtime(public_path('js/map.js')) }}"></script>
        <script src="{{ asset('js/risks.js') }}?v={{ filemtime(public_path('js/risks.js')) }}"></script>
        <script>
            (function () {
                const map = FloodMap.create(document.getElementById('pubRiskMap'), { center: [{{ $province->center_lat ?? 13.7563 }}, {{ $province->center_lng ?? 100.5018 }}], zoom: {{ $province->default_zoom ?? 9 }} });
                const g = FloodRisks.layer(map, @json($riskGeo));
                const pts = Object.values(g.byId).map(m => m.getLatLng());
                if (pts.length) map.fitBounds(L.latLngBounds(pts).pad(.2), { maxZoom: 14 });
            })();
        </script>
    @endif
@endpush
