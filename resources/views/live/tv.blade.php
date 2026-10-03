<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>ห้องสั่งการ {{ $province->name_th }}</title>
    @include('partials.head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
</head>
<body class="tv">
@php
    $s = $snapshot['stats'];
    $initial = [
        'cases' => $snapshot['cases'],
        'teams' => $snapshot['teams'],
        'risks' => $snapshot['risks'],
        'reports' => $snapshot['reports'],
        'stations' => $snapshot['stations'],
    ];
@endphp
<header class="tv-head">
    <span class="app-ico primary" style="width:44px;height:44px;border-radius:14px;font-size:1.3rem"><i class="bi bi-droplet-fill"></i></span>
    <div>
        <div class="tv-title">ศูนย์สั่งการน้ำท่วม{{ $province->fullName() }}</div>
        <div class="tv-sub"><span class="live-dot" id="liveDot"></span> <span id="liveText">กำลังเชื่อมต่อ</span> · อัปเดต <span id="liveTime">{{ $snapshot['time'] }}</span> น.</div>
    </div>
    <div class="ms-auto d-flex align-items-center gap-2">
        <button class="btn btn-sm btn-dark" id="soundBtn" type="button"></button>
        <button class="btn btn-sm btn-dark" id="fullscreenBtn" type="button"><i class="bi bi-arrows-fullscreen"></i></button>
        <div class="tv-clock mono" id="tvClock">{{ now()->format('H:i:s') }}</div>
    </div>
</header>

<div class="mx-3" id="liveAlerts">{!! $snapshot['alerts_html'] !!}</div>
<div class="alert alert-danger d-none mx-3 mb-2" id="criticalBar">
    <i class="bi bi-exclamation-octagon-fill"></i><div class="flex-grow-1 fs-5"><b>เคสวิกฤตเข้าใหม่</b> <span id="criticalCodes"></span></div>
</div>

<div class="mx-3" id="liveSos">{!! $snapshot['sos_html'] !!}</div>

<main class="tv-grid">
    <section class="tv-map"><div id="dashMap"></div></section>
    <aside class="tv-side">
        <div class="tv-stats">
            <div class="tv-stat danger"><div class="v mono" data-stat="triage">{{ $s['triage'] }}</div><div class="l">รอคัดกรอง</div></div>
            <div class="tv-stat warning"><div class="v mono" data-stat="queued">{{ $s['queued'] }}</div><div class="l">รอทีม <span data-wait>{{ $s['longest_wait'] ? 'นานสุด '.$s['longest_wait'].' นาที' : '' }}</span></div></div>
            <div class="tv-stat primary"><div class="v mono" data-stat="active">{{ $s['active'] }}</div><div class="l">กำลังช่วย</div></div>
            <div class="tv-stat success"><div class="v mono" data-stat="rescued_today">{{ $s['rescued_today'] }}</div><div class="l">ช่วยแล้ววันนี้ · <span data-stat="people_today">{{ $s['people_today'] }}</span> คน</div></div>
        </div>
        <div class="tv-teams">
            <span><b class="text-success mono" data-stat="teams_available">{{ $s['teams_available'] }}</b> ทีมพร้อม</span>
            <span><b class="mono" style="color:#60a5fa" data-stat="teams_busy">{{ $s['teams_busy'] }}</b> ติดภารกิจ</span>
            <span><b class="text-danger mono" data-stat="critical_open">{{ $s['critical_open'] }}</b> เคสวิกฤต</span>
            <span><b class="text-warning mono" data-stat="risks_threatened">{{ $s['risks_threatened'] }}</b> จุดเสี่ยงเตือน</span>
            <span><b class="mono" style="color:#5eead4" data-stat="evacuees_in">{{ $s['evacuees_in'] }}</b> คนในศูนย์พักพิง</span>
            <span><b class="mono" style="color:#fb923c" data-stat="stations_alarm">{{ $s['stations_alarm'] }}</b> สถานีเกินเกณฑ์</span>
            <span><b class="mono" style="color:#93c5fd" data-stat="reports_live">{{ $s['reports_live'] }}</b> รายงานน้ำ</span>
            <span><b class="mono" style="color:#fbbf24" data-stat="proactive_open">{{ $s['proactive_open'] }}</b> ตรวจเยี่ยมเปราะบาง</span>
        </div>
        <div class="tv-panel">
            <div class="tv-panel-h"><i class="bi bi-lightning-charge-fill text-danger"></i> คิวเคสด่วน</div>
            <div id="liveUrgent">{!! $snapshot['urgent_html'] !!}</div>
        </div>
        <div class="tv-panel grow">
            <div class="tv-panel-h"><i class="bi bi-activity"></i> ความเคลื่อนไหว</div>
            <div id="liveFeed">{!! $snapshot['feed_html'] !!}</div>
        </div>
    </aside>
</main>

@include('partials.scripts')
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="{{ asset('js/map.js') }}?v={{ filemtime(public_path('js/map.js')) }}"></script>
    <script src="{{ asset('js/risks.js') }}?v={{ filemtime(public_path('js/risks.js')) }}"></script>
    <script src="{{ asset('js/stations.js') }}?v={{ filemtime(public_path('js/stations.js')) }}"></script>
<script>
    window.LIVE_CFG = {
        snapshotUrl: @json(route('live.snapshot', ['tv' => 1])),
        provinceId: {{ $province->id }},
        lastEventId: {{ $snapshot['last_event_id'] }},
        sosOpen: @json($snapshot['sos_open']),
        alertsUnack: @json($snapshot['alerts_unack']),
        center: [{{ $province->center_lat ?? 13.7563 }}, {{ $province->center_lng ?? 100.5018 }}],
        zoom: {{ $province->default_zoom ?? 10 }},
        areasUrl: @json(route('dashboard.areas')) + '?level=district',
        initial: @json($initial),
    };
</script>
<script src="{{ asset('js/command-center.js') }}?v={{ filemtime(public_path('js/command-center.js')) }}"></script>
</body>
</html>
