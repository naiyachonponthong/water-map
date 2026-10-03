@extends('layouts.app')
@section('title', 'กล้อง CCTV')

@push('head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
@endpush

@section('content')
    @use('App\Support\StationOptions')

    <x-page-head title="กล้อง CCTV" sub="รวมกล้องของหน่วยงานต่างๆ ไว้ที่เดียว ใช้ดูสภาพน้ำจริงประกอบการตัดสินใจ">
        <button class="btn btn-primary" data-form-modal="#cameraModal" data-action="{{ route('cctv.store') }}" data-method="POST" data-title="เพิ่มกล้อง"><i class="bi bi-plus-lg me-1"></i>เพิ่มกล้อง</button>
    </x-page-head>

    @if($cameras->isEmpty())
        <div class="card">
            <x-empty icon="camera-video" title="ยังไม่มีกล้อง" text="รองรับภาพนิ่งที่รีเฟรชเอง (JPG), วิดีโอสด HLS (.m3u8), ฝังหน้าเว็บ หรือลิงก์ไปหน้าเว็บของกล้อง" />
        </div>
    @else
        <div class="row g-3">
            @foreach($cameras as $c)
                <div class="col-sm-6 col-xl-4">
                    <div class="card h-100 {{ $c->is_active ? '' : 'opacity-50' }}">
                        <div class="cam-tile" style="border-radius:16px 16px 0 0" data-camera="{{ json_encode($c->toViewer()) }}">
                            <div class="cam-cap"><i class="bi bi-{{ $c->icon() }}"></i> {{ $c->typeLabel() }}</div>
                        </div>
                        <div class="card-body py-2 d-flex align-items-center gap-2">
                            <div class="min-w-0 flex-grow-1">
                                <div class="fw-600 text-truncate">{{ $c->name }}</div>
                                <div class="small text-muted text-truncate">{{ $c->owner ?: '-' }}{{ $c->station ? ' · สถานี'.$c->station->name : '' }}@unless($c->is_public) · ไม่แสดงสาธารณะ@endunless</div>
                            </div>
                            <button class="btn btn-sm btn-light btn-icon" data-form-modal="#cameraModal" data-action="{{ route('cctv.update', $c) }}" data-method="PUT" data-title="แก้ไขกล้อง"
                                data-fill="{{ json_encode($c->only(['name', 'type', 'url', 'lat', 'lng', 'refresh_sec', 'owner', 'water_station_id', 'sort', 'is_public', 'is_active'])) }}"><i class="bi bi-pencil"></i></button>
                            <form method="POST" action="{{ route('cctv.destroy', $c) }}" data-confirm="ลบกล้อง {{ $c->name }}?">@csrf @method('DELETE')
                                <button class="btn btn-sm btn-light btn-icon text-danger" type="submit"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <x-form-modal id="cameraModal" title="เพิ่มกล้อง" :action="route('cctv.store')" size="lg">
        <div class="row g-3">
            <div class="col-md-7">
                <label class="form-label">ชื่อกล้อง <span class="text-danger">*</span></label>
                <input name="name" class="form-control" maxlength="150" value="{{ old('name') }}" placeholder="เช่น สะพานบางปะกง ฝั่งตลาด">
            </div>
            <div class="col-md-5">
                <label class="form-label">ชนิด</label>
                <select name="type" class="form-select" data-default="image">
                    @foreach(StationOptions::CAMERA_TYPES as $k => [$l])<option value="{{ $k }}" @selected(old('type') === $k)>{{ $l }}</option>@endforeach
                </select>
            </div>
            <div class="col-12">
                <label class="form-label">URL <span class="text-danger">*</span></label>
                <input name="url" type="url" class="form-control mono" maxlength="500" value="{{ old('url') }}" placeholder="https://">
                <div class="form-text">ต้องเป็น https และเจ้าของกล้องอนุญาตให้นำมาแสดง</div>
            </div>
            <div class="col-12">
                <div id="camPickMap" class="map-box" style="height:200px"></div>
                <div class="d-flex gap-2 mt-2">
                    <input name="lat" id="cLat" class="form-control form-control-sm mono" placeholder="ละติจูด" value="{{ old('lat') }}">
                    <input name="lng" id="cLng" class="form-control form-control-sm mono" placeholder="ลองจิจูด" value="{{ old('lng') }}">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">รีเฟรช (วินาที)</label>
                <input name="refresh_sec" type="number" min="10" max="3600" class="form-control" value="{{ old('refresh_sec', 60) }}" data-default="60">
            </div>
            <div class="col-6 col-md-4">
                <label class="form-label">หน่วยงานเจ้าของ</label>
                <input name="owner" class="form-control" maxlength="120" value="{{ old('owner') }}">
            </div>
            <div class="col-8 col-md-3">
                <label class="form-label">ผูกกับสถานี</label>
                <select name="water_station_id" class="form-select">
                    <option value="">ไม่ผูก</option>
                    @foreach($stations as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-4 col-md-2">
                <label class="form-label">ลำดับ</label>
                <input name="sort" type="number" min="0" class="form-control" value="{{ old('sort', 0) }}" data-default="0">
            </div>
            <div class="col-md-6">
                <input type="hidden" name="is_public" value="0">
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_public" value="1" id="cPub" data-default="1" @checked(old('is_public', '1'))><label class="form-check-label" for="cPub">แสดงบนเว็บประชาชน</label></div>
            </div>
            <div class="col-md-6">
                <input type="hidden" name="is_active" value="0">
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="cAct" data-default="1" @checked(old('is_active', '1'))><label class="form-check-label" for="cAct">ใช้งาน</label></div>
            </div>
        </div>
    </x-form-modal>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="{{ asset('js/map.js') }}?v={{ filemtime(public_path('js/map.js')) }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.15/dist/hls.min.js"></script>
    <script src="{{ asset('js/stations.js') }}?v={{ filemtime(public_path('js/stations.js')) }}"></script>
    <script>
        (function () {
            // แสดงภาพเฉพาะกล้องที่อยู่บนจอ ประหยัดเน็ต
            const io = new IntersectionObserver(entries => entries.forEach(e => {
                if (e.isIntersecting) FloodStations.mountCamera(e.target, JSON.parse(e.target.dataset.camera));
                else FloodStations.unmount(e.target);
            }), { rootMargin: '100px' });
            document.querySelectorAll('[data-camera]').forEach(el => io.observe(el));

            const modal = document.getElementById('cameraModal');
            let pick = null, marker = null;
            modal.addEventListener('shown.bs.modal', () => {
                const lat = document.getElementById('cLat'), lng = document.getElementById('cLng');
                const place = () => {
                    const a = parseFloat(lat.value), b = parseFloat(lng.value);
                    if (isNaN(a) || isNaN(b)) { if (marker) { pick.removeLayer(marker); marker = null; } return; }
                    if (!marker) marker = L.marker([a, b]).addTo(pick); else marker.setLatLng([a, b]);
                    pick.setView([a, b], 14);
                };
                if (!pick) {
                    pick = FloodMap.create(document.getElementById('camPickMap'), { center: [{{ $province->center_lat ?? 13.7563 }}, {{ $province->center_lng ?? 100.5018 }}], zoom: {{ $province->default_zoom ?? 10 }} });
                    pick.on('click', e => { lat.value = e.latlng.lat.toFixed(7); lng.value = e.latlng.lng.toFixed(7); place(); });
                }
                pick.invalidateSize(); place();
            });
        })();
    </script>
@endpush
