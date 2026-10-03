@extends('layouts.app')
@section('title', 'ฟื้นฟูและเยียวยา')

@section('content')
    @use('App\Support\RecoveryOptions')
    @use('App\Http\Controllers\Admin\RecoveryController')

    <x-page-head title="ฟื้นฟูและเยียวยา" sub="คำร้องหลังน้ำลด เรียงบ้านเสียหายหนักและมีหลักฐานจากช่วงน้ำท่วมก่อน">
        @can('recovery.manage')
            <button class="btn btn-soft" data-bs-toggle="modal" data-bs-target="#rcSettings"><i class="bi bi-gear me-1"></i>ตั้งค่า</button>
            <a href="{{ route('recovery.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>บันทึกคำร้อง</a>
        @endcan
    </x-page-head>

    @unless($open)
        <div class="alert alert-info small"><i class="bi bi-info-circle"></i><div>ยังไม่เปิดให้ประชาชนยื่นคำร้องทางเว็บ เปิดได้ที่ปุ่ม "ตั้งค่า" เมื่อน้ำลดและพร้อมสำรวจ เจ้าหน้าที่บันทึกคำร้องแทนได้ตลอด</div></div>
    @endunless

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><x-stat label="คำร้องทั้งหมด" :value="$totals['claims']" icon="file-earmark-text" tint="primary" /></div>
        <div class="col-6 col-lg-3"><x-stat label="บ้านเสียหายทั้งหลัง" :value="$totals['destroyed']" icon="house-x" tint="danger" /></div>
        <div class="col-6 col-lg-3"><x-stat label="อนุมัติแล้ว (บาท)" :value="number_format($totals['approved'], 0)" icon="check2-square" tint="success" /></div>
        <div class="col-6 col-lg-3"><x-stat label="จ่ายแล้ว (บาท)" :value="number_format($totals['paid'], 0)" icon="cash-coin" tint="teal" /></div>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <ul class="nav nav-pills flex-nowrap overflow-auto" style="scrollbar-width:none">
            @foreach(RecoveryController::TABS as $k => $l)
                <li class="nav-item"><a class="nav-link text-nowrap {{ $tab === $k ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['tab' => $k, 'page' => null]) }}">{{ $l }}<span class="count">{{ $counts[$k] ?? 0 }}</span></a></li>
            @endforeach
        </ul>
        <form class="ms-lg-auto d-flex flex-wrap gap-2" method="GET">
            <input type="hidden" name="tab" value="{{ $tab }}">
            @can('recovery.survey')
                <div class="form-check align-self-center"><input class="form-check-input" type="checkbox" name="mine" value="1" id="mine" @checked(request('mine')) onchange="this.form.submit()"><label class="form-check-label small" for="mine">งานสำรวจของฉัน</label></div>
            @endcan
            <select name="district" class="form-select form-select-sm" style="width:150px" onchange="this.form.submit()">
                <option value="">ทุกอำเภอ</option>
                @foreach($districts as $d)<option value="{{ $d->id }}" @selected(request('district') == $d->id)>{{ $d->name_th }}</option>@endforeach
            </select>
            <input type="search" name="q" class="form-control form-control-sm" style="width:200px" placeholder="เลขคำร้อง ชื่อ เบอร์" value="{{ request('q') }}">
        </form>
    </div>

    <div class="card">
        @forelse($claims as $c)
            <a href="{{ route('recovery.show', $c) }}" class="list-row text-reset text-decoration-none">
                <span class="app-ico flex-shrink-0" style="width:42px;height:42px;border-radius:12px;background:{{ RecoveryOptions::HOUSE[$c->verified_damage ?? $c->house_damage][2] }}1f;color:{{ RecoveryOptions::HOUSE[$c->verified_damage ?? $c->house_damage][2] }}"><i class="bi bi-house-{{ ($c->verified_damage ?? $c->house_damage) === 'destroyed' ? 'x' : 'exclamation' }}"></i></span>
                <div class="flex-grow-1 min-w-0">
                    <div class="d-flex flex-wrap align-items-center gap-1">
                        <span class="fw-600 me-1">{{ $c->head_name }}</span>
                        <span class="small mono text-muted">{{ $c->code }}</span>
                        <span class="chip">{{ $c->houseLabel() }}</span>
                        @if($c->verified_damage)<span class="chip chip-primary"><i class="bi bi-patch-check"></i> สำรวจแล้ว</span>@endif
                        <span class="chip {{ $c->evidence_score >= 40 ? 'chip-success' : ($c->evidence_score > 0 ? 'chip-warning' : 'chip-danger') }}" title="คะแนนหลักฐานจากข้อมูลช่วงน้ำท่วม"><i class="bi bi-shield-check"></i> {{ $c->evidence_score }}</span>
                    </div>
                    <div class="small text-muted">{{ $c->address }} · {{ $c->areaLabel() }} · {{ $c->members }} คน{{ $c->lossLabels() ? ' · '.implode(', ', $c->lossLabels()) : '' }}</div>
                    @if($c->surveyor)<div class="small text-muted"><i class="bi bi-person-badge"></i> {{ $c->surveyor->name }}</div>@endif
                </div>
                <div class="text-end small flex-shrink-0">
                    @if($c->approved_amount !== null)<div class="fw-bold mono">{{ number_format($c->approved_amount, 0) }} ฿</div>@elseif($c->suggested_amount)<div class="mono text-muted">แนะนำ {{ number_format($c->suggested_amount, 0) }}</div>@endif
                    <div class="text-muted">{{ thai_date($c->created_at, 'ago') }}</div>
                </div>
            </a>
        @empty
            <x-empty icon="file-earmark-text" title="ไม่มีคำร้องในหมวดนี้" />
        @endforelse
    </div>
    <div class="mt-3">{{ $claims->links() }}</div>

    @can('recovery.manage')
        <x-form-modal id="rcSettings" title="ตั้งค่าโหมดฟื้นฟู" :action="route('recovery.rates')" method="PUT" size="lg">
            <input type="hidden" name="recovery_open" value="0">
            <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="recovery_open" value="1" id="rcOpen" @checked($open)><label class="form-check-label fw-600" for="rcOpen">เปิดให้ประชาชนยื่นคำร้องทางเว็บ</label></div>
            <div class="alert alert-warning small py-2"><i class="bi bi-info-circle"></i><div>ใส่อัตราตามระเบียบที่หน่วยงานใช้จริง ระบบใช้คำนวณ "ยอดแนะนำ" หลังสำรวจเท่านั้น เจ้าหน้าที่ยังกำหนดยอดอนุมัติเองทุกคำร้อง เว้น 0 = ไม่คำนวณ</div></div>
            <div class="row g-2">
                @foreach(['minor', 'major', 'destroyed'] as $k)
                    <div class="col-md-4"><label class="form-label small">บ้าน{{ RecoveryOptions::HOUSE[$k][0] }} (บาท)</label><input name="house[{{ $k }}]" type="number" min="0" step="0.01" class="form-control mono" value="{{ $rates['house'][$k] ?? 0 }}"></div>
                @endforeach
                @foreach(RecoveryOptions::LOSSES as $k => [$l])
                    <div class="col-6 col-md-3"><label class="form-label small">{{ $l }}</label><input name="loss[{{ $k }}]" type="number" min="0" step="0.01" class="form-control form-control-sm mono" value="{{ $rates['loss'][$k] ?? 0 }}"></div>
                @endforeach
                <div class="col-md-6"><label class="form-label small">พืชผลต่อไร่ (บาท)</label><input name="crop_per_rai" type="number" min="0" step="0.01" class="form-control mono" value="{{ $rates['crop_per_rai'] ?? 0 }}"></div>
                <div class="col-md-6"><label class="form-label small">เพดานต่อครัวเรือน (บาท, 0 = ไม่จำกัด)</label><input name="cap" type="number" min="0" step="0.01" class="form-control mono" value="{{ $rates['cap'] ?? 0 }}"></div>
            </div>
        </x-form-modal>
    @endcan
@endsection
