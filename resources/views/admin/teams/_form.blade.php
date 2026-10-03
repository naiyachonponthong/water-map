{{-- ฟอร์มทีม ใช้ทั้งเพิ่ม (หน้ารายการ) และแก้ไข (หน้าทีม) --}}
@use('App\Support\TeamOptions')
<x-form-modal id="teamModal" title="เพิ่มทีม" :action="route('teams.store')" size="lg">
    <div class="row g-3">
        <div class="col-md-8">
            <label class="form-label req">ชื่อทีม</label>
            <input name="name" class="form-control" value="{{ old('name') }}" placeholder="เช่น มูลนิธิสว่างประชาสามัคคี หน่วยบางคล้า">
        </div>
        <div class="col-md-4">
            <label class="form-label req">ประเภท</label>
            <select name="type" class="form-select" data-default="foundation">
                @foreach(TeamOptions::TYPES as $k => $l)<option value="{{ $k }}" @selected(old('type', 'foundation') === $k)>{{ $l }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">หัวหน้าทีม</label>
            <select name="leader_id" class="form-select" data-search data-placeholder="เลือกจากผู้ใช้ในจังหวัด">
                <option value="">ยังไม่กำหนด</option>
                @foreach($leaders as $u)<option value="{{ $u->id }}" @selected(old('leader_id') == $u->id)>{{ $u->name }} · {{ phone_format($u->phone) }}</option>@endforeach
            </select>
            <div class="form-text">หัวหน้าทีมจะได้บทบาท หัวหน้าทีมกู้ภัย อัตโนมัติ</div>
        </div>
        <div class="col-md-6">
            <label class="form-label">เบอร์กลางของทีม</label>
            <input name="phone" type="tel" class="form-control" value="{{ old('phone') }}">
        </div>
        <div class="col-md-6">
            <label class="form-label">พื้นที่ประจำ</label>
            <select name="district_id" class="form-select">
                <option value="">ทั้งจังหวัด</option>
                @foreach($districts as $d)<option value="{{ $d->id }}" @selected(old('district_id') == $d->id)>{{ $d->name_th }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">ที่ตั้งฐาน</label>
            <input name="base_address" class="form-control" value="{{ old('base_address') }}" placeholder="เช่น หน้าที่ว่าการอำเภอบางคล้า">
        </div>
        <div class="col-5">
            <label class="form-label">ละติจูดฐาน</label>
            <input name="base_lat" class="form-control mono" value="{{ old('base_lat') }}">
        </div>
        <div class="col-5">
            <label class="form-label">ลองจิจูดฐาน</label>
            <input name="base_lng" class="form-control mono" value="{{ old('base_lng') }}">
        </div>
        <div class="col-2 d-flex align-items-end">
            <button type="button" class="btn btn-light w-100" title="ใช้ตำแหน่งปัจจุบัน"
                    onclick="const f=this.closest('form');navigator.geolocation&&navigator.geolocation.getCurrentPosition(p=>{f.base_lat.value=p.coords.latitude.toFixed(7);f.base_lng.value=p.coords.longitude.toFixed(7)})"><i class="bi bi-crosshair"></i></button>
        </div>
        <div class="col-12 form-text mt-1">ใช้คำนวณระยะทางเมื่อทีมยังไม่ได้ส่งพิกัดสด (วางพิกัดจาก Google Maps ได้)</div>
        <div class="col-12">
            <label class="form-label">หมายเหตุ</label>
            <input name="note" class="form-control" value="{{ old('note') }}" placeholder="เช่น ออกได้เฉพาะกลางวัน, มีนักประดาน้ำ">
        </div>
        <div class="col-12">
            <div class="form-check form-switch">
                <input type="hidden" name="is_active" value="0">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="tActive" checked data-default="1">
                <label class="form-check-label" for="tActive">เปิดใช้งาน (ให้ศูนย์มอบหมายงานได้)</label>
            </div>
        </div>
    </div>
</x-form-modal>
