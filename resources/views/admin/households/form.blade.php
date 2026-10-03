@extends('layouts.app')
@section('title', $h->exists ? 'แก้ไขครัวเรือน' : 'เพิ่มครัวเรือนเปราะบาง')

@push('head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
@endpush

@section('content')
    @use('App\Support\RiskOptions')

    <x-page-head :title="$h->exists ? 'แก้ไข '.$h->head_name : 'เพิ่มครัวเรือนเปราะบาง'" sub="ข้อมูลใช้เพื่อให้ทีมไปถึงก่อนน้ำมา เห็นเฉพาะเจ้าหน้าที่ที่มีสิทธิ์">
        <a href="{{ $h->exists ? route('vulnerable.show', $h) : route('vulnerable.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>กลับ</a>
    </x-page-head>

    @if($errors->any())
        <div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i><div>{{ $errors->first() }}</div></div>
    @endif

    <form method="POST" action="{{ $h->exists ? route('vulnerable.update', $h) : route('vulnerable.store') }}" class="row g-3" novalidate>
        @csrf
        @if($h->exists) @method('PUT') @endif

        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-person text-primary"></i> ครัวเรือน</div>
                <div class="card-body row g-3">
                    <div class="col-md-8">
                        <label class="form-label">ชื่อผู้ที่ต้องดูแล / หัวหน้าครัวเรือน <span class="text-danger">*</span></label>
                        <input name="head_name" class="form-control @error('head_name') is-invalid @enderror" maxlength="120" required value="{{ old('head_name', $h->head_name) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">จำนวนคนในบ้าน</label>
                        <input name="members" type="number" min="1" max="50" class="form-control" value="{{ old('members', $h->members) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">เบอร์โทร</label>
                        <input name="phone" type="tel" inputmode="tel" class="form-control mono" maxlength="20" value="{{ old('phone', $h->phone) }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">ภาวะที่ต้องดูแล <span class="text-danger">*</span></label>
                        @php $conds = old('conditions', $h->conditions ?? []); @endphp
                        <div class="cond-grid">
                            @foreach(RiskOptions::CONDITIONS as $k => [$l, $ic])
                                <input type="checkbox" class="btn-check" name="conditions[]" value="{{ $k }}" id="c_{{ $k }}" @checked(in_array($k, $conds))>
                                <label class="btn btn-outline-danger" for="c_{{ $k }}"><i class="bi bi-{{ $ic }} me-1"></i>{{ $l }}</label>
                            @endforeach
                        </div>
                        @error('conditions')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label">ข้อมูลสำหรับทีม</label>
                        <textarea name="note" class="form-control" rows="2" maxlength="1000" placeholder="เช่น ต้องใช้เปลหาม 4 คน, ใช้ถังออกซิเจนเหลือ 2 วัน, ประตูหลังบ้านเข้าทางซอย">{{ old('note', $h->note) }}</textarea>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-person-heart text-primary"></i> ผู้ดูแล / อสม.</div>
                <div class="card-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label">ชื่อผู้ดูแล</label>
                        <input name="caretaker_name" class="form-control" maxlength="120" value="{{ old('caretaker_name', $h->caretaker_name) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">เบอร์ผู้ดูแล</label>
                        <input name="caretaker_phone" type="tel" inputmode="tel" class="form-control mono" maxlength="20" value="{{ old('caretaker_phone', $h->caretaker_phone) }}">
                    </div>
                    <div class="col-12">
                        <input type="hidden" name="consent" value="0">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="consent" value="1" id="consent" @checked(old('consent', $h->consent_at ? 1 : 0))>
                            <label class="form-check-label" for="consent">ครัวเรือนหรือผู้ดูแลยินยอมให้เก็บข้อมูลเพื่อการช่วยเหลือในภัยพิบัติ</label>
                        </div>
                        @if($h->consent_at)<div class="small text-muted ms-4">บันทึกเมื่อ {{ thai_date($h->consent_at) }}</div>@endif
                    </div>
                    @if($h->exists)
                        <div class="col-12">
                            <input type="hidden" name="is_active" value="0">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="isActive" @checked(old('is_active', $h->is_active))>
                                <label class="form-check-label" for="isActive">ยังอยู่ในทะเบียน (ปิดเมื่อย้ายออกหรือเสียชีวิต)</label>
                            </div>
                        </div>
                    @else
                        <input type="hidden" name="is_active" value="1">
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-geo-alt text-primary"></i> ตำแหน่งบ้าน</div>
                <div class="card-body">
                    <div id="pickMap" class="map-box mb-2" style="height:300px"></div>
                    <div class="row g-2 mb-2">
                        <div class="col-6"><input name="lat" id="fLat" class="form-control form-control-sm mono @error('lat') is-invalid @enderror" value="{{ old('lat', $h->lat) }}" placeholder="ละติจูด"></div>
                        <div class="col-6"><input name="lng" id="fLng" class="form-control form-control-sm mono" value="{{ old('lng', $h->lng) }}" placeholder="ลองจิจูด"></div>
                    </div>
                    <div class="form-text mb-3">คลิกบนแผนที่ ถ้าไม่รู้พิกัดให้เลือกตำบล ระบบจะใช้กลางตำบลแทน</div>
                    <label class="form-label">ตำบล</label>
                    <select name="subdistrict_id" class="form-select mb-2" data-search data-placeholder="หาจากพิกัดให้อัตโนมัติ">
                        <option value="">หาจากพิกัด</option>
                        @foreach($subdistricts as $s)<option value="{{ $s->id }}" @selected(old('subdistrict_id', $h->subdistrict_id) == $s->id)>ต.{{ $s->name_th }} อ.{{ $s->district?->name_th }}</option>@endforeach
                    </select>
                    <label class="form-label">บ้านเลขที่ / หมู่ / ซอย</label>
                    <input name="address" class="form-control" maxlength="255" value="{{ old('address', $h->address) }}">
                </div>
            </div>
            <button class="btn btn-primary btn-lg w-100" type="submit"><i class="bi bi-check2 me-1"></i>บันทึก</button>
        </div>
    </form>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="{{ asset('js/map.js') }}?v={{ filemtime(public_path('js/map.js')) }}"></script>
    <script>
        FloodMap.picker(document.getElementById('pickMap'), document.getElementById('fLat'), document.getElementById('fLng'), {
            center: [{{ $province->center_lat ?? 13.7563 }}, {{ $province->center_lng ?? 100.5018 }}], zoom: {{ $province->default_zoom ?? 10 }},
            areasUrl: @json(route('dashboard.areas')) + '?level=district',
        });
    </script>
@endpush
