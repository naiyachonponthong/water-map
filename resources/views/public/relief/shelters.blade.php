@extends('layouts.public')
@section('title', 'ศูนย์พักพิง '.$province->fullName())

@push('head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
@endpush

@section('content')
    @use('App\Support\ReliefOptions')
    <header class="pub-head">
        <div class="inner d-flex align-items-center gap-2">
            <a href="{{ route('public.province', $province) }}" class="text-white fs-4 me-1" aria-label="กลับ"><i class="bi bi-arrow-left"></i></a>
            <div><div class="fw-bold lh-sm">ศูนย์พักพิง</div><div class="small opacity-75">{{ $province->fullName() }}</div></div>
            <a href="{{ route('public.find', $province) }}" class="btn btn-sm btn-light ms-auto"><i class="bi bi-search"></i> ค้นหาญาติ</a>
        </div>
    </header>

    <div class="pub-wrap">
        <div class="pub-float">
            @if($shelters->isNotEmpty())
                <div class="card mb-3"><div class="card-body p-2"><div id="shMap" class="map-box" style="height:260px"></div></div></div>
                <button class="btn btn-primary w-100 mb-3" id="nearMe" type="button"><i class="bi bi-crosshair me-1"></i>เรียงตามระยะจากฉัน</button>
            @endif
            <div id="shList">
                @forelse($shelters as $s)
                    <div class="card mb-2" data-lat="{{ $s->lat }}" data-lng="{{ $s->lng }}">
                        <div class="card-body">
                            <div class="d-flex align-items-start gap-2">
                                <div class="flex-grow-1 min-w-0">
                                    <div class="fw-bold">{{ $s->name }}</div>
                                    <div class="small text-muted">{{ $s->address ?: ($s->district ? 'อ.'.$s->district->name_th : '') }} <span class="dist"></span></div>
                                </div>
                                <span class="chip chip-dot {{ $s->statusChip() }}">{{ $s->statusLabel() }}</span>
                            </div>
                            @if($s->capacity)
                                <div class="d-flex align-items-center gap-2 mt-2">
                                    <div class="progress flex-grow-1" style="height:8px"><div class="progress-bar" style="width:{{ $s->percent() }}%;background:{{ $s->percent() >= 90 ? '#dc2626' : '#0d9488' }}"></div></div>
                                    <span class="small text-nowrap">ว่าง <b>{{ number_format($s->available()) }}</b> ที่</span>
                                </div>
                            @endif
                            @if($s->facilities)
                                <div class="d-flex flex-wrap gap-2 small text-muted mt-2">@foreach($s->facilities as $f)<span><i class="bi bi-{{ ReliefOptions::FACILITIES[$f][1] ?? 'dot' }}"></i> {{ ReliefOptions::FACILITIES[$f][0] ?? $f }}</span>@endforeach</div>
                            @endif
                            @if($s->needs->isNotEmpty())
                                <div class="small mt-2"><b class="text-warning"><i class="bi bi-basket"></i> ต้องการ:</b>
                                    @foreach($s->needs as $n)<span class="{{ $n->priority === 'urgent' ? 'text-danger fw-600' : '' }}">{{ $n->item }}{{ $n->qty ? ' '.number_format($n->qty).' '.$n->unit : '' }}</span>@if(! $loop->last), @endif @endforeach
                                </div>
                            @endif
                            <div class="d-flex gap-2 mt-3">
                                <a class="btn btn-sm btn-primary" target="_blank" rel="noopener" href="https://www.google.com/maps/dir/?api=1&destination={{ $s->lat }},{{ $s->lng }}"><i class="bi bi-sign-turn-right"></i> นำทาง</a>
                                @if($s->contact_phone)<a class="btn btn-sm btn-light" href="tel:{{ preg_replace('/\D+/', '', $s->contact_phone) }}"><i class="bi bi-telephone"></i> {{ $s->contact_phone }}</a>@endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="card"><x-empty icon="house-heart" title="ยังไม่มีศูนย์พักพิงที่เปิด" text="หากต้องการอพยพ โทร {{ $hotline }}" /></div>
                @endforelse
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @if($shelters->isNotEmpty())
        <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
        <script src="{{ asset('js/map.js') }}?v={{ filemtime(public_path('js/map.js')) }}"></script>
        <script>
            (function () {
                const map = FloodMap.create(document.getElementById('shMap'), { center: [{{ $province->center_lat ?? 13.7563 }}, {{ $province->center_lng ?? 100.5018 }}], zoom: 10 });
                const cards = [...document.querySelectorAll('#shList [data-lat]')];
                const pts = cards.map(c => { const ll = [parseFloat(c.dataset.lat), parseFloat(c.dataset.lng)]; L.circleMarker(ll, { radius: 9, color: '#fff', weight: 2, fillColor: '#0d9488', fillOpacity: 1 }).bindPopup(c.querySelector('.fw-bold').textContent).addTo(map); return ll; });
                map.fitBounds(L.latLngBounds(pts).pad(.2), { maxZoom: 14 });
                document.getElementById('nearMe').addEventListener('click', () => navigator.geolocation && navigator.geolocation.getCurrentPosition(p => {
                    const me = L.latLng(p.coords.latitude, p.coords.longitude);
                    L.circleMarker(me, { radius: 8, color: '#fff', weight: 3, fillColor: '#2563eb', fillOpacity: 1 }).addTo(map);
                    cards.forEach(c => { c._d = me.distanceTo([c.dataset.lat, c.dataset.lng]); c.querySelector('.dist').textContent = '· ' + (c._d / 1000).toFixed(1) + ' กม.'; });
                    cards.sort((a, b) => a._d - b._d).forEach(c => c.parentNode.appendChild(c));
                }, () => alert('หาตำแหน่งไม่ได้')));
            })();
        </script>
    @endif
@endpush
