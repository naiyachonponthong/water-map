@extends('layouts.app')
@section('title', 'ครัวเรือนกลุ่มเปราะบาง')

@section('content')
    @use('App\Support\RiskOptions')

    <x-page-head title="ครัวเรือนกลุ่มเปราะบาง" sub="ผู้ป่วยติดเตียง ผู้พิการ ผู้สูงอายุอยู่ลำพัง ระบบเปิดเคสตรวจเยี่ยมให้เองเมื่อน้ำถึงระดับ{{ config('floodthai.water_levels.'.$trigger.'.short') }}">
        <button class="btn btn-soft" data-bs-toggle="modal" data-bs-target="#hhImportModal"><i class="bi bi-upload me-1"></i>นำเข้า CSV</button>
        <a href="{{ route('vulnerable.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>เพิ่มครัวเรือน</a>
    </x-page-head>

    <div class="alert alert-info small"><i class="bi bi-shield-lock"></i>
        <div>ข้อมูลส่วนนี้เห็นเฉพาะผู้มีสิทธิ์ ไม่แสดงบนเว็บประชาชน เบอร์โทรเก็บแบบเข้ารหัส ควรบันทึกความยินยอมของครัวเรือนหรือผู้ดูแลทุกครั้ง</div>
    </div>

    @if(session('import_errors'))
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i>
            <div><div class="fw-600 mb-1">บางแถวนำเข้าไม่ได้</div>
                <ul class="mb-0 small ps-3">@foreach(session('import_errors') as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        </div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><x-stat label="ครัวเรือนในทะเบียน" :value="$stats['total']" icon="house-heart" tint="danger" /></div>
        <div class="col-6 col-lg-3"><x-stat label="มีเคสตรวจเยี่ยมเปิดอยู่" :value="$stats['open']" icon="life-preserver" tint="warning" /></div>
        <div class="col-6 col-lg-3"><x-stat label="เยี่ยมแล้ววันนี้" :value="$stats['checked_today']" icon="check2-circle" tint="success" /></div>
        <div class="col-6 col-lg-3"><x-stat label="ยังไม่มีพิกัด" :value="$stats['no_location']" icon="geo" tint="slate" /></div>
    </div>

    <form class="d-flex flex-wrap gap-2 mb-3" method="GET">
        <select name="district" class="form-select form-select-sm" style="width:150px" onchange="this.form.submit()">
            <option value="">ทุกอำเภอ</option>
            @foreach($districts as $d)<option value="{{ $d->id }}" @selected(request('district') == $d->id)>{{ $d->name_th }}</option>@endforeach
        </select>
        <select name="condition" class="form-select form-select-sm" style="width:200px" onchange="this.form.submit()">
            <option value="">ทุกภาวะ</option>
            @foreach(RiskOptions::CONDITIONS as $k => [$l])<option value="{{ $k }}" @selected(request('condition') === $k)>{{ $l }}</option>@endforeach
        </select>
        <select name="check" class="form-select form-select-sm" style="width:190px" onchange="this.form.submit()">
            <option value="">ผลเยี่ยมล่าสุด: ทั้งหมด</option>
            <option value="never" @selected(request('check') === 'never')>ยังไม่เคยเยี่ยม</option>
            @foreach(RiskOptions::CHECK as $k => [$l])<option value="{{ $k }}" @selected(request('check') === $k)>{{ $l }}</option>@endforeach
        </select>
        <select name="consent" class="form-select form-select-sm" style="width:150px" onchange="this.form.submit()">
            <option value="">ความยินยอม: ทั้งหมด</option>
            <option value="no" @selected(request('consent') === 'no')>ยังไม่บันทึก</option>
        </select>
        <div class="input-group input-group-sm ms-lg-auto" style="width:220px">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="search" name="q" class="form-control" placeholder="ชื่อ ที่อยู่" value="{{ request('q') }}">
        </div>
    </form>

    <div class="card">
        @forelse($households as $h)
            <a href="{{ route('vulnerable.show', $h) }}" class="list-row text-reset text-decoration-none {{ $h->is_active ? '' : 'opacity-50' }}">
                <span class="app-ico danger" style="width:40px;height:40px;border-radius:12px"><i class="bi bi-{{ RiskOptions::CONDITIONS[$h->conditions[0] ?? 'other'][1] ?? 'house-heart' }}"></i></span>
                <div class="flex-grow-1 min-w-0">
                    <div class="d-flex flex-wrap align-items-center gap-1">
                        <span class="fw-600 me-1">{{ $h->head_name }}</span>
                        <span class="chip"><i class="bi bi-people"></i> {{ $h->members }}</span>
                        @if($h->openCase && $h->openCase->isOpen())<span class="chip chip-danger"><i class="bi bi-life-preserver"></i> {{ $h->openCase->code }} {{ $h->openCase->statusLabel() }}</span>@endif
                        @unless($h->consent_at)<span class="chip chip-warning" title="ยังไม่บันทึกความยินยอม"><i class="bi bi-file-earmark-x"></i></span>@endunless
                        @if($h->lat === null)<span class="chip" title="ยังไม่มีพิกัด"><i class="bi bi-geo"></i> ไม่มีพิกัด</span>@endif
                    </div>
                    <div class="d-flex flex-wrap gap-1 my-1">
                        @foreach($h->conditionLabels() as $c)<span class="hh-cond">{{ $c }}</span>@endforeach
                    </div>
                    <div class="small text-muted"><i class="bi bi-geo-alt"></i> {{ $h->address ? $h->address.' ' : '' }}{{ $h->areaLabel() }}</div>
                </div>
                <div class="text-end small flex-shrink-0">
                    @if($h->last_checked_at)
                        <span class="chip {{ RiskOptions::CHECK[$h->last_check_status][1] ?? '' }}">{{ RiskOptions::CHECK[$h->last_check_status][0] ?? '' }}</span>
                        <div class="text-muted mt-1">{{ thai_date($h->last_checked_at, 'ago') }}</div>
                    @else
                        <span class="text-muted">ยังไม่เคยเยี่ยม</span>
                    @endif
                </div>
            </a>
        @empty
            <x-empty icon="house-heart" title="ยังไม่มีครัวเรือนในทะเบียน" text="นำเข้าจากข้อมูล อสม. หรือ รพ.สต. ด้วยไฟล์ CSV หรือเพิ่มทีละครัวเรือน">
                <a href="{{ route('vulnerable.template') }}" class="btn btn-light"><i class="bi bi-download me-1"></i>ไฟล์ตัวอย่าง</a>
            </x-empty>
        @endforelse
    </div>
    <div class="mt-3">{{ $households->links() }}</div>

    <x-form-modal id="hhImportModal" title="นำเข้าครัวเรือนเปราะบาง" :action="route('vulnerable.import')" submit="นำเข้า" :files="true">
        <div class="mb-3">
            <label class="form-label">ไฟล์ CSV</label>
            <input type="file" name="file" class="form-control" accept=".csv,.txt" required>
        </div>
        <div class="small text-muted mb-2">รองรับไฟล์จาก Excel (UTF-8 หรือ TIS-620) ภาวะหลายอย่างคั่นด้วยจุลภาค ระบบหาตำบลจากพิกัดหรือชื่อตำบลให้เอง แถวที่ชื่อและตำบลหรือที่อยู่ซ้ำกับที่มีอยู่จะถูกข้าม</div>
        <a href="{{ route('vulnerable.template') }}" class="small"><i class="bi bi-download"></i> ดาวน์โหลดไฟล์ตัวอย่าง</a>
    </x-form-modal>
@endsection
