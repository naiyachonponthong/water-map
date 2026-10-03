@extends('layouts.public')
@section('title', 'แจ้งระดับน้ำ '.$province->fullName())

@push('head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
@endpush

@section('content')
    @use('App\Support\ReportOptions')

    <header class="pub-head">
        <div class="inner">
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('public.map', $province) }}" class="text-white fs-4 me-1" aria-label="กลับ"><i class="bi bi-arrow-left"></i></a>
                <div>
                    <div class="fw-bold lh-sm">แจ้งระดับน้ำ</div>
                    <div class="small opacity-75">{{ $province->fullName() }} · ช่วยให้ศูนย์และเพื่อนบ้านรู้สถานการณ์</div>
                </div>
            </div>
        </div>
    </header>

    <div class="pub-wrap">
        @unless($open)
            <div class="pub-float">
                <div class="card">
                    <div class="card-body track-status">
                        <div class="ts-ico tint-primary"><i class="bi bi-droplet"></i></div>
                        <h1 class="h5 fw-bold">ยังไม่เปิดรับรายงานระดับน้ำ</h1>
                        <p class="text-muted small mb-3">ดูสถานการณ์บนแผนที่ได้ หากต้องการความช่วยเหลือ โทร {{ $hotline }}</p>
                        <a href="{{ route('public.map', $province) }}" class="btn btn-primary">ดูแผนที่สถานการณ์</a>
                    </div>
                </div>
            </div>
        @else
            <form method="POST" action="{{ route('public.reports.store', $province) }}" enctype="multipart/form-data" class="pub-float" id="reportForm" novalidate>
                @csrf
                <input type="text" name="website" class="d-none" tabindex="-1" autocomplete="off" aria-hidden="true">

                <div class="alert alert-danger small"><i class="bi bi-life-preserver"></i>
                    <div>ติดอยู่หรือต้องการความช่วยเหลือ <a href="{{ route('public.help', $province) }}" class="fw-600">ขอความช่วยเหลือ</a> แทน หน้านี้ใช้รายงานระดับน้ำเท่านั้น ศูนย์จะไม่ส่งทีมจากรายงานนี้</div>
                </div>

                @if($errors->any())
                    <div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i><div>{{ $errors->first() }}</div></div>
                @endif

                <section class="card mb-3">
                    <div class="card-body">
                        <h2 class="h6 fw-bold mb-2">1. ตำแหน่งที่น้ำท่วม</h2>
                        <div class="d-grid gap-2 mb-2" style="grid-template-columns:1fr 1fr">
                            <button type="button" class="btn btn-primary" id="btnGps"><i class="bi bi-crosshair me-1"></i>ตรงนี้</button>
                            <span class="small text-muted align-self-center">หรือแตะบนแผนที่ตรงจุดที่เห็นน้ำ</span>
                        </div>
                        <div id="reportMap" class="map-box mb-1" style="height:240px"></div>
                        <div class="small" id="locStatus"><span class="text-muted">ยังไม่ได้เลือกตำแหน่ง</span></div>
                        <input type="hidden" name="lat" id="rLat" value="{{ old('lat') }}">
                        <input type="hidden" name="lng" id="rLng" value="{{ old('lng') }}">
                        <input type="hidden" name="accuracy_m" id="rAcc" value="{{ old('accuracy_m') }}">
                    </div>
                </section>

                <section class="card mb-3">
                    <div class="card-body">
                        <h2 class="h6 fw-bold mb-2">2. น้ำสูงแค่ไหน</h2>
                        <div class="level-pick">
                            @foreach($levels as $k => $lv)
                                <input type="radio" class="btn-check" name="level" value="{{ $k }}" id="lv{{ $k }}" @checked(old('level') == $k)>
                                <label for="lv{{ $k }}" style="--lc:{{ $lv['color'] }}">
                                    <span class="lp-bar"><span style="height:{{ min(100, $k * 17) }}%"></span></span>
                                    <span class="lp-text"><b>{{ $lv['short'] }}</b><small>{{ $lv['range'] ?? '' }}</small></span>
                                </label>
                            @endforeach
                        </div>

                        <div class="small fw-600 mt-3 mb-1">น้ำตอนนี้</div>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach(ReportOptions::TRENDS as $k => [$l, $ic])
                                <input type="radio" class="btn-check" name="trend" value="{{ $k }}" id="tr{{ $k }}" @checked(old('trend') === $k)>
                                <label class="btn btn-outline-secondary btn-sm" for="tr{{ $k }}"><i class="bi bi-{{ $ic }} me-1"></i>{{ $l }}</label>
                            @endforeach
                        </div>

                        <div class="small fw-600 mt-3 mb-1">จุดนี้เป็น</div>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach(ReportOptions::PLACES as $k => [$l, $ic])
                                <input type="radio" class="btn-check" name="place_type" value="{{ $k }}" id="pl{{ $k }}" @checked(old('place_type') === $k)>
                                <label class="btn btn-outline-secondary btn-sm" for="pl{{ $k }}"><i class="bi bi-{{ $ic }} me-1"></i>{{ $l }}</label>
                            @endforeach
                        </div>
                    </div>
                </section>

                <section class="card mb-3">
                    <div class="card-body">
                        <h2 class="h6 fw-bold mb-2">3. รูปและรายละเอียด <span class="text-muted fw-normal small">(ไม่บังคับ)</span></h2>
                        <label class="btn btn-soft w-100 mb-2"><i class="bi bi-camera me-1"></i>ถ่ายรูป / เลือกรูป (สูงสุด 3 รูป)
                            <input type="file" name="photos[]" accept="image/*" capture="environment" multiple class="d-none" id="rPhotos">
                        </label>
                        <div class="small text-muted mb-2" id="photoCount"></div>
                        <textarea name="note" class="form-control mb-3" rows="2" maxlength="500" placeholder="เช่น รถเล็กผ่านไม่ได้ น้ำไหลแรง">{{ old('note') }}</textarea>
                        <div class="row g-2">
                            <div class="col-6"><input name="reporter_name" class="form-control" maxlength="120" placeholder="ชื่อ" value="{{ old('reporter_name') }}"></div>
                            <div class="col-6"><input name="reporter_phone" type="tel" inputmode="tel" class="form-control" maxlength="15" placeholder="เบอร์โทร" value="{{ old('reporter_phone') }}"></div>
                        </div>
                        <div class="form-text">ชื่อและเบอร์ไม่แสดงต่อสาธารณะ ใช้เมื่อเจ้าหน้าที่ต้องการสอบถามเพิ่ม</div>
                    </div>
                </section>

                @if($premoderate)
                    <div class="small text-muted mb-2"><i class="bi bi-info-circle"></i> ช่วงนี้รายงานจะขึ้นแผนที่หลังเจ้าหน้าที่ตรวจแล้ว</div>
                @endif
                @include('partials.privacy-note', ['text' => 'ชื่อและเบอร์ไม่แสดงต่อสาธารณะ ตำแหน่งและรูปที่ส่งจะแสดงบนแผนที่สาธารณะ'])
                <button type="submit" class="btn btn-primary btn-lg w-100"><i class="bi bi-send me-1"></i>ส่งรายงาน</button>
            </form>
        @endunless
    </div>
@endsection

@push('scripts')
    @if($open)
        <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
        <script src="{{ asset('js/map.js') }}?v={{ filemtime(public_path('js/map.js')) }}"></script>
        <script>
            (function () {
                const lat = document.getElementById('rLat'), lng = document.getElementById('rLng'), acc = document.getElementById('rAcc');
                const status = document.getElementById('locStatus');
                const map = FloodMap.picker(document.getElementById('reportMap'), lat, lng, {
                    center: [{{ $province->center_lat ?? 13.7563 }}, {{ $province->center_lng ?? 100.5018 }}], zoom: {{ $province->default_zoom ?? 10 }},
                });
                const show = () => { if (lat.value) status.innerHTML = '<span class="text-success"><i class="bi bi-check-circle"></i> ได้ตำแหน่งแล้ว' + (acc.value ? ' (แม่นยำราว ' + acc.value + ' ม.)' : '') + '</span>'; };
                map.on('click', () => { acc.value = ''; setTimeout(show, 0); });
                show();

                document.getElementById('btnGps').addEventListener('click', () => {
                    if (!navigator.geolocation) return alert('อุปกรณ์นี้ไม่รองรับการหาตำแหน่ง แตะบนแผนที่แทน');
                    status.innerHTML = '<span class="text-muted">กำลังหาตำแหน่ง...</span>';
                    navigator.geolocation.getCurrentPosition(p => {
                        const ll = L.latLng(p.coords.latitude, p.coords.longitude);
                        map.fire('click', { latlng: ll }); map.setView(ll, 16);
                        const accuracy = Number(p.coords.accuracy);
                        acc.value = Number.isFinite(accuracy) && accuracy >= 0 && accuracy <= 100000 ? Math.round(accuracy) : '';
                        show();
                    }, () => { status.innerHTML = '<span class="text-danger">หาตำแหน่งไม่ได้ เปิด GPS หรือแตะบนแผนที่แทน</span>'; }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 60000 });
                });

                const photos = document.getElementById('rPhotos');
                photos.addEventListener('change', () => {
                    const n = photos.files.length;
                    document.getElementById('photoCount').textContent = n ? (n > 3 ? 'เลือกได้สูงสุด 3 รูป จะใช้ 3 รูปแรก' : 'เลือกแล้ว ' + n + ' รูป') : '';
                });

                document.getElementById('reportForm').addEventListener('submit', e => {
                    if (!lat.value) { e.preventDefault(); alert('กรุณาระบุตำแหน่ง'); return; }
                    if (!document.querySelector('[name=level]:checked')) { e.preventDefault(); alert('เลือกระดับน้ำ'); }
                });
            })();
        </script>
    @endif
@endpush
