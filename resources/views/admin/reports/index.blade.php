@extends('layouts.app')
@section('title', 'รายงานระดับน้ำ')

@push('head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
@endpush

@section('content')
    @use('App\Support\ReportOptions')
    @use('App\Http\Controllers\Admin\WaterReportController')

    <x-page-head title="รายงานระดับน้ำ" sub="รายงานที่มีคนในพื้นที่ยืนยัน หรือเจ้าหน้าที่ยืนยันแล้ว จะถูกใช้เตือนจุดเสี่ยงและครัวเรือนเปราะบางอัตโนมัติ">
        <a href="{{ route('public.map', $province) }}" target="_blank" rel="noopener" class="btn btn-soft"><i class="bi bi-box-arrow-up-right me-1"></i>แผนที่สาธารณะ</a>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#staffReportModal"><i class="bi bi-plus-lg me-1"></i>บันทึกระดับน้ำ</button>
    </x-page-head>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><x-stat label="รายงานวันนี้" :value="$stats['today']" icon="droplet-half" tint="blue" /></div>
        <div class="col-6 col-lg-3"><x-stat label="จุดน้ำลึกเกินเอว" :value="$stats['deep']" icon="water" tint="danger" /></div>
        <div class="col-6 col-lg-3"><x-stat label="ยืนยันแล้ว ใช้เตือนได้" :value="$stats['trusted']" icon="patch-check" tint="success" /></div>
        <div class="col-6 col-lg-3"><x-stat label="แจ้งว่าน้ำกำลังขึ้น" :value="$stats['rising']" icon="arrow-up-circle" tint="warning" /></div>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <ul class="nav nav-pills flex-nowrap overflow-auto" style="scrollbar-width:none">
            @foreach(WaterReportController::TABS as $k => $label)
                <li class="nav-item">
                    <a class="nav-link text-nowrap {{ $tab === $k ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['tab' => $k, 'page' => null]) }}">{{ $label }}<span class="count">{{ $counts[$k] }}</span></a>
                </li>
            @endforeach
        </ul>
        <form class="ms-lg-auto d-flex flex-wrap gap-2" method="GET">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <select name="district" class="form-select form-select-sm" style="width:150px" onchange="this.form.submit()">
                <option value="">ทุกอำเภอ</option>
                @foreach($districts as $d)<option value="{{ $d->id }}" @selected(request('district') == $d->id)>{{ $d->name_th }}</option>@endforeach
            </select>
            <select name="level" class="form-select form-select-sm" style="width:150px" onchange="this.form.submit()">
                <option value="">ทุกระดับ</option>
                @foreach(config('floodthai.water_levels') as $k => $lv)<option value="{{ $k }}" @selected(request('level') == $k)>{{ $lv['short'] }} ขึ้นไป</option>@endforeach
            </select>
            <select name="source" class="form-select form-select-sm" style="width:130px" onchange="this.form.submit()">
                <option value="">ทุกช่องทาง</option>
                @foreach(ReportOptions::SOURCES as $k => [$l])<option value="{{ $k }}" @selected(request('source') === $k)>{{ $l }}</option>@endforeach
            </select>
        </form>
    </div>

    <div class="row g-3">
        <div class="col-xl-7">
            <form method="POST" action="{{ route('reports.bulk') }}" id="bulkForm">
                @csrf
                <div class="card">
                    @if($reports->isNotEmpty())
                        <div class="card-header py-2">
                            <div class="form-check m-0"><input class="form-check-input" type="checkbox" id="checkAll"><label class="form-check-label small" for="checkAll">เลือกทั้งหน้า</label></div>
                            <div class="ch-actions d-flex gap-1">
                                <button class="btn btn-sm btn-success" name="action" value="verify" type="submit">ยืนยัน</button>
                                <button class="btn btn-sm btn-light" name="action" value="publish" type="submit">แสดง</button>
                                <button class="btn btn-sm btn-light" name="action" value="hide" type="submit">ซ่อน</button>
                                <button class="btn btn-sm btn-light text-danger" name="action" value="reject" type="submit">ปฏิเสธ</button>
                            </div>
                        </div>
                    @endif
                    @forelse($reports as $r)
                        <div class="list-row">
                            <input class="form-check-input mt-2 flex-shrink-0" type="checkbox" name="ids[]" value="{{ $r->id }}" form="bulkForm">
                            <span class="app-ico flex-shrink-0" style="background:{{ $r->color() }};color:#fff;width:42px;height:42px;border-radius:12px;font-size:.85rem;font-weight:700">{{ $r->level }}</span>
                            <div class="flex-grow-1 min-w-0">
                                <div class="d-flex flex-wrap align-items-center gap-1">
                                    <a href="#" class="fw-600 text-reset" data-focus-report="{{ $r->id }}">น้ำ{{ $r->levelLabel() }}</a>
                                    <span class="chip {{ $r->statusChip() }}">{{ $r->statusLabel() }}</span>
                                    @if($r->verified)<span class="chip chip-primary"><i class="bi bi-patch-check"></i> ยืนยันแล้ว</span>@endif
                                    @if($r->confirm_count)<span class="chip chip-success" title="คนในพื้นที่ยืนยัน"><i class="bi bi-hand-thumbs-up"></i> {{ $r->confirm_count }}</span>@endif
                                    @if($r->recede_count)<span class="chip" title="แจ้งว่าน้ำลด"><i class="bi bi-arrow-down"></i> {{ $r->recede_count }}</span>@endif
                                    @if($r->wrong_count)<span class="chip chip-danger" title="แจ้งว่าไม่ถูกต้อง"><i class="bi bi-flag"></i> {{ $r->wrong_count }}</span>@endif
                                    @if($r->trend)<span class="chip"><i class="bi bi-{{ ReportOptions::TRENDS[$r->trend][1] }}"></i> {{ $r->trendLabel() }}</span>@endif
                                    @if($r->update_count)<span class="chip">อัปเดต {{ $r->update_count }} ครั้ง</span>@endif
                                </div>
                                <div class="small text-muted">
                                    <i class="bi bi-{{ ReportOptions::SOURCES[$r->source][1] ?? 'globe2' }}"></i> {{ $r->sourceLabel() }}{{ $r->team ? ' '.$r->team->name : '' }}
                                    · {{ $r->areaLabel() }}{{ $r->placeLabel() ? ' · '.$r->placeLabel() : '' }}
                                    · {{ thai_date($r->updated_at, 'ago') }}
                                    @if($r->accuracy_m) · GPS ±{{ number_format($r->accuracy_m) }} ม.@endif
                                </div>
                                @if($r->hide_reason)<div class="small text-warning"><i class="bi bi-info-circle"></i> {{ $r->hide_reason }}</div>@endif
                                @if($r->note)<div class="small mt-1">{{ $r->note }}</div>@endif
                                @if($r->reporter_name || $r->reporter_phone)
                                    <div class="small text-muted"><i class="bi bi-person"></i> {{ $r->reporter_name }} @if($r->reporter_phone)<a href="tel:{{ $r->reporter_phone }}" class="mono">{{ phone_format($r->reporter_phone) }}</a>@endif</div>
                                @endif
                                @if($r->photos)
                                    <div class="d-flex gap-1 mt-1">@foreach($r->photoUrls() as $u)<a href="{{ $u }}" target="_blank" rel="noopener"><img src="{{ $u }}" class="wr-thumb" alt="" loading="lazy"></a>@endforeach</div>
                                @endif
                            </div>
                            <div class="d-flex flex-wrap gap-1 flex-shrink-0 justify-content-end" style="max-width:190px">
                                @unless($r->verified && $r->status === 'published')
                                    <button class="btn btn-sm btn-success" type="submit" form="mod{{ $r->id }}" name="action" value="verify">ยืนยัน</button>
                                @endunless
                                @if($r->status !== 'published')
                                    <button class="btn btn-sm btn-light" type="submit" form="mod{{ $r->id }}" name="action" value="publish">แสดง</button>
                                @else
                                    <button class="btn btn-sm btn-light" type="submit" form="mod{{ $r->id }}" name="action" value="hide">ซ่อน</button>
                                @endif
                                @if($r->status !== 'rejected')
                                    <button class="btn btn-sm btn-light text-danger" type="submit" form="mod{{ $r->id }}" name="action" value="reject" title="ข้อมูลเท็จ"><i class="bi bi-x-octagon"></i></button>
                                @endif
                                @can('cases.manage')
                                    <a class="btn btn-sm btn-light" title="เปิดเป็นเคสขอความช่วยเหลือ" href="{{ route('cases.create', ['lat' => $r->lat, 'lng' => $r->lng, 'water_level' => $r->level, 'note' => 'จากรายงานระดับน้ำ #'.$r->id.($r->note ? ': '.$r->note : '')]) }}"><i class="bi bi-life-preserver"></i></a>
                                @endcan
                            </div>
                        </div>
                    @empty
                        <x-empty icon="droplet" title="ไม่มีรายงานในหมวดนี้" text="{{ $tab === 'review' ? 'รายงานที่อยู่นอกจังหวัด ถูกโหวตว่าไม่ถูกต้อง หรือเปิดโหมดตรวจก่อนแสดง จะมารอที่นี่' : 'ลองเปลี่ยนแท็บหรือตัวกรอง' }}" />
                    @endforelse
                </div>
            </form>
            @foreach($reports as $r)
                <form method="POST" action="{{ route('reports.moderate', $r) }}" id="mod{{ $r->id }}" class="d-none">@csrf</form>
            @endforeach
            <div class="mt-3">{{ $reports->links() }}</div>
        </div>
        <div class="col-xl-5">
            <div class="card" style="position:sticky;top:calc(var(--sb-topbar) + 1rem)">
                <div class="card-header"><i class="bi bi-map text-primary"></i> รายงานที่แสดงอยู่และรอตรวจ
                    <div class="ch-actions small text-muted">ขอบน้ำเงิน = ยืนยันแล้ว · ประ = รอตรวจ</div>
                </div>
                <div class="card-body p-2"><div id="reportMap" class="map-box" style="height:540px"></div></div>
            </div>
        </div>
    </div>

    <x-form-modal id="staffReportModal" title="บันทึกระดับน้ำ" :action="route('reports.store')" :files="true">
        <div id="staffPickMap" class="map-box mb-2" style="height:260px"></div>
        <div class="row g-2 mb-3">
            <div class="col-6"><input name="lat" id="sLat" class="form-control form-control-sm mono" placeholder="ละติจูด" value="{{ old('lat') }}"></div>
            <div class="col-6"><input name="lng" id="sLng" class="form-control form-control-sm mono" placeholder="ลองจิจูด" value="{{ old('lng') }}"></div>
        </div>
        <div class="row g-3">
            <div class="col-6">
                <label class="form-label">ระดับน้ำ</label>
                <select name="level" class="form-select" data-default="3">
                    @foreach(config('floodthai.water_levels') as $k => $lv)<option value="{{ $k }}" @selected(old('level') == $k)>{{ $lv['label'] }}</option>@endforeach
                </select>
            </div>
            <div class="col-6">
                <label class="form-label">แนวโน้ม</label>
                <select name="trend" class="form-select">
                    <option value="">ไม่ระบุ</option>
                    @foreach(ReportOptions::TRENDS as $k => [$l])<option value="{{ $k }}">{{ $l }}</option>@endforeach
                </select>
            </div>
            <div class="col-12">
                <input name="note" class="form-control" maxlength="500" placeholder="แหล่งข้อมูล / รายละเอียด เช่น อส.รายงานทางวิทยุ" value="{{ old('note') }}">
            </div>
            <div class="col-12"><input type="file" name="photos[]" class="form-control" accept="image/*" multiple></div>
        </div>
        <div class="form-text mt-2">ระดับน้ำที่เจ้าหน้าที่บันทึกถือว่ายืนยันแล้ว ระบบตรวจจุดเสี่ยงรอบจุดนี้ทันที</div>
    </x-form-modal>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="{{ asset('js/map.js') }}?v={{ filemtime(public_path('js/map.js')) }}"></script>
    <script>
        (function () {
            const center = [{{ $province->center_lat ?? 13.7563 }}, {{ $province->center_lng ?? 100.5018 }}], zoom = {{ $province->default_zoom ?? 10 }};
            const areas = @json(route('dashboard.areas')) + '?level=district';
            const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
            const map = FloodMap.create(document.getElementById('reportMap'), { center, zoom, areasUrl: areas, fit: true });
            const markers = {};
            fetch(@json(route('reports.geojson')), { headers: { Accept: 'application/json' } }).then(r => r.json()).then(d => {
                d.features.forEach(f => {
                    const p = f.properties, [lng, lat] = f.geometry.coordinates;
                    markers[p.id] = L.circleMarker([lat, lng], {
                        radius: 5 + p.level * 1.5, color: p.trusted ? '#1d4ed8' : '#fff', weight: p.trusted ? 3 : 1.5,
                        dashArray: p.pending ? '3 3' : null, fillColor: p.color, fillOpacity: p.pending ? .45 : p.opacity,
                    }).bindPopup(`<b>น้ำ${esc(p.label)}</b><br><span class="small">${esc(p.source)} · ${esc(p.ago)}${p.trend ? ' · ' + esc(p.trend) : ''}</span>${p.note ? '<br>' + esc(p.note) : ''}`).addTo(map);
                });
            });
            document.addEventListener('click', e => {
                const a = e.target.closest('[data-focus-report]');
                if (!a) return;
                e.preventDefault();
                const m = markers[a.dataset.focusReport];
                if (m) { map.flyTo(m.getLatLng(), 15); m.openPopup(); }
            });

            const all = document.getElementById('checkAll');
            if (all) all.addEventListener('change', () => document.querySelectorAll('[name="ids[]"]').forEach(c => (c.checked = all.checked)));

            const modal = document.getElementById('staffReportModal');
            let pick = null;
            modal.addEventListener('shown.bs.modal', () => {
                if (!pick) pick = FloodMap.picker(document.getElementById('staffPickMap'), document.getElementById('sLat'), document.getElementById('sLng'), { center, zoom, areasUrl: areas });
                pick.invalidateSize();
            });
        })();
    </script>
@endpush
