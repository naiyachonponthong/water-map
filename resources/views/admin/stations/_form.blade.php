@use('App\Support\StationOptions')
<x-form-modal id="stationModal" title="เพิ่มสถานี" :action="route('stations.store')" size="lg">
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">ชื่อสถานี <span class="text-danger">*</span></label>
            <input name="name" class="form-control" maxlength="150" value="{{ old('name') }}" placeholder="เช่น สะพานแม่น้ำบางปะกง">
        </div>
        <div class="col-md-3">
            <label class="form-label">รหัส</label>
            <input name="code" class="form-control" maxlength="40" value="{{ old('code') }}">
        </div>
        <div class="col-md-3">
            <label class="form-label">ลำน้ำ</label>
            <input name="river" class="form-control" maxlength="120" value="{{ old('river') }}" placeholder="บางปะกง">
        </div>
        <div class="col-12">
            <div id="stationPickMap" class="map-box" style="height:220px"></div>
            <div class="d-flex gap-2 mt-2">
                <input name="lat" id="stLat" class="form-control form-control-sm mono" placeholder="ละติจูด" value="{{ old('lat') }}">
                <input name="lng" id="stLng" class="form-control form-control-sm mono" placeholder="ลองจิจูด" value="{{ old('lng') }}">
            </div>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label">หน่วย</label>
            <input name="unit" class="form-control" maxlength="20" value="{{ old('unit', 'ม.รทก.') }}" data-default="ม.รทก.">
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label">ระดับตลิ่ง</label>
            <input name="bank_level" type="number" step="0.01" class="form-control mono" value="{{ old('bank_level') }}">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label text-warning">เฝ้าระวัง</label>
            <input name="watch_level" type="number" step="0.01" class="form-control mono" value="{{ old('watch_level') }}">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label" style="color:#f97316">เตือนภัย</label>
            <input name="warning_level" type="number" step="0.01" class="form-control mono" value="{{ old('warning_level') }}">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label text-danger">วิกฤต</label>
            <input name="critical_level" type="number" step="0.01" class="form-control mono" value="{{ old('critical_level') }}">
        </div>
        <div class="col-6 col-md-4">
            <label class="form-label">รัศมีที่ใช้เตือนจุดเสี่ยง</label>
            <div class="input-group">
                <input name="influence_radius_m" type="number" min="200" max="30000" step="100" class="form-control mono" value="{{ old('influence_radius_m', 3000) }}" data-default="3000">
                <span class="input-group-text">ม.</span>
            </div>
        </div>
        <div class="col-md-8">
            <label class="form-label">ลิงก์หน้าเว็บต้นทาง</label>
            <input name="link_url" type="url" class="form-control" maxlength="500" value="{{ old('link_url') }}" placeholder="https://">
        </div>

        <div class="col-12">
            <label class="form-label">การรับข้อมูล</label>
            <select name="fetch_mode" class="form-select" data-default="manual" id="stMode">
                @foreach(StationOptions::FETCH_MODES as $k => $l)<option value="{{ $k }}" @selected(old('fetch_mode') === $k)>{{ $l }}</option>@endforeach
            </select>
        </div>
        <div class="col-12 st-json">
            <label class="form-label">URL ของ JSON API</label>
            <input name="fetch_url" type="url" class="form-control mono" maxlength="500" value="{{ old('fetch_url') }}" placeholder="https://.../waterlevel?station=...">
        </div>
        <div class="col-md-5 st-json">
            <label class="form-label">ตำแหน่งค่าระดับน้ำ</label>
            <input name="value_path" class="form-control mono" maxlength="120" value="{{ old('value_path') }}" placeholder="data.0.waterlevel_msl">
        </div>
        <div class="col-md-4 st-json">
            <label class="form-label">ตำแหน่งเวลา</label>
            <input name="time_path" class="form-control mono" maxlength="120" value="{{ old('time_path') }}" placeholder="data.0.datetime">
        </div>
        <div class="col-md-3 st-json">
            <label class="form-label">บวกค่าเพิ่ม</label>
            <input name="value_offset" type="number" step="0.01" class="form-control mono" value="{{ old('value_offset', 0) }}" data-default="0">
        </div>
        <div class="col-12 st-json"><div class="form-text">ระบบดึงทุก 10 นาที ตำแหน่งค่าใช้จุดคั่น เช่น <span class="mono">data.0.value</span> คือค่า value ของรายการแรกใน data</div></div>

        <div class="col-md-6">
            <input type="hidden" name="is_public" value="0">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" name="is_public" value="1" id="stPublic" data-default="1" @checked(old('is_public', '1'))>
                <label class="form-check-label" for="stPublic">แสดงบนเว็บประชาชน</label>
            </div>
        </div>
        <div class="col-md-6">
            <input type="hidden" name="is_active" value="0">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="stActive" data-default="1" @checked(old('is_active', '1'))>
                <label class="form-check-label" for="stActive">ใช้งาน</label>
            </div>
        </div>
    </div>
</x-form-modal>

@push('scripts')
    <script>
        (function () {
            const modal = document.getElementById('stationModal');
            const mode = document.getElementById('stMode');
            const sync = () => modal.querySelectorAll('.st-json').forEach(el => el.classList.toggle('d-none', mode.value !== 'json'));
            mode.addEventListener('change', sync); sync();
            let pick = null, marker = null;
            modal.addEventListener('shown.bs.modal', () => {
                const lat = document.getElementById('stLat'), lng = document.getElementById('stLng');
                if (!pick) {
                    pick = FloodMap.create(document.getElementById('stationPickMap'), { center: @json([$province->center_lat ?? 13.7563, $province->center_lng ?? 100.5018]), zoom: {{ $province->default_zoom ?? 10 }} });
                    pick.on('click', e => { lat.value = e.latlng.lat.toFixed(7); lng.value = e.latlng.lng.toFixed(7); place(); });
                }
                const place = () => {
                    const a = parseFloat(lat.value), b = parseFloat(lng.value);
                    if (isNaN(a) || isNaN(b)) { if (marker) { pick.removeLayer(marker); marker = null; } return; }
                    if (!marker) marker = L.marker([a, b]).addTo(pick); else marker.setLatLng([a, b]);
                };
                pick.invalidateSize(); place();
                if (marker) pick.setView(marker.getLatLng(), 13);
                sync();
            });
        })();
    </script>
@endpush
