<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0e2233">
    <meta name="robots" content="noindex">
    <title>ทีมกู้ภัย | ศูนย์ช่วยเหลือน้ำท่วม</title>
    @include('partials.head')
    <link rel="manifest" href="{{ asset('field-manifest.webmanifest') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
    <link href="{{ asset('css/field.css') }}?v={{ filemtime(public_path('css/field.css')) }}" rel="stylesheet">
</head>
<body class="field">
@if(! $team)
    <div class="f-empty">
        <div class="em-ico mx-auto mb-3" style="width:72px;height:72px;border-radius:22px;background:#1a2d42;display:grid;place-items:center;font-size:2rem"><i class="bi bi-people"></i></div>
        <h1 class="h5 fw-bold">คุณยังไม่ได้อยู่ในทีม</h1>
        <p class="text-white-50 small">ติดต่อเจ้าหน้าที่ศูนย์ให้เพิ่มคุณเข้าทีม แล้วเปิดหน้านี้อีกครั้ง</p>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-light">ออกจากระบบ</button></form>
    </div>
@else
    <header class="f-head">
        <div class="min-w-0">
            <div class="f-team text-truncate" id="fTeam">{{ $team->name }}</div>
            <button class="f-status" id="fStatus" type="button" style="--c:{{ $team->statusColor() }}"><span class="dot"></span><span id="fStatusText">{{ $team->statusLabel() }}</span> <i class="bi bi-chevron-down small"></i></button>
        </div>
        <div class="f-net ms-auto" id="fNet"><i class="bi bi-wifi"></i> <span>ออนไลน์</span></div>
    </header>

    <div class="f-banner d-none" id="fSosBanner"></div>
    <div class="f-banner warn d-none" id="fQueueBanner"></div>

    <main class="f-main">
        <section class="f-tab" data-tab="jobs" id="tabJobs"></section>
        <section class="f-tab d-none" data-tab="near" id="tabNear"></section>
        <section class="f-tab d-none" data-tab="map"><div id="fMap" class="f-map"></div><div id="fMapLegend" class="f-legend"></div></section>
        <section class="f-tab d-none" data-tab="team" id="tabTeam"></section>
        <section class="f-tab d-none" data-tab="sos" id="tabSos">
            <div class="sos-wrap">
                <div class="small text-white-50 mb-2">กดค้าง 2 วินาที เพื่อขอความช่วยเหลือจากศูนย์</div>
                <div class="sos-kinds" id="sosKinds">
                    @foreach($options['sos'] as $k => $label)
                        <label><input type="radio" name="sosKind" value="{{ $k }}" @checked($loop->first)><span>{{ $label }}</span></label>
                    @endforeach
                </div>
                <button class="sos-btn" id="sosBtn" type="button"><svg viewBox="0 0 120 120"><circle cx="60" cy="60" r="56" id="sosRing"/></svg><span>SOS</span></button>
                <input class="form-control mt-3" id="sosNote" maxlength="300" placeholder="รายละเอียดสั้นๆ (ไม่บังคับ)">
                <div id="sosState" class="mt-3"></div>
                <a class="btn btn-outline-light w-100 mt-3" id="sosCall" href="tel:1784"><i class="bi bi-telephone-fill"></i> โทรสายด่วน</a>
            </div>
        </section>
    </main>

    <nav class="f-nav">
        <button data-go="jobs" class="active"><i class="bi bi-list-check"></i><span>งานของฉัน</span><b class="f-badge d-none" id="badgeJobs"></b></button>
        <button data-go="near"><i class="bi bi-geo"></i><span>รอบตัวฉัน</span></button>
        <button data-go="map"><i class="bi bi-map"></i><span>แผนที่</span></button>
        <button data-go="team"><i class="bi bi-people"></i><span>ทีม</span></button>
        <button data-go="sos" class="sos"><i class="bi bi-exclamation-octagon-fill"></i><span>SOS</span></button>
    </nav>

    {{-- แผ่นล่างใช้ซ้ำ: เลือกเหตุผล ปิดงาน ระดับน้ำ บันทึก สถานะทีม --}}
    <div class="modal fade" id="sheet" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
            <div class="modal-content f-sheet">
                <div class="modal-header"><h5 class="modal-title" id="sheetTitle"></h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
                <div class="modal-body" id="sheetBody"></div>
                <div class="modal-footer"><button class="btn btn-primary btn-lg w-100" id="sheetOk" type="button">ยืนยัน</button></div>
            </div>
        </div>
    </div>

    <div class="flash-wrap" id="fToast"></div>

    <script>
        window.FIELD = {
            teamId: {{ $team->id }},
            stateUrl: @json(route('field.state')),
            actionUrl: @json(route('field.action')),
            locationUrl: @json(route('field.location')),
            photoUrl: @json(route('field.photo')),
            desktopUrl: @json(route('my-team.index')),
            logoutUrl: @json(route('logout')),
            options: @json($options),
            userName: @json(auth()->user()->name),
        };
    </script>
@endif

@include('partials.scripts')
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="{{ asset('js/field.js') }}?v={{ filemtime(public_path('js/field.js')) }}"></script>
<script>
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/field-sw.js', { scope: '/field' }).catch(() => {});
    }
</script>
</body>
</html>
