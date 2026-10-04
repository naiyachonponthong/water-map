@extends('layouts.app')
@section('title', 'ตั้งค่าเริ่มต้น')
@push('head')
    <link rel="stylesheet" href="{{ asset('css/setup.css') }}">
@endpush
@section('content')
    <section class="setup-hero">
        <div><span class="setup-eyebrow">FLOODTHAI · {{ config('floodthai.version') }}</span><h1>เริ่มต้นใช้งานอย่างมั่นใจ</h1><p>ตั้งค่าทีละขั้นสำหรับ{{ $province->fullName() }} กลับมาทำต่อได้ทุกเมื่อ</p></div>
        <div class="setup-progress"><strong>{{ $done }}<small> / {{ $total }}</small></strong><span>รายการที่ผ่าน</span><progress value="{{ $done }}" max="{{ $total }}" aria-label="ความคืบหน้าการตั้งค่า"></progress></div>
    </section>
    <div class="setup-layout">
        <nav class="setup-nav" aria-label="ขั้นตอนตั้งค่า">
            @foreach($steps as $number => $item)
                @php $passed = collect($item['checks'])->every(fn ($check) => $check['ok']); @endphp
                <a href="{{ route('admin.setup', ['step' => $number]) }}" class="setup-step {{ $step === $number ? 'is-active' : '' }} {{ $passed ? 'is-done' : '' }}" @if($step === $number) aria-current="step" @endif>
                    <span class="setup-number"><i class="bi bi-{{ $passed ? 'check-lg' : $item['icon'] }}" aria-hidden="true"></i></span><span><small>ขั้นตอน {{ $number }}</small><strong>{{ $item['title'] }}</strong></span>
                </a>
            @endforeach
            <p class="small text-muted px-3 mt-3">ข้อมูลและผลตรวจแยกตามจังหวัด<br>สถานะศูนย์: {{ $province->command_open ? 'เปิดอยู่' : 'ยังไม่เปิด' }}</p>
        </nav>
        <section class="card setup-panel">
            <div class="card-body">
                <span class="setup-eyebrow text-primary">ขั้นตอน {{ $step }} จาก 5</span><h2 class="h4 mt-2 mb-4">{{ $steps[$step]['title'] }}</h2>
                @if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                @if($step === 1)
                    <p class="text-muted">เลือกจังหวัดที่หน่วยงานจะดูแล แล้วตั้งค่าข้อมูลและเจ้าหน้าที่ของจังหวัดนั้น</p>
                    <form method="POST" action="{{ route('admin.setup.province') }}">@csrf
                        <label for="setupProvince" class="form-label">จังหวัด</label><select id="setupProvince" name="province_id" class="form-select form-select-lg mb-4" required>
                            @foreach($provinces as $option)<option value="{{ $option->id }}" @selected((int) old('province_id', $province->id) === $option->id)>{{ $option->name_th }}</option>@endforeach
                        </select><button class="btn btn-primary" type="submit">เลือกจังหวัดและตั้งค่าต่อ <i class="bi bi-arrow-right"></i></button>
                    </form>
                @elseif($step === 2)
                    <form method="POST" action="{{ route('admin.setup.basics') }}">@csrf<input type="hidden" name="province_id" value="{{ $province->id }}">
                        <div class="mb-3"><label class="form-label" for="setupOrganization">หน่วยงานผู้รับผิดชอบ</label><input id="setupOrganization" name="privacy_controller" class="form-control" maxlength="200" value="{{ old('privacy_controller', \App\Support\Settings::get('privacy_controller', $province->id, '')) }}" placeholder="เช่น ศูนย์สั่งการจังหวัด / องค์กรปกครองส่วนท้องถิ่น" required></div>
                        <div class="mb-3"><label class="form-label" for="setupContact">ช่องทางติดต่อเรื่องข้อมูลส่วนบุคคล</label><input id="setupContact" name="privacy_contact" class="form-control" maxlength="300" value="{{ old('privacy_contact', \App\Support\Settings::get('privacy_contact', $province->id, '')) }}" placeholder="เบอร์โทร อีเมล หรือช่องทางติดต่อหน่วยงาน" required></div>
                        <div class="mb-3"><label class="form-label" for="setupHotline">สายด่วนหลักของจังหวัด</label><input id="setupHotline" name="hotline" class="form-control" inputmode="tel" maxlength="20" value="{{ old('hotline', \App\Support\Settings::get('hotline', $province->id, '')) }}" required><div class="form-text">ตรวจว่าเบอร์นี้มีผู้รับสายก่อนเปิดรับเหตุ</div></div>
                        <div class="row g-3 mb-4"><div class="col-sm-4"><label class="form-label" for="setupLat">ละติจูด</label><input id="setupLat" name="center_lat" type="number" step="any" min="5" max="21" class="form-control" value="{{ old('center_lat', $province->center_lat) }}" required></div><div class="col-sm-4"><label class="form-label" for="setupLng">ลองจิจูด</label><input id="setupLng" name="center_lng" type="number" step="any" min="97" max="106" class="form-control" value="{{ old('center_lng', $province->center_lng) }}" required></div><div class="col-sm-4"><label class="form-label" for="setupZoom">ระดับซูมแผนที่</label><input id="setupZoom" name="default_zoom" type="number" min="7" max="15" class="form-control" value="{{ old('default_zoom', $province->default_zoom) }}" required></div></div>
                        <button class="btn btn-primary" type="submit">บันทึกและทำขั้นต่อไป <i class="bi bi-arrow-right"></i></button>
                        <a href="{{ route('admin.settings.index', ['tab' => 'contacts']) }}" class="btn btn-light mt-2 mt-sm-0">เพิ่มเบอร์ฉุกเฉินในพื้นที่</a>
                    </form>
                @elseif($step === 3)
                    <p class="text-muted">ใช้หน้าจัดการเดิมเพื่อสร้างเจ้าหน้าที่และนำเข้าขอบเขต แล้วกลับมาดูผลตรวจที่นี่</p>
                    <div class="setup-links mb-4"><a class="btn btn-soft" href="{{ route('admin.users.index') }}"><i class="bi bi-person-plus"></i> ตั้งเจ้าหน้าที่</a><a class="btn btn-soft" href="{{ route('admin.areas.index') }}"><i class="bi bi-map"></i> นำเข้าพื้นที่</a><a class="btn btn-soft" href="{{ route('teams.index') }}"><i class="bi bi-people"></i> เตรียมทีมกู้ภัย</a><a class="btn btn-soft" href="{{ route('shelters.index') }}"><i class="bi bi-house-heart"></i> เตรียมศูนย์พักพิง</a></div>
                @elseif($step === 4)
                    <p class="text-muted">ตั้งงานอัตโนมัติทุกนาทีในแผงโฮสต์ แล้วรอ 1–2 นาทีเพื่อดูหลักฐานการทำงานล่าสุด</p>
                    @include('partials.hosting-cron')
                    <a href="{{ route('admin.setup', ['step' => 4]) }}" class="btn btn-light my-3"><i class="bi bi-arrow-clockwise"></i> ตรวจอีกครั้ง</a>
                @else
                    <p class="text-muted">ติ๊กเฉพาะขั้นตอนที่ทดลองแล้ว ผลนี้เป็นบันทึกการตรวจของผู้ดูแล ไม่ใช่การรับรองจากระบบ</p>
                    <form method="POST" action="{{ route('admin.setup.review') }}">@csrf<input type="hidden" name="province_id" value="{{ $province->id }}">
                        @foreach(['workflow' => 'ทดลองแจ้งเหตุ รับงานของทีม และปิดงานครบในระบบทดสอบ', 'backup' => 'สำรองฐานข้อมูล รูป และ .env พร้อมทดลองกู้คืนในระบบแยก', 'sources' => 'ตรวจสถานี พยากรณ์ สายด่วน ศูนย์พักพิง และช่องทางประกาศแล้ว'] as $key => $label)
                            <label class="setup-confirm"><input type="checkbox" name="{{ $key }}" value="1" @checked(old($key, $review[$key] ?? false))><span>{{ $label }}</span></label>
                        @endforeach
                        @if(isset($review['confirmed_at']))<p class="small text-muted mt-3">บันทึกครั้งล่าสุด {{ thai_date($review['confirmed_at']) }}</p>@endif
                        <button type="submit" class="btn btn-primary mt-3">บันทึกผลที่ทดลองแล้ว</button>
                    </form>
                @endif
                @if($step !== 1)
                    <ul class="setup-checks mt-4">@foreach($steps[$step]['checks'] as $check)<li><i class="bi bi-{{ $check['ok'] ? 'check-circle-fill text-success' : 'circle text-muted' }}" aria-hidden="true"></i><div><strong>{{ $check['label'] }}</strong><span class="badge {{ $check['ok'] ? 'text-bg-success' : 'text-bg-light' }} ms-2">{{ $check['ok'] ? 'ผ่าน' : 'ยังไม่ผ่าน' }}</span><p>{{ $check['help'] }}</p></div></li>@endforeach</ul>
                @endif
                @if($step === 5)
                    <div class="alert {{ $done === $total ? 'alert-success' : 'alert-warning' }} mt-4">{{ $done === $total ? 'รายการตั้งค่าผ่านครบแล้ว ตรวจความพร้อมของเจ้าหน้าที่ก่อนเปิดศูนย์' : 'ยังมีรายการที่ต้องเตรียม — กลับไปทำขั้นตอนที่ยังไม่ผ่านให้ครบ' }}</div>
                    <a href="{{ route('admin.settings.index', ['tab' => 'switches']) }}" class="btn btn-outline-primary">ดูสวิตช์เปิดศูนย์</a><a href="{{ route('public.province', $province) }}" target="_blank" rel="noopener" class="btn btn-light">ดูหน้าเว็บประชาชน <i class="bi bi-box-arrow-up-right"></i></a>
                @endif
                <div class="setup-footer mt-4 pt-3 border-top">
                    @if($step > 1)<a href="{{ route('admin.setup', ['step' => $step - 1]) }}" class="btn btn-light"><i class="bi bi-arrow-left"></i> ย้อนกลับ</a>@endif
                    @if($step > 1 && $step < 5)<a href="{{ route('admin.setup', ['step' => $step + 1]) }}" class="btn btn-outline-primary ms-auto">ขั้นถัดไป <i class="bi bi-arrow-right"></i></a>@endif
                </div>
            </div>
        </section>
    </div>
@endsection
@push('scripts')
    <script src="{{ asset('js/setup.js') }}"></script>
@endpush
