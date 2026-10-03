@extends('layouts.app')
@section('title', 'เคสขอความช่วยเหลือ')

@push('head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
@endpush

@section('content')
    @use('App\Support\CaseOptions')

    <x-page-head title="เคสขอความช่วยเหลือ" sub="เรียงตามความเร่งด่วน คะแนนสูงอยู่บน เคสที่รอนานจะได้คะแนนเพิ่มทุกชั่วโมง">
        @can('cases.manage')
            <a href="{{ route('cases.create') }}" class="btn btn-primary"><i class="bi bi-telephone-plus me-1"></i>รับแจ้งทางโทรศัพท์</a>
        @endcan
    </x-page-head>

    @unless($province->web_help_open)
        <div class="alert alert-warning"><i class="bi bi-info-circle"></i>
            <div class="small">ยังปิดรับแจ้งทางเว็บอยู่ ประชาชนจะเห็นแต่เบอร์ฉุกเฉิน เจ้าหน้าที่ยังคีย์เคสจากโทรศัพท์ได้ตามปกติ
                @can('settings.manage')<a href="{{ route('admin.settings.index', ['tab' => 'switches']) }}">เปิดรับแจ้ง</a>@endcan
            </div>
        </div>
    @endunless

    <div class="new-case-bar d-none mb-2" id="newCaseBar">
        <a href="{{ route('cases.index', ['tab' => 'triage']) }}" class="alert alert-danger mb-0 shadow-sm text-decoration-none">
            <i class="bi bi-bell-fill"></i><div class="flex-grow-1"><b id="newCaseText">มีเคสใหม่</b><div class="small" id="newCaseList"></div></div>
            <span class="btn btn-sm btn-danger">ดูเคส</span>
        </a>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <ul class="nav nav-pills flex-nowrap overflow-auto" style="scrollbar-width:none">
            @foreach(CaseOptions::TABS as $key => [$label])
                <li class="nav-item">
                    <a class="nav-link text-nowrap {{ $tab === $key ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['tab' => $key, 'page' => null]) }}">
                        {{ $label }}<span class="count">{{ $counts[$key] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
        <form class="ms-lg-auto d-flex flex-wrap gap-2" method="GET">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <select name="district" class="form-select form-select-sm" style="width:150px" onchange="this.form.submit()">
                <option value="">ทุกอำเภอ</option>
                @foreach($districts as $d)<option value="{{ $d->id }}" @selected(request('district') == $d->id)>{{ $d->name_th }}</option>@endforeach
            </select>
            <select name="priority" class="form-select form-select-sm" style="width:130px" onchange="this.form.submit()">
                <option value="">ทุกระดับ</option>
                @foreach(CaseOptions::PRIORITIES as $k => [$l])<option value="{{ $k }}" @selected(request('priority') === $k)>{{ $l }}</option>@endforeach
            </select>
            <div class="input-group input-group-sm" style="width:220px">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="search" name="q" class="form-control" placeholder="เลขเคส ชื่อ เบอร์" value="{{ request('q') }}">
            </div>
        </form>
    </div>

    <div class="row g-3">
        <div class="col-xl-7">
            <div class="card">
                @forelse($cases as $c)
                    <a href="{{ route('cases.show', $c) }}" class="case-row" data-case-id="{{ $c->id }}">
                        <span class="prio-bar" style="background:{{ $c->priorityColor() }}"></span>
                        <div class="cr-main">
                            <div class="d-flex flex-wrap align-items-center gap-1">
                                <span class="mono small text-muted me-1">{{ $c->code }}</span>
                                <span class="fw-600 me-1">{{ $c->requester_name }}</span>
                                <span class="chip {{ $c->statusChip() }}">{{ $c->statusLabel() }}</span>
                                @if($c->possible_duplicate_of_id)<span class="chip chip-warning" title="อาจซ้ำกับ {{ $c->possibleDuplicateOf?->code }}"><i class="bi bi-intersect"></i> อาจซ้ำ</span>@endif
                                @if($c->outside_province)<span class="chip chip-warning"><i class="bi bi-geo"></i> นอกจังหวัด</span>@endif
                                @if($c->report_count > 1)<span class="chip">แจ้ง {{ $c->report_count }} ครั้ง</span>@endif
                            </div>
                            <div class="cr-meta">
                                <span><span class="lv-dot" style="background:{{ config('floodthai.water_levels.'.$c->water_level.'.color') }};width:9px;height:9px"></span> น้ำ{{ $c->waterLabel() }}</span>
                                <span><i class="bi bi-people"></i> {{ $c->people_count }} คน</span>
                                <span><i class="bi bi-geo-alt"></i> {{ $c->areaLabel() }}</span>
                                @foreach(($c->vulnerable ?? []) as $v)
                                    @continue($v === 'pets')
                                    <span class="text-danger" title="{{ CaseOptions::VULNERABLE[$v][0] ?? '' }}"><i class="bi bi-{{ CaseOptions::VULNERABLE[$v][1] ?? 'dot' }}"></i></span>
                                @endforeach
                                <span><i class="bi bi-{{ CaseOptions::SOURCES[$c->source][1] ?? 'globe2' }}"></i></span>
                            </div>
                        </div>
                        <div class="cr-right">
                            <span class="score-pill" style="background:{{ $c->priorityColor() }}">{{ $c->priorityLabel() }} {{ $c->priority_score }}</span>
                            <span class="small text-muted">{{ $c->isOpen() ? 'รอ '.$c->waitingLabel() : $c->createdLabel() }}</span>
                        </div>
                    </a>
                @empty
                    <x-empty icon="life-preserver" title="ไม่มีเคสในหมวดนี้" text="{{ $tab === 'triage' ? 'เคสใหม่จะแสดงที่นี่ทันทีที่มีคนแจ้ง' : 'ลองเปลี่ยนแท็บหรือตัวกรอง' }}" />
                @endforelse
            </div>
            <div class="mt-3">{{ $cases->links() }}</div>
        </div>
        <div class="col-xl-5">
            <div class="card" style="position:sticky;top:calc(var(--sb-topbar) + 1rem)">
                <div class="card-header"><i class="bi bi-map text-primary"></i> เคสที่ยังเปิดอยู่
                    <div class="ch-actions small d-flex gap-2">
                        @foreach(CaseOptions::PRIORITIES as [$l, , $color])<span><span class="lv-dot" style="background:{{ $color }};width:9px;height:9px"></span> {{ $l }}</span>@endforeach
                    </div>
                </div>
                <div class="card-body p-2"><div id="caseMap" class="map-box" style="height:520px"></div></div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="{{ asset('js/map.js') }}?v={{ filemtime(public_path('js/map.js')) }}"></script>
    <script src="{{ asset('js/cases.js') }}?v={{ filemtime(public_path('js/cases.js')) }}"></script>
    <script>
        FloodCases.map(document.getElementById('caseMap'), {
            center: [{{ $province->center_lat ?? 13.7563 }}, {{ $province->center_lng ?? 100.5018 }}],
            zoom: {{ $province->default_zoom ?? 10 }},
            areasUrl: @json(route('dashboard.areas')) + '?level=district',
            casesUrl: @json(route('cases.geojson')),
        });
        FloodCases.poll(@json(route('cases.poll')), {{ $latestId }}, {{ $province->id }});
    </script>
@endpush
