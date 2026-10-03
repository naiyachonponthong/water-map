@extends('layouts.app')
@section('title', 'เตือนภัยล่วงหน้า')

@section('content')
    @use('App\Support\StationOptions')

    <x-page-head title="เตือนภัยล่วงหน้า" sub="ระบบประกาศเองจากสถานีวัดน้ำ พยากรณ์ฝน และจุดเสี่ยง ศูนย์ประกาศเพิ่มเองได้ ประกาศสาธารณะแสดงบนเว็บประชาชน">
        @can('announcements.manage')
            <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#alertModal"><i class="bi bi-megaphone me-1"></i>ประกาศเตือนภัย</button>
        @endcan
    </x-page-head>

    <h2 class="h6 fw-bold mb-2">มีผลอยู่ ({{ $active->count() }})</h2>
    @forelse($active as $a)
        <div class="alert-card {{ $a->acknowledged_at ? '' : 'unack' }}" style="--ac:{{ $a->color() }}">
            <i class="bi bi-{{ $a->icon() }} ac-ico"></i>
            <div class="flex-grow-1 min-w-0">
                <div class="d-flex flex-wrap align-items-center gap-1">
                    <span class="fw-bold me-1">{{ $a->title }}</span>
                    <span class="chip {{ $a->levelChip() }}">{{ $a->levelLabel() }}</span>
                    <span class="chip"><i class="bi bi-{{ $a->kindIcon() }}"></i> {{ $a->kindLabel() }}</span>
                    @unless($a->is_public)<span class="chip"><i class="bi bi-eye-slash"></i> เฉพาะเจ้าหน้าที่</span>@endunless
                </div>
                @if($a->body)<div class="small mt-1">{{ $a->body }}</div>@endif
                <div class="small text-muted mt-1">
                    เริ่ม {{ thai_date($a->created_at, 'compact') }}{{ $a->updated_at->gt($a->created_at->addMinute()) ? ' · อัปเดต '.thai_date($a->updated_at, 'ago') : '' }}
                    {{ $a->expires_at ? ' · ถึง '.thai_date($a->expires_at, 'compact') : '' }}
                    @if($a->acknowledged_at) · <i class="bi bi-check2"></i> {{ $a->acker?->name }} รับทราบแล้ว@endif
                </div>
            </div>
            <div class="d-flex flex-wrap gap-1 flex-shrink-0">
                @unless($a->acknowledged_at)
                    <form method="POST" action="{{ route('alerts.ack', $a) }}">@csrf<button class="btn btn-sm btn-primary" type="submit">รับทราบ</button></form>
                @endunless
                @can('announcements.manage')
                    <a class="btn btn-sm btn-light" href="{{ route('announcements.index', ['alert' => $a->id]) }}"><i class="bi bi-megaphone"></i> ประกาศ/LINE</a>
                    <form method="POST" action="{{ route('alerts.resolve', $a) }}" data-confirm="ยกเลิกประกาศนี้?">@csrf<button class="btn btn-sm btn-light" type="submit">ยกเลิก</button></form>
                @endcan
            </div>
        </div>
    @empty
        <div class="card mb-3"><x-empty icon="shield-check" title="ไม่มีประกาศเตือนภัย" text="เมื่อสถานีวัดน้ำเกินเกณฑ์ หรือพยากรณ์ฝนหนัก ระบบจะประกาศที่นี่และเด้งบนศูนย์สั่งการ" /></div>
    @endforelse

    <h2 class="h6 fw-bold mt-4 mb-2">ประวัติ</h2>
    <div class="card">
        @forelse($history as $a)
            <div class="list-row">
                <i class="bi bi-{{ $a->kindIcon() }} text-muted mt-1"></i>
                <div class="flex-grow-1 min-w-0">
                    <div class="small"><span class="fw-600">{{ $a->title }}</span> <span class="chip {{ $a->levelChip() }}">{{ $a->levelLabel() }}</span></div>
                    <div class="small text-muted">{{ thai_date($a->created_at, 'compact') }} ถึง {{ thai_date($a->resolved_at ?? $a->expires_at, 'compact') }}</div>
                </div>
            </div>
        @empty
            <div class="card-body small text-muted">ยังไม่มีประวัติ</div>
        @endforelse
    </div>
    <div class="mt-3">{{ $history->links() }}</div>

    @can('announcements.manage')
        <x-form-modal id="alertModal" title="ประกาศเตือนภัย" :action="route('alerts.store')" submit="ประกาศ">
            <div class="mb-3">
                <label class="form-label">ระดับ</label>
                <div class="d-flex gap-2">
                    @foreach(StationOptions::ALERT_LEVELS as $k => [$l, , $color])
                        <input type="radio" class="btn-check" name="level" value="{{ $k }}" id="al{{ $k }}" @checked(old('level', 'warning') === $k)>
                        <label class="btn btn-outline-secondary flex-fill" for="al{{ $k }}" style="--bs-btn-active-bg:{{ $color }};--bs-btn-active-border-color:{{ $color }}">{{ $l }}</label>
                    @endforeach
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">หัวข้อ <span class="text-danger">*</span></label>
                <input name="title" class="form-control" maxlength="200" value="{{ old('title') }}" placeholder="เช่น เขื่อนระบายน้ำเพิ่ม ประชาชนริมแม่น้ำบางปะกงเตรียมขนของขึ้นที่สูง">
            </div>
            <div class="mb-3">
                <label class="form-label">รายละเอียด</label>
                <textarea name="body" class="form-control" rows="3" maxlength="2000">{{ old('body') }}</textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">อำเภอที่ได้รับผลกระทบ</label>
                <select name="district_ids[]" class="form-select" multiple data-search data-placeholder="ทั้งจังหวัด">
                    @foreach($districts as $d)<option value="{{ $d->id }}">{{ $d->name_th }}</option>@endforeach
                </select>
            </div>
            <div class="row g-2 align-items-end">
                <div class="col-6">
                    <label class="form-label">มีผล (ชั่วโมง)</label>
                    <input name="hours" type="number" min="1" max="168" class="form-control" placeholder="จนกว่าจะยกเลิก" value="{{ old('hours') }}">
                </div>
                <div class="col-6">
                    <input type="hidden" name="is_public" value="0">
                    <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" name="is_public" value="1" id="alPub" checked><label class="form-check-label" for="alPub">แสดงบนเว็บประชาชน</label></div>
                </div>
            </div>
        </x-form-modal>
    @endcan
@endsection
