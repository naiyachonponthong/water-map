@extends('layouts.public')
@section('title', 'แจ้งจุดเสี่ยง '.$province->fullName())

@push('head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
@endpush

@section('content')
    <header class="pub-head">
        <div class="inner">
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('public.province', $province) }}" class="text-white fs-4 me-1" aria-label="กลับ"><i class="bi bi-arrow-left"></i></a>
                <div>
                    <div class="fw-bold lh-sm">แจ้งจุดเสี่ยง</div>
                    <div class="small opacity-75">{{ $province->fullName() }} · เจ้าหน้าที่ตรวจก่อนแสดงบนแผนที่</div>
                </div>
            </div>
        </div>
    </header>

    <div class="pub-wrap">
        <form method="POST" action="{{ route('public.risks.store', $province) }}" class="pub-float" novalidate>
            @csrf
            <input type="text" name="website" class="d-none" tabindex="-1" autocomplete="off" aria-hidden="true">

            <div class="alert alert-warning small"><i class="bi bi-exclamation-triangle"></i>
                <div>ถ้ามีคนติดอยู่หรือต้องการความช่วยเหลือตอนนี้ <a href="{{ route('public.help', $province) }}" class="fw-600">ขอความช่วยเหลือ</a> แทน หน้านี้สำหรับแจ้งจุดอันตรายให้ศูนย์รู้ล่วงหน้า</div>
            </div>

            @if($errors->any())
                <div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i><div>{{ $errors->first() }}</div></div>
            @endif

            <div class="card mb-3">
                <div class="card-body">
                    <label class="form-label">ตำแหน่ง <span class="text-danger">*</span></label>
                    <div class="d-flex gap-2 mb-2">
                        <button type="button" class="btn btn-soft btn-sm" id="btnGps"><i class="bi bi-crosshair me-1"></i>ตำแหน่งปัจจุบัน</button>
                        <span class="small text-muted align-self-center">หรือแตะบนแผนที่</span>
                    </div>
                    <div id="proposeMap" class="map-box mb-2" style="height:280px"></div>
                    <input type="hidden" name="lat" id="pLat" value="{{ old('lat') }}">
                    <input type="hidden" name="lng" id="pLng" value="{{ old('lng') }}">

                    <label class="form-label mt-2">เป็นจุดแบบไหน <span class="text-danger">*</span></label>
                    <div class="d-grid gap-2 mb-3" style="grid-template-columns:1fr 1fr">
                        @foreach($types as $k => [$l, $ic, $color])
                            <input type="radio" class="btn-check" name="type" value="{{ $k }}" id="t_{{ $k }}" @checked(old('type') === $k)>
                            <label class="btn btn-outline-secondary text-start" for="t_{{ $k }}"><i class="bi bi-{{ $ic }} me-1" style="color:{{ $color }}"></i>{{ $l }}</label>
                        @endforeach
                    </div>

                    <label class="form-label">ชื่อจุด <span class="text-danger">*</span></label>
                    <input name="name" class="form-control mb-3" maxlength="150" value="{{ old('name') }}" placeholder="เช่น ถนนหน้าวัดใหม่ ทางลงสะพาน">

                    <label class="form-label">อันตรายอย่างไร</label>
                    <textarea name="description" class="form-control" rows="3" maxlength="1000" placeholder="เช่น น้ำท่วมทุกปี ลึกเกินเข่า กระแสแรง เสาไฟเอียง">{{ old('description') }}</textarea>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <div class="small text-muted mb-2">ไม่บังคับ ใช้ติดต่อกลับเมื่อเจ้าหน้าที่ต้องการข้อมูลเพิ่ม ไม่แสดงต่อสาธารณะ</div>
                    <div class="row g-2">
                        <div class="col-sm-6"><input name="proposer_name" class="form-control" maxlength="120" placeholder="ชื่อ" value="{{ old('proposer_name') }}"></div>
                        <div class="col-sm-6"><input name="proposer_phone" type="tel" inputmode="tel" class="form-control" maxlength="20" placeholder="เบอร์โทร" value="{{ old('proposer_phone') }}"></div>
                    </div>
                </div>
            </div>

            @include('partials.privacy-note', ['text' => 'ชื่อและเบอร์ใช้ติดต่อกลับเท่านั้น ไม่แสดงต่อสาธารณะ'])
            <button type="submit" class="btn btn-primary btn-lg w-100">ส่งให้เจ้าหน้าที่ตรวจ</button>
        </form>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="{{ asset('js/map.js') }}?v={{ filemtime(public_path('js/map.js')) }}"></script>
    <script>
        (function () {
            const lat = document.getElementById('pLat'), lng = document.getElementById('pLng');
            const map = FloodMap.picker(document.getElementById('proposeMap'), lat, lng, {
                center: [{{ $province->center_lat ?? 13.7563 }}, {{ $province->center_lng ?? 100.5018 }}], zoom: {{ $province->default_zoom ?? 10 }},
            });
            document.getElementById('btnGps').addEventListener('click', () => {
                if (!navigator.geolocation) return alert('อุปกรณ์นี้ไม่รองรับการหาตำแหน่ง');
                navigator.geolocation.getCurrentPosition(p => {
                    const ll = L.latLng(p.coords.latitude, p.coords.longitude);
                    map.fire('click', { latlng: ll }); map.setView(ll, 16);
                }, () => alert('หาตำแหน่งไม่ได้ ลองแตะบนแผนที่แทน'), { enableHighAccuracy: true, timeout: 15000 });
            });
        })();
    </script>
@endpush
