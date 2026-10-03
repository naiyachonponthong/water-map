@extends('layouts.app')
@section('title', 'จุดเสี่ยง')

@push('head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css">
@endpush

@section('content')
    @use('App\Support\RiskOptions')

    <x-page-head title="จุดเสี่ยง" sub="ระบบเตือนอัตโนมัติเมื่อระดับน้ำรอบจุดถึงเกณฑ์ และเปิดเคสตรวจเยี่ยมครัวเรือนเปราะบางให้เอง">
        <form method="POST" action="{{ route('risks.evaluate') }}">@csrf
            <button class="btn btn-soft" type="submit"><i class="bi bi-arrow-repeat me-1"></i>ตรวจตอนนี้</button>
        </form>
        <button class="btn btn-soft" data-bs-toggle="modal" data-bs-target="#importModal"><i class="bi bi-upload me-1"></i>นำเข้า</button>
        <button class="btn btn-primary" data-form-modal="#riskModal" data-action="{{ route('risks.store') }}" data-method="POST" data-title="เพิ่มจุดเสี่ยง"><i class="bi bi-plus-lg me-1"></i>เพิ่มจุดเสี่ยง</button>
    </x-page-head>

    @if(session('import_errors'))
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i>
            <div><div class="fw-600 mb-1">บางรายการนำเข้าไม่ได้</div>
                <ul class="mb-0 small ps-3">@foreach(session('import_errors') as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        </div>
    @endif

    {{-- ประกาศระดับน้ำรายตำบล --}}
    <div class="card mb-3">
        <div class="card-header"><i class="bi bi-megaphone text-warning"></i> ระดับน้ำที่ศูนย์ประกาศ
            <div class="ch-actions"><button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#levelModal"><i class="bi bi-plus-lg me-1"></i>ประกาศระดับน้ำ</button></div>
        </div>
        @if($areaLevels->isEmpty())
            <div class="card-body small text-muted">ยังไม่มีประกาศ ระบบใช้ระดับน้ำจากเคสที่ประชาชนแจ้งเป็นหลัก ประกาศเพิ่มเมื่อรู้ว่าน้ำมาถึงตำบลไหนแล้ว จุดเสี่ยงและครัวเรือนเปราะบางในตำบลนั้นจะถูกตรวจทันที</div>
        @else
            <div class="card-body d-flex flex-wrap gap-2">
                @foreach($areaLevels as $al)
                    @php $lv = config('floodthai.water_levels.'.$al->level); @endphp
                    <div class="level-pill">
                        <span class="lv-dot" style="background:{{ $lv['color'] }}"></span>
                        <div class="small">
                            <div class="fw-600">ต.{{ $al->subdistrict?->name_th }} <span class="text-muted fw-normal">อ.{{ $al->subdistrict?->district?->name_th }}</span></div>
                            <div class="text-muted">{{ $lv['short'] }} · ถึง {{ thai_date($al->expires_at, 'compact') }}{{ $al->note ? ' · '.$al->note : '' }}</div>
                        </div>
                        <form method="POST" action="{{ route('risks.levels.end', $al) }}" data-confirm="ยกเลิกประกาศ ต.{{ $al->subdistrict?->name_th }}?">@csrf @method('DELETE')
                            <button class="btn btn-sm btn-light btn-icon" type="submit" title="ยกเลิก"><i class="bi bi-x-lg"></i></button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <ul class="nav nav-pills flex-nowrap overflow-auto" style="scrollbar-width:none">
            @foreach(['live' => 'ใช้งาน', 'threatened' => 'กำลังเตือน', 'pending' => 'ประชาชนเสนอ', 'inactive' => 'ปิด/ปฏิเสธ'] as $k => $label)
                <li class="nav-item">
                    <a class="nav-link text-nowrap {{ $tab === $k ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['tab' => $k, 'page' => null]) }}">{{ $label }}<span class="count">{{ $counts[$k] }}</span></a>
                </li>
            @endforeach
        </ul>
        <form class="ms-lg-auto d-flex flex-wrap gap-2" method="GET">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <select name="type" class="form-select form-select-sm" style="width:190px" onchange="this.form.submit()">
                <option value="">ทุกประเภท</option>
                @foreach(RiskOptions::TYPES as $k => [$l])<option value="{{ $k }}" @selected(request('type') === $k)>{{ $l }}</option>@endforeach
            </select>
            <div class="input-group input-group-sm" style="width:200px">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="search" name="q" class="form-control" placeholder="ชื่อจุด" value="{{ request('q') }}">
            </div>
        </form>
    </div>

    <div class="row g-3">
        <div class="col-xl-6">
            <div class="card">
                @forelse($points as $p)
                    <div class="list-row">
                        <span class="app-ico" style="background:{{ $p->color() }}1a;color:{{ $p->color() }};width:40px;height:40px;border-radius:12px"><i class="bi bi-{{ $p->icon() }}"></i></span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="d-flex flex-wrap align-items-center gap-1">
                                <a href="#" class="fw-600 text-reset" data-focus-risk="{{ $p->id }}">{{ $p->name }}</a>
                                @if($p->review === 'pending')<span class="chip chip-warning">รอตรวจ</span>
                                @elseif($p->review === 'rejected')<span class="chip">ปฏิเสธ</span>
                                @else<span class="chip chip-dot {{ RiskOptions::STATUS[$p->status][1] ?? '' }}">{{ $p->statusLabel() }}</span>@endif
                                @if($p->severity !== 'low')<span class="chip {{ RiskOptions::SEVERITY[$p->severity][1] }}">เสี่ยง{{ RiskOptions::SEVERITY[$p->severity][0] }}</span>@endif
                                @if($p->zone)<span class="chip"><i class="bi bi-bounding-box"></i> โซน</span>@endif
                                @unless($p->is_public)<span class="chip" title="ไม่แสดงบนเว็บประชาชน"><i class="bi bi-eye-slash"></i></span>@endunless
                            </div>
                            <div class="small text-muted">
                                {{ $p->typeLabel() }}
                                @if($p->subdistrict) · ต.{{ $p->subdistrict->name_th }} อ.{{ $p->district?->name_th }}@endif
                                · เตือนเมื่อน้ำ{{ config('floodthai.water_levels.'.$p->trigger_level.'.short') }}
                                @unless($p->zone) · รัศมี {{ number_format($p->radius()) }} ม.@endunless
                            </div>
                            @if($p->status === 'threatened' && $p->threat_reason)
                                <div class="small text-danger"><i class="bi bi-exclamation-octagon"></i> {{ $p->threat_reason }} · {{ thai_date($p->threatened_at, 'ago') }}</div>
                            @endif
                            @if($p->source === 'citizen' && $p->proposer_name)
                                <div class="small text-muted"><i class="bi bi-person"></i> เสนอโดย {{ $p->proposer_name }}{{ $p->proposer_phone ? ' '.phone_format($p->proposer_phone) : '' }}</div>
                            @endif
                            @if($p->description)<div class="small mt-1">{{ \Illuminate\Support\Str::limit($p->description, 140) }}</div>@endif
                        </div>
                        <div class="d-flex gap-1 flex-shrink-0">
                            @if($p->review === 'pending')
                                <form method="POST" action="{{ route('risks.review', $p) }}">@csrf<input type="hidden" name="decision" value="approved"><button class="btn btn-sm btn-success" type="submit">อนุมัติ</button></form>
                                <form method="POST" action="{{ route('risks.review', $p) }}">@csrf<input type="hidden" name="decision" value="rejected"><button class="btn btn-sm btn-light" type="submit">ปฏิเสธ</button></form>
                            @endif
                            <button class="btn btn-sm btn-light btn-icon" title="แก้ไข" data-form-modal="#riskModal" data-action="{{ route('risks.update', $p) }}" data-method="PUT" data-title="แก้ไขจุดเสี่ยง"
                                data-fill="{{ json_encode([
                                    'name' => $p->name, 'type' => $p->type, 'description' => $p->description, 'lat' => $p->lat, 'lng' => $p->lng,
                                    'zone' => $p->zone ?? '', 'radius_m' => $p->radius_m, 'trigger_level' => $p->trigger_level, 'severity' => $p->severity,
                                    'is_public' => $p->is_public, 'status' => $p->status === 'inactive' ? 'inactive' : 'normal',
                                ]) }}"><i class="bi bi-pencil"></i></button>
                            <form method="POST" action="{{ route('risks.destroy', $p) }}" data-confirm="ลบ {{ $p->name }}?">@csrf @method('DELETE')
                                <button class="btn btn-sm btn-light btn-icon text-danger" type="submit" title="ลบ"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </div>
                @empty
                    <x-empty icon="exclamation-triangle" title="ไม่มีจุดเสี่ยงในหมวดนี้" text="{{ $tab === 'pending' ? 'จุดที่ประชาชนเสนอผ่านหน้าเว็บจะมารอตรวจที่นี่' : 'เพิ่มเองบนแผนที่ หรือนำเข้าจาก CSV, KML, GeoJSON' }}" />
                @endforelse
            </div>
            <div class="mt-3">{{ $points->links() }}</div>
        </div>
        <div class="col-xl-6">
            <div class="card" style="position:sticky;top:calc(var(--sb-topbar) + 1rem)">
                <div class="card-header"><i class="bi bi-map text-primary"></i> แผนที่จุดเสี่ยง
                    <div class="ch-actions small d-flex gap-2">
                        <span><span class="lv-dot" style="background:#dc2626;width:9px;height:9px"></span> กำลังเตือน</span>
                        <span><span class="lv-dot" style="background:#f59e0b;width:9px;height:9px;border:2px dashed #92400e"></span> รอตรวจ</span>
                    </div>
                </div>
                <div class="card-body p-2"><div id="riskMap" class="map-box" style="height:560px"></div></div>
            </div>
        </div>
    </div>

    {{-- เพิ่ม/แก้ไขจุดเสี่ยง --}}
    <x-form-modal id="riskModal" title="เพิ่มจุดเสี่ยง" :action="route('risks.store')" size="lg">
        <div class="row g-3">
            <div class="col-md-7">
                <label class="form-label">ชื่อจุด <span class="text-danger">*</span></label>
                <input name="name" class="form-control" maxlength="150" required value="{{ old('name') }}" placeholder="เช่น สะพานข้ามคลองบางพระ">
            </div>
            <div class="col-md-5">
                <label class="form-label">ประเภท</label>
                <select name="type" class="form-select" data-default="flood_prone">
                    @foreach(RiskOptions::TYPES as $k => [$l])<option value="{{ $k }}" @selected(old('type') === $k)>{{ $l }}</option>@endforeach
                </select>
            </div>
            <div class="col-12">
                <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                    <span class="small text-muted">คลิกแผนที่เพื่อปักหมุด หรือวาดโซนสำหรับพื้นที่กว้าง</span>
                    <div class="ms-auto d-flex gap-1">
                        <button type="button" class="btn btn-sm btn-soft" id="rmDraw"><i class="bi bi-bounding-box me-1"></i>วาดโซน</button>
                        <button type="button" class="btn btn-sm btn-light" id="rmClearZone"><i class="bi bi-eraser me-1"></i>ลบโซน</button>
                    </div>
                </div>
                <div id="riskPickMap" class="map-box" style="height:300px"></div>
                <div class="d-flex gap-2 mt-2">
                    <input name="lat" class="form-control form-control-sm mono" placeholder="ละติจูด" value="{{ old('lat') }}" readonly>
                    <input name="lng" class="form-control form-control-sm mono" placeholder="ลองจิจูด" value="{{ old('lng') }}" readonly>
                    <textarea name="zone" class="d-none">{{ old('zone') }}</textarea>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label">เตือนเมื่อน้ำถึง</label>
                <select name="trigger_level" class="form-select" data-default="2">
                    @foreach(config('floodthai.water_levels') as $k => $lv)<option value="{{ $k }}" @selected(old('trigger_level') == $k)>{{ $lv['label'] }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">รัศมีเตือน (เมตร)</label>
                <input name="radius_m" type="number" min="50" max="20000" step="50" class="form-control" value="{{ old('radius_m') }}" placeholder="ค่าเริ่มต้น {{ setting('risk_alert_radius_m') }}">
                <div class="form-text">ใช้เมื่อไม่ได้วาดโซน</div>
            </div>
            <div class="col-md-4">
                <label class="form-label">ความรุนแรง</label>
                <select name="severity" class="form-select" data-default="medium">
                    @foreach(RiskOptions::SEVERITY as $k => [$l])<option value="{{ $k }}" @selected(old('severity') === $k)>{{ $l }}</option>@endforeach
                </select>
            </div>
            <div class="col-12">
                <label class="form-label">รายละเอียด / ข้อควรระวัง</label>
                <textarea name="description" class="form-control" rows="2" maxlength="2000" placeholder="เช่น น้ำไหลแรงข้ามถนนช่วงกลางคืน รถเล็กห้ามผ่าน">{{ old('description') }}</textarea>
            </div>
            <div class="col-md-6">
                <div class="form-check form-switch">
                    <input type="hidden" name="is_public" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" name="is_public" value="1" id="rIsPublic" data-default="1" @checked(old('is_public', '1'))>
                    <label class="form-check-label" for="rIsPublic">แสดงบนเว็บประชาชน</label>
                </div>
            </div>
            <div class="col-md-6" data-only="edit">
                <select name="status" class="form-select form-select-sm" data-default="normal">
                    <option value="normal">ใช้งาน</option>
                    <option value="inactive">ปิดใช้งาน</option>
                </select>
            </div>
        </div>
    </x-form-modal>

    {{-- ประกาศระดับน้ำ --}}
    <x-form-modal id="levelModal" title="ประกาศระดับน้ำรายตำบล" :action="route('risks.levels.store')" submit="ประกาศ">
        <div class="mb-3">
            <label class="form-label">ตำบล <span class="text-danger">*</span></label>
            <select name="subdistrict_ids[]" class="form-select" multiple data-search data-placeholder="พิมพ์ชื่อตำบล เลือกได้หลายตำบล">
                @foreach($subdistricts as $s)<option value="{{ $s->id }}" @selected(in_array($s->id, old('subdistrict_ids', [])))>ต.{{ $s->name_th }} อ.{{ $s->district?->name_th }}</option>@endforeach
            </select>
        </div>
        <div class="row g-3">
            <div class="col-7">
                <label class="form-label">ระดับน้ำ</label>
                <select name="level" class="form-select" data-default="2">
                    @foreach(config('floodthai.water_levels') as $k => $lv)<option value="{{ $k }}">{{ $lv['label'] }}</option>@endforeach
                </select>
            </div>
            <div class="col-5">
                <label class="form-label">มีผล (ชั่วโมง)</label>
                <input name="hours" type="number" class="form-control" min="1" max="72" value="{{ old('hours', 12) }}" data-default="12">
            </div>
            <div class="col-12">
                <label class="form-label">หมายเหตุ</label>
                <input name="note" class="form-control" maxlength="255" placeholder="เช่น น้ำจากเขื่อนระบายเพิ่ม" value="{{ old('note') }}">
            </div>
        </div>
        <div class="form-text mt-2">เมื่อประกาศแล้ว ระบบจะตรวจจุดเสี่ยงและครัวเรือนเปราะบางในตำบลเหล่านี้ทันที และหมดอายุเองตามเวลาที่ตั้ง</div>
    </x-form-modal>

    {{-- นำเข้า --}}
    <x-form-modal id="importModal" title="นำเข้าจุดเสี่ยง" :action="route('risks.import')" submit="นำเข้า" :files="true">
        <div class="mb-3">
            <label class="form-label">ไฟล์ CSV, KML หรือ GeoJSON</label>
            <input type="file" name="file" class="form-control" accept=".csv,.txt,.kml,.geojson,.json" required>
        </div>
        <div class="mb-2">
            <label class="form-label">ประเภทเริ่มต้น (ถ้าไฟล์ไม่ระบุ)</label>
            <select name="type" class="form-select" data-default="flood_prone">
                @foreach(RiskOptions::TYPES as $k => [$l])<option value="{{ $k }}">{{ $l }}</option>@endforeach
            </select>
        </div>
        <div class="small text-muted">
            CSV ใช้หัวคอลัมน์ <span class="mono">ชื่อ, ประเภท, ละติจูด, ลองจิจูด, รัศมี, ระดับเตือน, รายละเอียด</span>
            · KML จาก Google My Maps รองรับทั้งหมุดและรูปหลายเหลี่ยม · GeoJSON ใช้ property <span class="mono">name</span>
        </div>
    </x-form-modal>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>
    <script src="{{ asset('js/map.js') }}?v={{ filemtime(public_path('js/map.js')) }}"></script>
    <script src="{{ asset('js/risks.js') }}?v={{ filemtime(public_path('js/risks.js')) }}"></script>
    <script>
        FloodRisks.admin({
            mapEl: document.getElementById('riskMap'),
            pickEl: document.getElementById('riskPickMap'),
            modal: document.getElementById('riskModal'),
            center: [{{ $province->center_lat ?? 13.7563 }}, {{ $province->center_lng ?? 100.5018 }}],
            zoom: {{ $province->default_zoom ?? 10 }},
            areasUrl: @json(route('dashboard.areas')) + '?level=district',
            risksUrl: @json(route('risks.geojson')),
            defaultRadius: {{ (int) setting('risk_alert_radius_m') }},
        });
    </script>
@endpush
