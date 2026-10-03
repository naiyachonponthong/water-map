{{-- ช่องกรอกคำร้องเยียวยา ใช้ทั้งหน้าประชาชนและเจ้าหน้าที่ --}}
@use('App\Support\RecoveryOptions')
<div class="card mb-3">
    <div class="card-body row g-3">
        <h2 class="h6 fw-bold mb-0">1. ผู้ยื่นคำร้อง</h2>
        <div class="col-md-6">
            <label class="form-label">ชื่อ-สกุล หัวหน้าครัวเรือน <span class="text-danger">*</span></label>
            <input name="head_name" class="form-control @error('head_name') is-invalid @enderror" maxlength="120" value="{{ old('head_name') }}" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">เบอร์โทร <span class="text-danger">*</span></label>
            <input name="phone" type="tel" inputmode="tel" class="form-control mono @error('phone') is-invalid @enderror" maxlength="15" value="{{ old('phone') }}" required>
            <div class="form-text">ใช้เบอร์เดียวกับตอนแจ้งขอความช่วยเหลือ ระบบจะตรวจสอบให้เร็วขึ้น</div>
        </div>
        <div class="col-12">
            <label class="form-label">ที่อยู่บ้านที่ได้รับความเสียหาย <span class="text-danger">*</span></label>
            <input name="address" class="form-control @error('address') is-invalid @enderror" maxlength="255" value="{{ old('address') }}" placeholder="บ้านเลขที่ หมู่ ตำบล อำเภอ" required>
        </div>
        <div class="col-12">
            <div class="d-flex gap-2 mb-2 align-items-center">
                <button type="button" class="btn btn-soft btn-sm" id="rcGps"><i class="bi bi-crosshair me-1"></i>ใช้ตำแหน่งปัจจุบัน</button>
                <span class="small text-muted">หรือแตะบนแผนที่ตรงบ้าน (ไม่บังคับ แต่ช่วยให้ตรวจสอบเร็วขึ้น)</span>
            </div>
            <div id="rcMap" class="map-box" style="height:220px"></div>
            <input type="hidden" name="lat" id="rcLat" value="{{ old('lat') }}">
            <input type="hidden" name="lng" id="rcLng" value="{{ old('lng') }}">
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label">จำนวนคนในบ้าน</label>
            <input name="members" type="number" min="1" max="50" class="form-control" value="{{ old('members', 1) }}">
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label">สถานะที่อยู่</label>
            <select name="tenure" class="form-select">@foreach(RecoveryOptions::TENURE as $k => $l)<option value="{{ $k }}" @selected(old('tenure', 'own') === $k)>{{ $l }}</option>@endforeach</select>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body row g-3">
        <h2 class="h6 fw-bold mb-0">2. ความเสียหาย</h2>
        <div class="col-12">
            <label class="form-label">บ้าน <span class="text-danger">*</span></label>
            <div class="d-grid gap-2" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr))">
                @foreach(RecoveryOptions::HOUSE as $k => [$l, , $color])
                    <input type="radio" class="btn-check" name="house_damage" value="{{ $k }}" id="hd{{ $k }}" @checked(old('house_damage') === $k)>
                    <label class="btn btn-outline-secondary" for="hd{{ $k }}" style="--bs-btn-active-bg:{{ $color }};--bs-btn-active-border-color:{{ $color }}">{{ $l }}</label>
                @endforeach
            </div>
        </div>
        <div class="col-6 col-md-4">
            <label class="form-label">น้ำท่วมสูงสุด</label>
            <select name="water_level" class="form-select"><option value="">ไม่ระบุ</option>@foreach($levels as $k => $lv)<option value="{{ $k }}" @selected(old('water_level') == $k)>{{ $lv['label'] }}</option>@endforeach</select>
        </div>
        <div class="col-6 col-md-4">
            <label class="form-label">ท่วมขังกี่วัน</label>
            <input name="flood_days" type="number" min="0" max="365" class="form-control" value="{{ old('flood_days') }}">
        </div>
        <div class="col-12">
            <label class="form-label">ทรัพย์สินที่เสียหาย</label>
            <div class="d-flex flex-wrap gap-2">
                @foreach(RecoveryOptions::LOSSES as $k => [$l, $ic])
                    <input type="checkbox" class="btn-check" name="losses[]" value="{{ $k }}" id="ls{{ $k }}" @checked(in_array($k, old('losses', [])))>
                    <label class="btn btn-outline-secondary btn-sm" for="ls{{ $k }}"><i class="bi bi-{{ $ic }} me-1"></i>{{ $l }}</label>
                @endforeach
            </div>
        </div>
        <div class="col-6 col-md-4">
            <label class="form-label">พื้นที่เกษตรเสียหาย (ไร่)</label>
            <input name="crop_rai" type="number" step="0.25" min="0" class="form-control" value="{{ old('crop_rai') }}">
        </div>
        <div class="col-6 col-md-4">
            <label class="form-label">สัตว์ตาย/สูญหาย (ตัว)</label>
            <input name="livestock" type="number" min="0" class="form-control" value="{{ old('livestock') }}">
        </div>
        <div class="col-12">
            <label class="form-label">รูปความเสียหาย (สูงสุด 5 รูป)</label>
            <input type="file" name="photos[]" class="form-control" accept="image/*" multiple>
        </div>
        <div class="col-12">
            <label class="form-label">รายละเอียดเพิ่มเติม</label>
            <textarea name="note" class="form-control" rows="2" maxlength="1000">{{ old('note') }}</textarea>
        </div>
    </div>
</div>

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="{{ asset('js/map.js') }}?v={{ filemtime(public_path('js/map.js')) }}"></script>
    <script>
        (function () {
            const lat = document.getElementById('rcLat'), lng = document.getElementById('rcLng');
            const map = FloodMap.picker(document.getElementById('rcMap'), lat, lng, { center: [{{ $province->center_lat ?? 13.7563 }}, {{ $province->center_lng ?? 100.5018 }}], zoom: {{ $province->default_zoom ?? 10 }} });
            document.getElementById('rcGps').addEventListener('click', () => navigator.geolocation && navigator.geolocation.getCurrentPosition(p => {
                const ll = L.latLng(p.coords.latitude, p.coords.longitude);
                map.fire('click', { latlng: ll }); map.setView(ll, 17);
            }, () => alert('หาตำแหน่งไม่ได้ แตะบนแผนที่แทน'), { enableHighAccuracy: true, timeout: 15000 }));
        })();
    </script>
@endpush
