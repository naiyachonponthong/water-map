@extends('layouts.app')
@section('title', 'ศูนย์สั่งการ')

@push('head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
@endpush

@section('content')
    @php
        $done = collect($checklist)->where('done', true)->count();
        $total = count($checklist);
        $pct = $total ? (int) round($done / $total * 100) : 0;
        $s = $snap['stats'];
        $initial = [
            'cases' => $snap['cases'],
            'teams' => $snap['teams'],
            'risks' => $snap['risks'],
            'reports' => $snap['reports'],
            'stations' => $snap['stations'],
        ];
        $tiles = [
            ['key' => 'triage', 'label' => 'รอคัดกรอง', 'icon' => 'bell', 'tint' => 'danger', 'url' => route('cases.index', ['tab' => 'triage'])],
            ['key' => 'queued', 'label' => 'รอทีม', 'icon' => 'hourglass-split', 'tint' => 'warning', 'url' => route('cases.index', ['tab' => 'queued'])],
            ['key' => 'active', 'label' => 'กำลังช่วย', 'icon' => 'life-preserver', 'tint' => 'primary', 'url' => route('cases.index', ['tab' => 'active'])],
            ['key' => 'rescued_today', 'label' => 'ช่วยแล้ววันนี้', 'icon' => 'check-circle', 'tint' => 'success', 'url' => route('cases.index', ['tab' => 'done'])],
        ];
    @endphp

    @role('super-admin')
        <div class="alert alert-info d-flex flex-wrap align-items-center gap-2">
            <span class="me-auto">เตรียมจังหวัดให้พร้อมก่อนเปิดรับเหตุจริง</span>
            <a class="btn btn-sm btn-primary" href="{{ route('admin.setup') }}">เปิดตัวช่วยตั้งค่าเริ่มต้น <i class="bi bi-arrow-right"></i></a>
        </div>
    @endrole
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <div class="me-auto">
            <h1 class="h4 fw-bold mb-0" style="letter-spacing:-.02em">ศูนย์สั่งการ{{ $province->fullName() }}</h1>
            <div class="small text-muted">
                <span class="live-dot" id="liveDot"></span> <span id="liveText">กำลังเชื่อมต่อ</span>
                · อัปเดต <span id="liveTime">{{ $snap['time'] }}</span> น.
                @if($pendingUsers)· <a href="{{ route('admin.users.index', ['status' => 'pending']) }}" class="text-warning fw-600">บัญชีรออนุมัติ {{ $pendingUsers }}</a>@endif
            </div>
        </div>
        <button class="btn btn-light btn-sm" type="button" id="soundBtn"><i class="bi bi-volume-mute"></i> เปิดเสียงเตือน</button>
        <a href="{{ route('live.tv') }}" class="btn btn-soft btn-sm" target="_blank"><i class="bi bi-tv me-1"></i>โหมดทีวี</a>
        @can('dispatch.manage')<a href="{{ route('dispatch.index') }}" class="btn btn-primary btn-sm"><i class="bi bi-kanban me-1"></i>สั่งการทีม</a>@endcan
    </div>

    @unless($province->command_open)
        <div class="alert alert-warning py-2"><i class="bi bi-info-circle"></i>
            <div class="small">ศูนย์สั่งการยังไม่เปิด ความพร้อม {{ $pct }}% ({{ $done }}/{{ $total }}) @can('settings.manage')<a href="{{ route('admin.settings.index', ['tab' => 'switches']) }}">สวิตช์ศูนย์</a>@endcan</div>
        </div>
    @endunless

    <div id="liveSos">{!! $snap['sos_html'] !!}</div>

    <div id="liveAlerts">{!! $snap['alerts_html'] !!}</div>
    <div class="alert alert-danger d-none mb-3" id="criticalBar">
        <i class="bi bi-exclamation-octagon-fill"></i>
        <div class="flex-grow-1"><b>เคสวิกฤตเข้าใหม่</b> <span id="criticalCodes"></span></div>
        <a href="{{ route('cases.index', ['tab' => 'triage', 'priority' => 'critical']) }}" class="btn btn-sm btn-danger">ดูเคส</a>
    </div>

    <div class="row g-3 mb-3">
        @foreach($tiles as $t)
            <div class="col-6 col-xl-3">
                <a href="{{ $t['url'] }}" class="card card-lift text-reset h-100">
                    <div class="card-body stat">
                        <span class="st-icon tint-{{ $t['tint'] }}"><i class="bi bi-{{ $t['icon'] }}"></i></span>
                        <div>
                            <div class="st-num mono" data-stat="{{ $t['key'] }}">{{ number_format($s[$t['key']]) }}</div>
                            <div class="st-label">{{ $t['label'] }}
                                @if($t['key'] === 'rescued_today')<span class="text-success">· <span data-stat="people_today">{{ number_format($s['people_today']) }}</span> คน</span>@endif
                                @if($t['key'] === 'queued')<span class="text-danger d-block small" data-wait>{{ $s['longest_wait'] ? 'นานสุด '.$s['longest_wait'].' นาที' : '' }}</span>@endif
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="dash-grid">
        <div class="dash-side">
            <div class="card">
                <div class="card-header"><i class="bi bi-lightning-charge text-danger"></i> คิวเคสด่วน
                    <span class="ch-actions small"><span class="chip chip-danger">วิกฤต <span data-stat="critical_open">{{ $s['critical_open'] }}</span></span></span>
                </div>
                <div id="liveUrgent">{!! $snap['urgent_html'] !!}</div>
            </div>
            <div class="card">
                <div class="card-header"><i class="bi bi-people text-primary"></i> ทีม</div>
                <div class="card-body d-flex gap-3">
                    <div><div class="fs-4 fw-bold text-success mono" data-stat="teams_available">{{ $s['teams_available'] }}</div><div class="small text-muted">พร้อม</div></div>
                    <div><div class="fs-4 fw-bold text-primary mono" data-stat="teams_busy">{{ $s['teams_busy'] }}</div><div class="small text-muted">ติดภารกิจ</div></div>
                    @can('teams.manage')<a href="{{ route('teams.index') }}" class="ms-auto align-self-center small">จัดการทีม</a>@endcan
                </div>
            </div>
            <div class="card">
                <div class="card-header"><i class="bi bi-house-heart text-teal"></i> ศูนย์พักพิง</div>
                <div class="card-body d-flex gap-3">
                    <div><div class="fs-4 fw-bold mono" style="color:#0d9488" data-stat="evacuees_in">{{ $s['evacuees_in'] }}</div><div class="small text-muted">คนในศูนย์</div></div>
                    <div><div class="fs-4 fw-bold mono" data-stat="shelters_open">{{ $s['shelters_open'] }}</div><div class="small text-muted">ศูนย์เปิดรับ</div></div>
                    @canany(['shelters.manage', 'evacuees.manage'])<a href="{{ route('shelters.index') }}" class="ms-auto align-self-center small">จัดการศูนย์</a>@endcanany
                </div>
            </div>
            @can('risks.manage')
                <div class="card">
                    <div class="card-header"><i class="bi bi-exclamation-triangle text-warning"></i> จุดเสี่ยงและครัวเรือนเปราะบาง</div>
                    <div class="card-body d-flex gap-3">
                        <a href="{{ route('risks.index', ['tab' => 'threatened']) }}" class="text-reset"><div class="fs-4 fw-bold text-danger mono" data-stat="risks_threatened">{{ $s['risks_threatened'] }}</div><div class="small text-muted">จุดกำลังเตือน</div></a>
                        <a href="{{ route('cases.index', ['source' => 'proactive', 'tab' => 'queued']) }}" class="text-reset"><div class="fs-4 fw-bold text-warning mono" data-stat="proactive_open">{{ $s['proactive_open'] }}</div><div class="small text-muted">เคสตรวจเยี่ยมเปิดอยู่</div></a>
                        @can('reports.moderate')
                            <a href="{{ route('reports.index', ['tab' => 'live']) }}" class="text-reset"><div class="fs-4 fw-bold text-primary mono" data-stat="reports_live">{{ $s['reports_live'] }}</div><div class="small text-muted">รายงานน้ำ</div></a>
                        @endcan
                        <a href="{{ route('risks.index') }}" class="ms-auto align-self-center small">ประกาศระดับน้ำ</a>
                    </div>
                </div>
            @endcan
            @if($pct < 100)
                <div class="card">
                    <div class="card-header"><i class="bi bi-clipboard-check text-primary"></i> ความพร้อมเปิดศูนย์ <span class="ch-actions small text-muted">{{ $done }}/{{ $total }}</span></div>
                    <div class="card-body pt-1">
                        @foreach($checklist as $c)
                            <a href="{{ $c['url'] }}" class="check-row {{ $c['done'] ? 'done' : 'todo' }}">
                                <span class="cr-ico"><i class="bi bi-{{ $c['done'] ? 'check-lg' : 'exclamation-lg' }}"></i></span>
                                <span class="small">{{ $c['label'] }}</span>
                                @isset($c['detail'])<span class="cr-detail mono">{{ $c['detail'] }}</span>@endisset
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="card">
            <div class="card-header">
                <i class="bi bi-map text-primary"></i> แผนที่สด
                <div class="ch-actions small d-flex gap-2 flex-wrap">
                    @foreach(\App\Support\CaseOptions::PRIORITIES as [$l, , $color])<span><span class="lv-dot" style="background:{{ $color }};width:9px;height:9px"></span> {{ $l }}</span>@endforeach
                    <span><i class="bi bi-truck text-primary"></i> ทีม</span>
                </div>
            </div>
            <div class="card-body p-2"><div id="dashMap" class="map-box"></div></div>
        </div>

        <div class="dash-side dash-right">
            <div class="card">
                <div class="card-header"><i class="bi bi-activity text-primary"></i> ความเคลื่อนไหวล่าสุด</div>
                <div class="card-body py-1" id="liveFeed" style="max-height:640px;overflow:auto">{!! $snap['feed_html'] !!}</div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="{{ asset('js/map.js') }}?v={{ filemtime(public_path('js/map.js')) }}"></script>
    <script src="{{ asset('js/risks.js') }}?v={{ filemtime(public_path('js/risks.js')) }}"></script>
    <script src="{{ asset('js/stations.js') }}?v={{ filemtime(public_path('js/stations.js')) }}"></script>
    <script>
        window.LIVE_CFG = {
            snapshotUrl: @json(route('live.snapshot')),
            provinceId: {{ $province->id }},
            lastEventId: {{ $snap['last_event_id'] }},
            sosOpen: @json($snap['sos_open']),
        alertsUnack: @json($snap['alerts_unack']),
            center: [{{ $province->center_lat ?? 13.7563 }}, {{ $province->center_lng ?? 100.5018 }}],
            zoom: {{ $province->default_zoom ?? 10 }},
            areasUrl: @json(route('dashboard.areas')) + '?level=district',
            initial: @json($initial),
        };
    </script>
    <script src="{{ asset('js/command-center.js') }}?v={{ filemtime(public_path('js/command-center.js')) }}"></script>
@endpush
