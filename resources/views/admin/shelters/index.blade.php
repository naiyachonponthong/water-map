@extends('layouts.app')
@section('title', 'ศูนย์พักพิง')

@push('head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
@endpush

@section('content')
    @use('App\Support\ReliefOptions')

    <x-page-head title="ศูนย์พักพิง" sub="สถานะ จำนวนที่รับได้ และผู้อพยพในแต่ละศูนย์ ศูนย์เปลี่ยนเป็น เต็ม เองเมื่อครบจำนวน">
        @can('shelters.manage')
            <button class="btn btn-primary" data-form-modal="#shelterModal" data-action="{{ route('shelters.store') }}" data-method="POST" data-title="เพิ่มศูนย์พักพิง"><i class="bi bi-plus-lg me-1"></i>เพิ่มศูนย์</button>
        @endcan
    </x-page-head>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><x-stat label="ศูนย์ที่เปิดรับ" :value="$stats['open']" icon="house-heart" tint="teal" /></div>
        <div class="col-6 col-lg-3"><x-stat label="ผู้อพยพในศูนย์" :value="$stats['people']" icon="people" tint="primary" /></div>
        <div class="col-6 col-lg-3"><x-stat label="รับได้รวม" :value="$stats['capacity']" icon="grid-3x3" tint="slate" /></div>
        <div class="col-6 col-lg-3"><x-stat label="ลงทะเบียนวันนี้" :value="$stats['today']" icon="person-plus" tint="success" /></div>
    </div>

    <div class="row g-3">
        <div class="col-xl-7">
            @forelse($shelters as $s)
                <a href="{{ route('shelters.show', $s) }}" class="card card-lift text-reset mb-2 {{ $s->status === 'closed' ? 'opacity-50' : '' }}">
                    <div class="card-body d-flex gap-3 align-items-center">
                        <span class="app-ico teal flex-shrink-0" style="width:44px;height:44px;border-radius:14px"><i class="bi bi-{{ $s->icon() }}"></i></span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="d-flex flex-wrap align-items-center gap-1">
                                <span class="fw-bold me-1">{{ $s->name }}</span>
                                <span class="chip chip-dot {{ $s->statusChip() }}">{{ $s->statusLabel() }}</span>
                                @if($s->open_needs)<span class="chip chip-warning"><i class="bi bi-basket"></i> ต้องการ {{ $s->open_needs }} รายการ</span>@endif
                                @unless($s->is_public)<span class="chip"><i class="bi bi-eye-slash"></i></span>@endunless
                            </div>
                            <div class="small text-muted">{{ $s->typeLabel() }}{{ $s->district ? ' · อ.'.$s->district->name_th : '' }}{{ $s->contact_phone ? ' · '.$s->contact_phone : '' }}</div>
                            @if($s->capacity)
                                <div class="progress mt-2" style="height:8px"><div class="progress-bar" style="width:{{ $s->percent() }}%;background:{{ $s->percent() >= 90 ? '#dc2626' : ($s->percent() >= 70 ? '#f97316' : '#0d9488') }}"></div></div>
                            @endif
                        </div>
                        <div class="text-end flex-shrink-0">
                            <div class="fs-5 fw-bold mono">{{ number_format($s->occupancy) }}</div>
                            <div class="small text-muted">{{ $s->capacity ? 'จาก '.number_format($s->capacity) : 'คน' }}</div>
                        </div>
                    </div>
                </a>
            @empty
                <div class="card"><x-empty icon="house-heart" title="ยังไม่มีศูนย์พักพิง" text="เพิ่มวัด โรงเรียน หรืออาคารที่เตรียมไว้ ตั้งเป็น เตรียมเปิด ไว้ก่อนได้" /></div>
            @endforelse
        </div>
        <div class="col-xl-5">
            <div class="card" style="position:sticky;top:calc(var(--sb-topbar) + 1rem)">
                <div class="card-body p-2"><div id="shelterMap" class="map-box" style="height:480px"></div></div>
            </div>
        </div>
    </div>

    @can('shelters.manage')
        @include('admin.shelters._form')
    @endcan
@endsection

@push('scripts')
    @php
        $shelterMapData = $shelters->map(fn ($s) => [
            'name' => $s->name,
            'lat' => $s->lat,
            'lng' => $s->lng,
            'color' => $s->color(),
            'status' => $s->statusLabel(),
            'occ' => $s->occupancy,
            'cap' => $s->capacity,
            'url' => route('shelters.show', $s),
        ])->values();
    @endphp
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="{{ asset('js/map.js') }}?v={{ filemtime(public_path('js/map.js')) }}"></script>
    <script>
        (function () {
            const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
            const map = FloodMap.create(document.getElementById('shelterMap'), { center: [{{ $province->center_lat ?? 13.7563 }}, {{ $province->center_lng ?? 100.5018 }}], zoom: {{ $province->default_zoom ?? 10 }}, areasUrl: @json(route('dashboard.areas')) + '?level=district', fit: {{ $shelters->isEmpty() ? 'true' : 'false' }} });
            const data = @json($shelterMapData);
            const pts = data.map(s => {
                L.circleMarker([s.lat, s.lng], { radius: 10, color: '#fff', weight: 2, fillColor: s.color, fillOpacity: 1 })
                    .bindPopup(`<b>${esc(s.name)}</b><br>${esc(s.status)} · ${s.occ}${s.cap ? '/' + s.cap : ''} คน<br><a href="${esc(s.url)}">เปิด</a>`).addTo(map);
                return [s.lat, s.lng];
            });
            if (pts.length) map.fitBounds(L.latLngBounds(pts).pad(.2), { maxZoom: 13 });
        })();
    </script>
@endpush
