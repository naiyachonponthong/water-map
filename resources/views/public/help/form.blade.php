@extends('layouts.public')
@section('title', 'ขอความช่วยเหลือ '.$province->fullName())

@push('head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
@endpush

@section('content')
    <header class="pub-head has-steps">
        <div class="inner">
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('public.province', $province) }}" class="text-white fs-4 me-1" aria-label="กลับ"><i class="bi bi-arrow-left"></i></a>
                <div>
                    <div class="fw-bold lh-sm">ขอความช่วยเหลือ</div>
                    <div class="small opacity-75">{{ $province->fullName() }} · ใช้เวลาประมาณ 1 นาที</div>
                </div>
                <a href="tel:{{ preg_replace('/\D+/', '', $hotline) }}" class="btn btn-sm btn-danger ms-auto"><i class="bi bi-telephone-fill"></i> {{ $hotline }}</a>
            </div>
            <div class="help-steps mt-3" id="stepBar">
                @foreach(['ตำแหน่ง', 'สถานการณ์', 'ต้องการอะไร', 'ติดต่อ'] as $i => $s)
                    <div class="hs-item {{ $i === 0 ? 'active' : '' }}" data-step-dot="{{ $i }}"><span>{{ $i + 1 }}</span>{{ $s }}</div>
                @endforeach
            </div>
        </div>
    </header>

    <div class="pub-wrap">
        <form method="POST" action="{{ route('public.help.store', $province) }}" enctype="multipart/form-data" id="helpForm" class="pub-float" novalidate>
            @csrf
            <input type="text" name="website" class="d-none" tabindex="-1" autocomplete="off" aria-hidden="true">

            {{-- 1 ตำแหน่ง --}}
            <section class="card mb-3 help-step" data-step="0">
                <div class="card-body">
                    <h2 class="h5 fw-bold mb-1">1. คุณอยู่ที่ไหน</h2>
                    <p class="small text-muted mb-3">เลือกวิธีใดก็ได้ ตำแหน่งที่แม่นยำช่วยให้ทีมไปถึงเร็วขึ้น</p>

                    <div class="d-grid gap-2 mb-3" style="grid-template-columns:1fr 1fr">
                        <button type="button" class="btn btn-primary" id="btnGps"><i class="bi bi-crosshair me-1"></i>ตำแหน่งปัจจุบัน</button>
                        <button type="button" class="btn btn-soft" id="btnPin"><i class="bi bi-pin-map me-1"></i>ปักบนแผนที่</button>
                    </div>

                    <div id="helpMap" class="map-box sm mb-2" style="height:260px"></div>
                    <div class="small mb-3" id="locStatus"><span class="text-muted">ยังไม่ได้เลือกตำแหน่ง</span></div>

                    <label class="form-label small">มีลิงก์ Google Maps / LINE หรือพิกัด? วางที่นี่</label>
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" id="pasteLoc" placeholder="https://maps.app.goo.gl/... หรือ 13.69, 101.07">
                        <button type="button" class="btn btn-light" id="btnPaste" data-url="{{ route('public.help.resolve', $province) }}">ใช้</button>
                    </div>

                    <input type="hidden" name="lat" value="{{ old('lat') }}">
                    <input type="hidden" name="lng" value="{{ old('lng') }}">
                    <input type="hidden" name="location_source" value="{{ old('location_source', 'gps') }}">
                    <input type="hidden" name="location_raw" value="{{ old('location_raw') }}">
                    <input type="hidden" name="accuracy_m" value="{{ old('accuracy_m') }}">

                    <label class="form-label small">บ้านเลขที่ / ซอย / หมู่บ้าน</label>
                    <input name="address_text" class="form-control mb-2" value="{{ old('address_text') }}" placeholder="เช่น 45/2 หมู่ 3 ซอยวัดใหม่">
                    <div class="row g-2">
                        <div class="col-8">
                            <label class="form-label small">จุดสังเกต</label>
                            <input name="landmark" class="form-control" value="{{ old('landmark') }}" placeholder="เช่น บ้านหลังคาสีฟ้า ข้างร้านค้า">
                        </div>
                        <div class="col-4">
                            <label class="form-label small">อยู่ชั้นที่</label>
                            <input name="floor_level" type="number" min="1" max="50" inputmode="numeric" class="form-control" value="{{ old('floor_level') }}">
                        </div>
                    </div>
                </div>
            </section>

            {{-- 2 สถานการณ์ --}}
            <section class="card mb-3 help-step d-none" data-step="1">
                <div class="card-body">
                    <h2 class="h5 fw-bold mb-3">2. น้ำสูงแค่ไหน</h2>
                    <div class="level-grid mb-4">
                        @foreach($levels as $n => $lv)
                            <label class="level-card">
                                <input type="radio" name="water_level" value="{{ $n }}" @checked(old('water_level') == $n) required>
                                <span class="lc-bar" style="background:{{ $lv['color'] }}"></span>
                                <span class="lc-text"><b>{{ $lv['label'] }}</b><small>{{ $lv['range'] }}</small></span>
                            </label>
                        @endforeach
                    </div>

                    <label class="form-label">มีกี่คน</label>
                    <div class="input-group mb-4" style="max-width:200px">
                        <button class="btn btn-light" type="button" data-step-num="-1"><i class="bi bi-dash-lg"></i></button>
                        <input type="number" name="people_count" class="form-control text-center fw-bold" min="1" max="500" inputmode="numeric" value="{{ old('people_count', 1) }}">
                        <button class="btn btn-light" type="button" data-step-num="1"><i class="bi bi-plus-lg"></i></button>
                    </div>

                    <label class="form-label">มีคนกลุ่มนี้อยู่ด้วยไหม</label>
                    <div class="chip-check">
                        @foreach($vulnerable as $key => [$label, $icon])
                            <label><input type="checkbox" name="vulnerable[]" value="{{ $key }}" @checked(in_array($key, old('vulnerable', [])))><span><i class="bi bi-{{ $icon }}"></i> {{ $label }}</span></label>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- 3 ต้องการอะไร --}}
            <section class="card mb-3 help-step d-none" data-step="2">
                <div class="card-body">
                    <h2 class="h5 fw-bold mb-3">3. ต้องการความช่วยเหลืออะไร</h2>
                    <div class="chip-check big mb-3">
                        @foreach($needs as $key => [$label, $icon])
                            <label><input type="checkbox" name="needs[]" value="{{ $key }}" @checked(in_array($key, old('needs', [])))><span><i class="bi bi-{{ $icon }}"></i> {{ $label }}</span></label>
                        @endforeach
                    </div>
                    <label class="form-label small">รายละเอียดเพิ่มเติม</label>
                    <textarea name="needs_note" class="form-control mb-3" rows="3" maxlength="1000" placeholder="เช่น ผู้ป่วยต้องใช้ออกซิเจน ยาเบาหวานเหลือ 1 วัน">{{ old('needs_note') }}</textarea>

                    <label class="form-label small">แนบรูป (ไม่บังคับ สูงสุด 3 รูป)</label>
                    <input type="file" name="photos[]" class="form-control" accept="image/*" multiple id="photoInput">
                    <div class="d-flex gap-2 mt-2" id="photoPreview"></div>
                </div>
            </section>

            {{-- 4 ติดต่อ --}}
            <section class="card mb-3 help-step d-none" data-step="3">
                <div class="card-body">
                    <h2 class="h5 fw-bold mb-3">4. ติดต่อกลับที่ใคร</h2>
                    <label class="form-label req">ชื่อผู้แจ้ง</label>
                    <input name="requester_name" class="form-control mb-3" value="{{ old('requester_name') }}" required autocomplete="name">
                    <label class="form-label req">เบอร์โทร</label>
                    <input name="requester_phone" type="tel" inputmode="tel" class="form-control mb-3" value="{{ old('requester_phone') }}" required autocomplete="tel" placeholder="08x-xxx-xxxx">

                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="on_behalf" value="1" id="onBehalf" @checked(old('on_behalf'))>
                        <label class="form-check-label" for="onBehalf">แจ้งแทนคนอื่น (เช่น ญาติที่อยู่ในพื้นที่)</label>
                    </div>
                    <div id="behalfFields" class="{{ old('on_behalf') ? '' : 'd-none' }} border rounded-4 p-3 mb-3">
                        <div class="small text-muted mb-2">คนที่อยู่ในพื้นที่ (ให้ทีมโทรหาตอนใกล้ถึง)</div>
                        <input name="contact_name" class="form-control mb-2" placeholder="ชื่อ" value="{{ old('contact_name') }}">
                        <input name="contact_phone" type="tel" inputmode="tel" class="form-control" placeholder="เบอร์โทร" value="{{ old('contact_phone') }}">
                    </div>

                    <div class="alert alert-primary small mb-0">
                        <i class="bi bi-shield-check"></i>
                        <div>เบอร์โทรเห็นเฉพาะเจ้าหน้าที่ศูนย์และทีมที่รับงาน ไม่แสดงบนเว็บ หลังส่งจะได้ลิงก์ติดตามสถานะ ส่งต่อให้ญาติได้</div>
                    </div>
                </div>
            </section>

            @include('partials.privacy-note', ['text' => 'ข้อมูลใช้เพื่อส่งทีมช่วยเหลือเท่านั้น ทีมที่รับงานเห็นเบอร์โทรของคุณ ไม่แสดงต่อสาธารณะ'])
            <div class="d-flex gap-2 help-nav">
                <button type="button" class="btn btn-light btn-lg d-none" id="btnBack"><i class="bi bi-arrow-left"></i></button>
                <button type="button" class="btn btn-primary btn-lg flex-grow-1" id="btnNext">ถัดไป <i class="bi bi-arrow-right"></i></button>
                <button type="submit" class="btn btn-danger btn-lg flex-grow-1 d-none" id="btnSubmit"><i class="bi bi-send-fill me-1"></i>ส่งขอความช่วยเหลือ</button>
            </div>
            <div class="small text-muted text-center mt-2" id="draftNote"></div>
        </form>

        <div class="text-center small mt-4">
            <a href="{{ route('public.track.lookup') }}">เคยส่งคำขอแล้ว? ติดตามสถานะ</a>
        </div>
    </div>

    <script>
        window.HELP_CFG = {
            center: [{{ $province->center_lat ?? 13.7563 }}, {{ $province->center_lng ?? 100.5018 }}],
            zoom: {{ $province->default_zoom ?? 10 }},
            draftKey: 'flood-help-{{ $province->slug }}',
            hasErrors: {{ $errors->any() ? 'true' : 'false' }},
        };
    </script>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="{{ asset('js/help-form.js') }}?v={{ filemtime(public_path('js/help-form.js')) }}"></script>
@endpush
