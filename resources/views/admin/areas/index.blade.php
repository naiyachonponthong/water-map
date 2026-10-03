@extends('layouts.app')
@section('title', 'อำเภอและตำบล')

@push('head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
@endpush

@section('content')
    <x-page-head title="อำเภอและตำบล" sub="ขอบเขตพื้นที่ใช้ระบายสีระดับน้ำ หาตำบลจากพิกัดผู้แจ้ง และกรองเคสตามพื้นที่">
        <button class="btn btn-soft" data-bs-toggle="modal" data-bs-target="#importModal"><i class="bi bi-upload me-1"></i>นำเข้า GeoJSON</button>
        <button class="btn btn-primary" data-form-modal="#districtModal" data-action="{{ route('admin.areas.districts.store') }}" data-method="POST" data-title="เพิ่มอำเภอ"><i class="bi bi-plus-lg me-1"></i>เพิ่มอำเภอ</button>
    </x-page-head>

    @if(session('import_errors'))
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i>
            <div><div class="fw-600 mb-1">บางรายการนำเข้าไม่ได้</div>
                <ul class="mb-0 small ps-3">@foreach(session('import_errors') as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><i class="bi bi-map text-primary"></i> อำเภอ <span class="ch-actions small text-muted">{{ $districts->count() }} อำเภอ</span></div>
                <div class="list-group list-group-flush" style="border-radius:0 0 16px 16px;overflow:hidden">
                    @forelse($districts as $d)
                        <a href="{{ route('admin.areas.index', ['district' => $d->id]) }}"
                           class="list-group-item list-group-item-action d-flex align-items-center gap-2 py-2 {{ $selected?->id === $d->id ? 'active' : '' }}"
                           @if($selected?->id === $d->id) style="background:var(--sb-primary-50);color:var(--sb-primary-700);border-color:var(--sb-border-soft)" @endif>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-600">{{ $d->shortName() }}</div>
                                <div class="small text-muted">
                                    {{ $d->subdistricts_count }} ตำบล
                                    @if($d->subdistricts_count) · ขอบเขต {{ $d->subdistricts_with_boundary_count }}/{{ $d->subdistricts_count }}@endif
                                </div>
                            </div>
                            @if($d->bbox_south)
                                <i class="bi bi-bounding-box text-success" title="มีขอบเขตแผนที่"></i>
                            @else
                                <i class="bi bi-bounding-box text-muted opacity-50" title="ยังไม่มีขอบเขต"></i>
                            @endif
                        </a>
                    @empty
                        <x-empty icon="map" title="ยังไม่มีอำเภอ" text="นำเข้าไฟล์ GeoJSON หรือเพิ่มเอง" />
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-body p-2"><div id="areaMap" class="map-box sm"></div></div>
            </div>

            @if($selected)
                <div class="card table-card">
                    <div class="card-header">
                        <i class="bi bi-geo-alt text-primary"></i> ตำบลใน{{ $selected->shortName() }}
                        <div class="ch-actions">
                            <button class="btn btn-sm btn-light" data-form-modal="#districtModal" data-action="{{ route('admin.areas.districts.update', $selected) }}" data-method="PUT" data-title="แก้ไข{{ $selected->shortName() }}"
                                    data-fill="{{ json_encode($selected->only(['name_th', 'name_en', 'code', 'sort', 'center_lat', 'center_lng'])) }}"><i class="bi bi-pencil"></i> แก้ไขอำเภอ</button>
                            <form method="POST" action="{{ route('admin.areas.districts.destroy', $selected) }}" data-confirm="ลบ{{ $selected->shortName() }} และตำบลทั้งหมดในอำเภอนี้?">@csrf @method('DELETE')
                                <button class="btn btn-sm btn-light text-danger btn-icon" type="submit" title="ลบอำเภอ"><i class="bi bi-trash"></i></button>
                            </form>
                            <button class="btn btn-sm btn-primary" data-form-modal="#subdistrictModal" data-action="{{ route('admin.areas.subdistricts.store') }}" data-method="POST" data-title="เพิ่มตำบล"
                                    data-fill="{{ json_encode(['district_id' => $selected->id]) }}"><i class="bi bi-plus-lg"></i> ตำบล</button>
                        </div>
                    </div>
                    @if($subdistricts->isEmpty())
                        <x-empty icon="geo-alt" title="ยังไม่มีตำบล" text="นำเข้า GeoJSON ระดับตำบล หรือเพิ่มเองทีละตำบล" />
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover table-stack">
                                <thead><tr><th>ตำบล</th><th>รหัส</th><th>รหัสไปรษณีย์</th><th>ขอบเขต</th><th></th></tr></thead>
                                <tbody>
                                @foreach($subdistricts as $s)
                                    <tr>
                                        <td class="td-main fw-600">ต.{{ $s->name_th }}</td>
                                        <td data-label="รหัส" class="mono small">{{ $s->code ?: '-' }}</td>
                                        <td data-label="รหัสไปรษณีย์" class="mono small">{{ $s->postcode ?: '-' }}</td>
                                        <td data-label="ขอบเขต">@if($s->bbox_south)<span class="chip chip-success">มี</span>@else<span class="chip">ยังไม่มี</span>@endif</td>
                                        <td class="text-end text-nowrap">
                                            <button class="btn btn-sm btn-light btn-icon" data-form-modal="#subdistrictModal" data-action="{{ route('admin.areas.subdistricts.update', $s) }}" data-method="PUT" data-title="แก้ไข ต.{{ $s->name_th }}"
                                                    data-fill="{{ json_encode($s->only(['district_id', 'name_th', 'code', 'postcode', 'center_lat', 'center_lng'])) }}"><i class="bi bi-pencil"></i></button>
                                            <form method="POST" action="{{ route('admin.areas.subdistricts.destroy', $s) }}" class="d-inline" data-confirm="ลบ ต.{{ $s->name_th }}?">@csrf @method('DELETE')
                                                <button class="btn btn-sm btn-light btn-icon text-danger" type="submit"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- อำเภอ --}}
    <x-form-modal id="districtModal" title="เพิ่มอำเภอ" :action="route('admin.areas.districts.store')">
        <div class="row g-3">
            <div class="col-8">
                <label class="form-label req">ชื่ออำเภอ</label>
                <input name="name_th" class="form-control" placeholder="ไม่ต้องใส่คำว่า อำเภอ" value="{{ old('name_th') }}" required>
            </div>
            <div class="col-4">
                <label class="form-label">รหัส 4 หลัก</label>
                <input name="code" class="form-control mono" inputmode="numeric" maxlength="4" value="{{ old('code') }}">
            </div>
            <div class="col-8">
                <label class="form-label">ชื่ออังกฤษ</label>
                <input name="name_en" class="form-control" value="{{ old('name_en') }}">
            </div>
            <div class="col-4">
                <label class="form-label">ลำดับ</label>
                <input name="sort" type="number" min="0" class="form-control" value="{{ old('sort') }}">
            </div>
            <div class="col-6">
                <label class="form-label">ละติจูดกลาง</label>
                <input name="center_lat" class="form-control mono" value="{{ old('center_lat') }}">
            </div>
            <div class="col-6">
                <label class="form-label">ลองจิจูดกลาง</label>
                <input name="center_lng" class="form-control mono" value="{{ old('center_lng') }}">
            </div>
            <div class="col-12 form-text mt-1">พิกัดกลางคำนวณให้เองเมื่อนำเข้าขอบเขต</div>
        </div>
    </x-form-modal>

    {{-- ตำบล --}}
    <x-form-modal id="subdistrictModal" title="เพิ่มตำบล" :action="route('admin.areas.subdistricts.store')">
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label req">อำเภอ</label>
                <select name="district_id" class="form-select">
                    @foreach($districts as $d)
                        <option value="{{ $d->id }}" @selected(old('district_id', $selected?->id) == $d->id)>{{ $d->shortName() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-8">
                <label class="form-label req">ชื่อตำบล</label>
                <input name="name_th" class="form-control" placeholder="ไม่ต้องใส่คำว่า ตำบล" value="{{ old('name_th') }}" required>
            </div>
            <div class="col-4">
                <label class="form-label">รหัส 6 หลัก</label>
                <input name="code" class="form-control mono" inputmode="numeric" maxlength="6" value="{{ old('code') }}">
            </div>
            <div class="col-4">
                <label class="form-label">รหัสไปรษณีย์</label>
                <input name="postcode" class="form-control mono" inputmode="numeric" maxlength="5" value="{{ old('postcode') }}">
            </div>
            <div class="col-4">
                <label class="form-label">ละติจูด</label>
                <input name="center_lat" class="form-control mono" value="{{ old('center_lat') }}">
            </div>
            <div class="col-4">
                <label class="form-label">ลองจิจูด</label>
                <input name="center_lng" class="form-control mono" value="{{ old('center_lng') }}">
            </div>
        </div>
    </x-form-modal>

    {{-- นำเข้า --}}
    <div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" action="{{ route('admin.areas.import') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">นำเข้าขอบเขตจาก GeoJSON</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label req">ระดับพื้นที่</label>
                    <div class="d-flex gap-2 mb-3">
                        <input type="radio" class="btn-check" name="level" id="lvD" value="district" checked>
                        <label class="btn btn-outline-primary flex-fill" for="lvD">อำเภอ</label>
                        <input type="radio" class="btn-check" name="level" id="lvS" value="subdistrict">
                        <label class="btn btn-outline-primary flex-fill" for="lvS">ตำบล</label>
                    </div>
                    <label class="form-label req">ไฟล์ .geojson / .json</label>
                    <input type="file" name="file" class="form-control mb-2" accept=".geojson,.json,application/geo+json,application/json" required>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="boundary_only" value="1" id="boundaryOnly">
                        <label class="form-check-label small" for="boundaryOnly">อัปเดตขอบเขตเฉพาะพื้นที่ที่มีอยู่แล้ว ไม่เพิ่มรายการใหม่</label>
                    </div>
                    <div class="alert alert-primary small mb-0">
                        <i class="bi bi-info-circle"></i>
                        <div>
                            รองรับไฟล์ชุด COD-AB ของประเทศไทย (คอลัมน์ ADM2_TH / ADM3_TH / ADM2_PCODE) และไฟล์ที่มีคอลัมน์ name_th + code
                            ระบบข้ามพื้นที่ของจังหวัดอื่นให้เอง นำเข้าอำเภอก่อนตำบลเสมอ
                            ไฟล์ทั้งประเทศใหญ่เกิน 50 MB ใช้คำสั่ง <code>php artisan flood:import-areas</code>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-upload me-1"></i>นำเข้า</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="{{ asset('js/map.js') }}?v={{ filemtime(public_path('js/map.js')) }}"></script>
    <script>
        (function () {
            const base = @json(route('admin.areas.geojson'));
            const selectedId = @json($selected?->id);
            const map = FloodMap.create(document.getElementById('areaMap'), {
                center: [{{ $province->center_lat ?? 13.7563 }}, {{ $province->center_lng ?? 100.5018 }}],
                zoom: {{ $province->default_zoom ?? 9 }},
                areasUrl: base + '?level=district',
                onAreaClick: p => { window.location = @json(route('admin.areas.index')) + '?district=' + p.id; },
            });
            // ไฮไลต์อำเภอที่เลือก
            const timer = setInterval(() => {
                if (!map || !map._areas) return;
                clearInterval(timer);
                map._areas.eachLayer(l => {
                    if (l.feature.properties.id === selectedId) {
                        l.setStyle({ weight: 3, fillOpacity: .22 });
                        map.fitBounds(l.getBounds(), { padding: [20, 20] });
                    }
                });
            }, 200);
            setTimeout(() => clearInterval(timer), 8000);
        })();
    </script>
@endpush
