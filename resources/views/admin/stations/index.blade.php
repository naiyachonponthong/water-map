@extends('layouts.app')
@section('title', 'สถานีวัดน้ำและพยากรณ์ฝน')

@push('head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
@endpush

@section('content')
    @use('App\Support\StationOptions')

    <x-page-head title="สถานีวัดน้ำและพยากรณ์ฝน" sub="สถานีที่เกินเกณฑ์จะประกาศเตือนภัยและส่งผลให้จุดเสี่ยงในรัศมีขึ้นเตือนอัตโนมัติ">
        <button class="btn btn-primary" data-form-modal="#stationModal" data-action="{{ route('stations.store') }}" data-method="POST" data-title="เพิ่มสถานี"><i class="bi bi-plus-lg me-1"></i>เพิ่มสถานี</button>
    </x-page-head>

    <div class="card mb-3">
        <div class="card-header"><i class="bi bi-cloud-rain text-primary"></i> พยากรณ์ฝน 7 วัน (ค่าสูงสุดในจังหวัด)
            <div class="ch-actions d-flex align-items-center gap-2">
                @if($forecastAt)<span class="small text-muted">อัปเดต {{ thai_date($forecastAt, 'ago') }}</span>@endif
                <form method="POST" action="{{ route('stations.forecast') }}">@csrf<button class="btn btn-sm btn-light" type="submit"><i class="bi bi-arrow-repeat"></i> ดึงใหม่</button></form>
            </div>
        </div>
        <div class="card-body">
            @include('partials.forecast', ['week' => $week])
            @if($byDistrict->isNotEmpty())
                <div class="table-responsive mt-3">
                    <table class="table table-sm small mb-0 align-middle">
                        <thead><tr><th>อำเภอ</th>@for($i = 0; $i <= 2; $i++)<th class="text-end">{{ $i === 0 ? 'วันนี้' : ($i === 1 ? 'พรุ่งนี้' : thai_date(today()->addDays($i))) }}</th>@endfor</tr></thead>
                        <tbody>
                            @foreach($districts as $d)
                                @continue(! $byDistrict->has($d->id))
                                <tr>
                                    <td>{{ $d->name_th }}</td>
                                    @for($i = 0; $i <= 2; $i++)
                                        @php $f = $byDistrict[$d->id]->first(fn ($r) => $r->date->isSameDay(today()->addDays($i))); $c = $f?->category(); @endphp
                                        <td class="text-end mono" style="{{ $c && $f->rain_mm >= 35 ? 'color:'.$c['color'].';font-weight:700' : '' }}">{{ $f ? number_format($f->rain_mm, 0) : '-' }}</td>
                                    @endfor
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            <div class="form-text mt-2">ข้อมูลจาก Open-Meteo ดึงทุกชั่วโมง ฝนเกิน {{ setting('rain_warning_mm') }} มม./วัน ประกาศเตือนภัย เกิน {{ setting('rain_critical_mm') }} มม. ประกาศวิกฤต (ตั้งค่าได้ในหน้าตั้งค่า)</div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="{{ route('stations.index') }}" class="chip {{ request('status') ? '' : 'chip-primary' }}">ทั้งหมด {{ $counts->sum() }}</a>
        @foreach(['critical', 'warning', 'watch', 'normal', 'offline'] as $st)
            @if($counts[$st] ?? 0)
                <a href="{{ route('stations.index', ['status' => $st]) }}" class="chip {{ request('status') === $st ? 'chip-primary' : '' }}"><span class="lv-dot" style="background:{{ StationOptions::STATUS[$st][2] }};width:8px;height:8px"></span> {{ StationOptions::STATUS[$st][0] }} {{ $counts[$st] }}</a>
            @endif
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-xl-6">
            <div class="card">
                @forelse($stations as $s)
                    <a href="{{ route('stations.show', $s) }}" class="list-row text-reset text-decoration-none {{ $s->is_active ? '' : 'opacity-50' }}">
                        <span class="st-gauge"><span style="height:{{ $s->fillPercent() ?? 0 }}%;background:{{ $s->color() }}"></span></span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="d-flex flex-wrap align-items-center gap-1">
                                <span class="fw-600 me-1">{{ $s->name }}</span>
                                <span class="chip chip-dot {{ $s->statusChip() }}" style="{{ $s->statusChip() ? '' : 'color:'.$s->color() }}">{{ $s->statusLabel() }}</span>
                                @if($s->fetch_mode === 'json')<span class="chip" title="ดึงอัตโนมัติ"><i class="bi bi-cloud-download"></i></span>@endif
                                @if($s->fetch_error)<span class="chip chip-danger" title="{{ $s->fetch_error }}"><i class="bi bi-exclamation-circle"></i> ดึงไม่ได้</span>@endif
                                @unless($s->is_public)<span class="chip"><i class="bi bi-eye-slash"></i></span>@endunless
                            </div>
                            <div class="small text-muted">{{ $s->river ? 'ลำน้ำ'.$s->river.' · ' : '' }}{{ $s->district ? 'อ.'.$s->district->name_th : '' }}{{ $s->last_at ? ' · '.thai_date($s->last_at, 'ago') : '' }}</div>
                        </div>
                        <div class="text-end flex-shrink-0">
                            @if($s->last_value !== null)
                                <div class="fw-bold mono">{{ number_format($s->last_value, 2) }} <small class="text-muted fw-normal">{{ $s->unit }}</small></div>
                                @if($s->toBank() !== null)<div class="small {{ $s->toBank() >= 0 ? 'text-danger' : 'text-muted' }}">{{ $s->toBank() >= 0 ? 'เกินตลิ่ง +' : 'ต่ำกว่าตลิ่ง ' }}{{ number_format(abs($s->toBank()), 2) }}</div>@endif
                                @if($s->trendLabel())<div class="small {{ $s->trend_per_hour > 0 ? 'text-warning' : 'text-muted' }}">{{ $s->trendLabel() }}</div>@endif
                            @else
                                <span class="small text-muted">ยังไม่มีค่า</span>
                            @endif
                        </div>
                    </a>
                @empty
                    <x-empty icon="water" title="ยังไม่มีสถานีวัดน้ำ" text="เพิ่มสถานีของกรมชลประทาน สทนช. หรือหลักวัดของ อปท. บันทึกค่าเอง นำเข้าไฟล์ หรือดึงจาก API อัตโนมัติ" />
                @endforelse
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card" style="position:sticky;top:calc(var(--sb-topbar) + 1rem)">
                <div class="card-body p-2"><div id="stationMap" class="map-box" style="height:520px"></div></div>
            </div>
        </div>
    </div>

    @include('admin.stations._form')
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="{{ asset('js/map.js') }}?v={{ filemtime(public_path('js/map.js')) }}"></script>
    <script src="{{ asset('js/stations.js') }}?v={{ filemtime(public_path('js/stations.js')) }}"></script>
    <script>
        (function () {
            const map = FloodMap.create(document.getElementById('stationMap'), {
                center: [{{ $province->center_lat ?? 13.7563 }}, {{ $province->center_lng ?? 100.5018 }}], zoom: {{ $province->default_zoom ?? 10 }},
                areasUrl: @json(route('dashboard.areas')) + '?level=district', fit: {{ $stations->isEmpty() ? 'true' : 'false' }},
            });
            fetch(@json(route('stations.geojson')), { headers: { Accept: 'application/json' } }).then(r => r.json()).then(d => {
                const g = FloodStations.layer(map, d, { link: id => @json(url('admin/stations')) + '/' + id });
                const pts = g.getLayers().map(l => l.getLatLng());
                if (pts.length) map.fitBounds(L.latLngBounds(pts).pad(.2), { maxZoom: 13 });
            });
        })();
    </script>
@endpush
