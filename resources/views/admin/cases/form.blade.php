@extends('layouts.app')
@section('title', $case->exists ? 'แก้ไข '.$case->code : 'รับแจ้งทางโทรศัพท์')

@push('head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
@endpush

@section('content')
    @use('App\Support\CaseOptions')
    @php
        $isEdit = $case->exists;
        $vul = old('vulnerable', $case->vulnerable ?? []);
        $needs = old('needs', $case->needs ?? []);
    @endphp

    <x-page-head :title="$isEdit ? 'แก้ไข '.$case->code : 'รับแจ้งทางโทรศัพท์'" :sub="$isEdit ? 'การแก้ไขบันทึกในเหตุการณ์ของเคส' : 'คีย์ขณะคุยสาย ช่องที่มีดาวจำเป็น ที่เหลือกรอกเท่าที่ได้'">
        <a href="{{ $isEdit ? route('cases.show', $case) : route('cases.index') }}" class="btn btn-light">ยกเลิก</a>
    </x-page-head>

    <form method="POST" action="{{ $isEdit ? route('cases.update', $case) : route('cases.store') }}" enctype="multipart/form-data" class="row g-3" novalidate>
        @csrf
        @if($isEdit) @method('PUT') @endif

        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-person text-primary"></i> ผู้แจ้ง</div>
                <div class="card-body row g-3">
                    @unless($isEdit)
                        <div class="col-12">
                            <label class="form-label req">ช่องทาง</label>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach(CaseOptions::SOURCES as $k => [$label, $icon])
                                    @continue(in_array($k, ['web', 'proactive'], true))
                                    <input type="radio" class="btn-check" name="source" id="src{{ $k }}" value="{{ $k }}" @checked(old('source', $case->source) === $k)>
                                    <label class="btn btn-outline-primary btn-sm" for="src{{ $k }}"><i class="bi bi-{{ $icon }}"></i> {{ $label }}</label>
                                @endforeach
                            </div>
                        </div>
                    @endunless
                    <div class="col-md-6">
                        <label class="form-label req">ชื่อผู้แจ้ง</label>
                        <input name="requester_name" class="form-control @error('requester_name') is-invalid @enderror" value="{{ old('requester_name', $case->requester_name) }}">
                        @error('requester_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label {{ $isEdit ? '' : 'req' }}">เบอร์โทร</label>
                        <input name="requester_phone" type="tel" class="form-control @error('requester_phone') is-invalid @enderror" value="{{ old('requester_phone') }}" placeholder="{{ $isEdit ? 'เว้นว่างถ้าไม่เปลี่ยน ('.$case->maskedPhone().')' : '08x-xxx-xxxx' }}">
                        @error('requester_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">คนในพื้นที่ (ถ้าแจ้งแทน)</label>
                        <input name="contact_name" class="form-control" value="{{ old('contact_name', $case->contact_name) }}" placeholder="ชื่อ">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">เบอร์คนในพื้นที่</label>
                        <input name="contact_phone" type="tel" class="form-control @error('contact_phone') is-invalid @enderror" value="{{ old('contact_phone', $case->contact_phone) }}">
                        <input type="hidden" name="on_behalf" value="{{ old('on_behalf', $case->on_behalf ? 1 : 0) }}" id="onBehalfHidden">
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-water text-primary"></i> สถานการณ์</div>
                <div class="card-body">
                    <label class="form-label req">ระดับน้ำ</label>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        @foreach(config('floodthai.water_levels') as $n => $lv)
                            <input type="radio" class="btn-check" name="water_level" id="wl{{ $n }}" value="{{ $n }}" @checked((int) old('water_level', $case->water_level) === $n)>
                            <label class="btn btn-outline-secondary btn-sm" for="wl{{ $n }}"><span class="lv-dot me-1" style="background:{{ $lv['color'] }}"></span>{{ $lv['short'] }}</label>
                        @endforeach
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-4">
                            <label class="form-label req">จำนวนคน</label>
                            <input name="people_count" type="number" min="1" max="500" class="form-control" value="{{ old('people_count', $case->people_count) }}">
                        </div>
                        <div class="col-4">
                            <label class="form-label">อยู่ชั้นที่</label>
                            <input name="floor_level" type="number" min="1" max="50" class="form-control" value="{{ old('floor_level', $case->floor_level) }}">
                        </div>
                    </div>
                    <label class="form-label">กลุ่มเปราะบาง</label>
                    <div class="chip-check mb-3">
                        @foreach(CaseOptions::VULNERABLE as $k => [$label, $icon])
                            <label><input type="checkbox" name="vulnerable[]" value="{{ $k }}" @checked(in_array($k, $vul))><span><i class="bi bi-{{ $icon }}"></i> {{ $label }}</span></label>
                        @endforeach
                    </div>
                    <label class="form-label">ต้องการ</label>
                    <div class="chip-check mb-3">
                        @foreach(CaseOptions::NEEDS as $k => [$label, $icon])
                            <label><input type="checkbox" name="needs[]" value="{{ $k }}" @checked(in_array($k, $needs))><span><i class="bi bi-{{ $icon }}"></i> {{ $label }}</span></label>
                        @endforeach
                    </div>
                    <label class="form-label">รายละเอียด</label>
                    <textarea name="needs_note" class="form-control" rows="3" maxlength="1000">{{ old('needs_note', $case->needs_note) }}</textarea>
                    @unless($isEdit)
                        <label class="form-label mt-3">รูป (ถ้ามี)</label>
                        <input type="file" name="photos[]" class="form-control" accept="image/*" multiple>
                    @endunless
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-geo-alt text-primary"></i> ตำแหน่ง</div>
                <div class="card-body">
                    <div class="input-group mb-2">
                        <input type="text" class="form-control" id="pasteLoc" placeholder="วางลิงก์ Google Maps หรือพิกัดที่ผู้แจ้งส่งมา">
                        <button type="button" class="btn btn-light" id="btnPaste" data-url="{{ route('public.help.resolve', $province) }}">ใช้</button>
                    </div>
                    <div id="pickMap" class="map-box mb-2" style="height:320px"></div>
                    <div class="row g-2 mb-2">
                        <div class="col-6"><input name="lat" id="fLat" class="form-control form-control-sm mono @error('lat') is-invalid @enderror" value="{{ old('lat', $case->lat) }}" placeholder="ละติจูด"></div>
                        <div class="col-6"><input name="lng" id="fLng" class="form-control form-control-sm mono" value="{{ old('lng', $case->lng) }}" placeholder="ลองจิจูด"></div>
                    </div>
                    <input type="hidden" name="location_source" value="{{ old('location_source', $case->location_source ?? 'staff') }}">
                    <input type="hidden" name="location_raw" id="fRaw" value="{{ old('location_raw', $case->location_raw) }}">
                    <div class="form-text mb-3">คลิกบนแผนที่หรือลากหมุดตามที่ผู้แจ้งบอก</div>
                    <label class="form-label">บ้านเลขที่ / ซอย / หมู่บ้าน</label>
                    <input name="address_text" class="form-control mb-2" value="{{ old('address_text', $case->address_text) }}">
                    <label class="form-label">จุดสังเกต</label>
                    <input name="landmark" class="form-control" value="{{ old('landmark', $case->landmark) }}">
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    @unless($isEdit)
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="queue_now" value="1" id="queueNow" @checked(old('queue_now', true))>
                            <label class="form-check-label" for="queueNow">คัดกรองแล้ว ส่งเข้าคิวรอทีมทันที</label>
                        </div>
                    @endunless
                    <button class="btn btn-primary btn-lg w-100" type="submit"><i class="bi bi-check2 me-1"></i>{{ $isEdit ? 'บันทึกการแก้ไข' : 'บันทึกเคส' }}</button>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="{{ asset('js/map.js') }}?v={{ filemtime(public_path('js/map.js')) }}"></script>
    <script>
        (function () {
            const lat = document.getElementById('fLat'), lng = document.getElementById('fLng');
            const map = FloodMap.picker(document.getElementById('pickMap'), lat, lng, {
                center: [{{ $province->center_lat ?? 13.7563 }}, {{ $province->center_lng ?? 100.5018 }}], zoom: {{ $province->default_zoom ?? 10 }},
                areasUrl: @json(route('dashboard.areas')) + '?level=district',
            });
            document.getElementById('btnPaste').addEventListener('click', async e => {
                const text = document.getElementById('pasteLoc').value.trim(); if (!text) return;
                const res = await fetch(e.currentTarget.dataset.url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify({ text }) });
                const d = await res.json();
                if (!d.ok) { alert(d.message || 'อ่านพิกัดไม่ได้'); return; }
                lat.value = d.lat; lng.value = d.lng; document.getElementById('fRaw').value = text.slice(0, 500);
                map.fire('click', { latlng: L.latLng(d.lat, d.lng) }); map.setView([d.lat, d.lng], 16);
            });
            const cn = document.querySelector('[name=contact_name]'), cp = document.querySelector('[name=contact_phone]');
            const sync = () => document.getElementById('onBehalfHidden').value = (cn.value || cp.value) ? 1 : 0;
            cn.addEventListener('input', sync); cp.addEventListener('input', sync);
        })();
    </script>
@endpush
