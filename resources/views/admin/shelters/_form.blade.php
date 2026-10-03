@use('App\Support\ReliefOptions')
<x-form-modal id="shelterModal" title="เพิ่มศูนย์พักพิง" :action="route('shelters.store')" size="lg">
    <div class="row g-3">
        <div class="col-md-7">
            <label class="form-label">ชื่อศูนย์ <span class="text-danger">*</span></label>
            <input name="name" class="form-control" maxlength="150" value="{{ old('name') }}" placeholder="เช่น วัดโสธรวรารามวรวิหาร">
        </div>
        <div class="col-md-5">
            <label class="form-label">ประเภท</label>
            <select name="type" class="form-select" data-default="temple">
                @foreach(ReliefOptions::SHELTER_TYPES as $k => [$l])<option value="{{ $k }}" @selected(old('type') === $k)>{{ $l }}</option>@endforeach
            </select>
        </div>
        <div class="col-12">
            <div id="shelterPickMap" class="map-box" style="height:220px"></div>
            <div class="d-flex gap-2 mt-2">
                <input name="lat" id="shLat" class="form-control form-control-sm mono" placeholder="ละติจูด" value="{{ old('lat') }}">
                <input name="lng" id="shLng" class="form-control form-control-sm mono" placeholder="ลองจิจูด" value="{{ old('lng') }}">
            </div>
        </div>
        <div class="col-12">
            <label class="form-label">ที่อยู่</label>
            <input name="address" class="form-control" maxlength="255" value="{{ old('address') }}">
        </div>
        <div class="col-6 col-md-4">
            <label class="form-label">รับได้ (คน)</label>
            <input name="capacity" type="number" min="0" class="form-control" value="{{ old('capacity', 0) }}" data-default="0">
        </div>
        <div class="col-6 col-md-4">
            <label class="form-label">สถานะ</label>
            <select name="status" class="form-select" data-default="preparing">
                @foreach(ReliefOptions::SHELTER_STATUS as $k => [$l])<option value="{{ $k }}" @selected(old('status') === $k)>{{ $l }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-4 d-flex align-items-end">
            <input type="hidden" name="is_public" value="0">
            <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" name="is_public" value="1" id="shPub" data-default="1" @checked(old('is_public', '1'))><label class="form-check-label" for="shPub">แสดงบนเว็บประชาชน</label></div>
        </div>
        <div class="col-md-6">
            <label class="form-label">ผู้ประสานงาน</label>
            <input name="contact_name" class="form-control" maxlength="120" value="{{ old('contact_name') }}">
        </div>
        <div class="col-md-6">
            <label class="form-label">เบอร์ติดต่อศูนย์</label>
            <input name="contact_phone" class="form-control mono" maxlength="20" value="{{ old('contact_phone') }}">
        </div>
        <div class="col-12">
            <label class="form-label">สิ่งอำนวยความสะดวก</label>
            <div class="d-flex flex-wrap gap-2">
                @foreach(ReliefOptions::FACILITIES as $k => [$l, $ic])
                    <input type="checkbox" class="btn-check" name="facilities[]" value="{{ $k }}" id="fac{{ $k }}" @checked(in_array($k, old('facilities', [])))>
                    <label class="btn btn-outline-secondary btn-sm" for="fac{{ $k }}"><i class="bi bi-{{ $ic }} me-1"></i>{{ $l }}</label>
                @endforeach
            </div>
        </div>
        <div class="col-12">
            <label class="form-label">หมายเหตุ</label>
            <textarea name="note" class="form-control" rows="2" maxlength="2000">{{ old('note') }}</textarea>
        </div>
    </div>
</x-form-modal>

@push('scripts')
    <script>
        (function () {
            const modal = document.getElementById('shelterModal');
            let pick = null, marker = null;
            modal.addEventListener('shown.bs.modal', () => {
                const lat = document.getElementById('shLat'), lng = document.getElementById('shLng');
                const place = () => {
                    const a = parseFloat(lat.value), b = parseFloat(lng.value);
                    if (isNaN(a) || isNaN(b)) { if (marker) { pick.removeLayer(marker); marker = null; } return; }
                    if (!marker) marker = L.marker([a, b]).addTo(pick); else marker.setLatLng([a, b]);
                    pick.setView([a, b], 15);
                };
                if (!pick) {
                    pick = FloodMap.create(document.getElementById('shelterPickMap'), { center: @json([$province->center_lat ?? 13.7563, $province->center_lng ?? 100.5018]), zoom: {{ $province->default_zoom ?? 10 }} });
                    pick.on('click', e => { lat.value = e.latlng.lat.toFixed(7); lng.value = e.latlng.lng.toFixed(7); place(); });
                }
                pick.invalidateSize(); place();
            });
        })();
    </script>
@endpush
