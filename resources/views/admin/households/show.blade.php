@extends('layouts.app')
@section('title', $h->head_name)

@push('head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
@endpush

@section('content')
    @use('App\Support\RiskOptions')

    <x-page-head :title="$h->head_name" :sub="$h->areaLabel().' · '.$h->members.' คน'">
        <a href="{{ route('vulnerable.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>ทะเบียน</a>
        <a href="{{ route('vulnerable.edit', $h) }}" class="btn btn-soft"><i class="bi bi-pencil me-1"></i>แก้ไข</a>
    </x-page-head>

    <div class="row g-3">
        <div class="col-lg-7">
            @if($h->openCase && $h->openCase->isOpen())
                <a href="{{ route('cases.show', $h->openCase) }}" class="alert alert-danger text-decoration-none">
                    <i class="bi bi-life-preserver"></i>
                    <div class="flex-grow-1"><b>มีเคสตรวจเยี่ยมเปิดอยู่ {{ $h->openCase->code }}</b><div class="small">{{ $h->openCase->statusLabel() }} · {{ $h->openCase->createdLabel() }}</div></div>
                    <span class="btn btn-sm btn-danger">ดูเคส</span>
                </a>
            @endif

            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-1 mb-3">
                        @foreach($h->conditions ?? [] as $c)
                            <span class="hh-cond fs-6"><i class="bi bi-{{ RiskOptions::CONDITIONS[$c][1] ?? 'dot' }}"></i> {{ RiskOptions::CONDITIONS[$c][0] ?? $c }}</span>
                        @endforeach
                    </div>
                    <dl class="row mb-0 small">
                        <dt class="col-sm-4 text-muted fw-normal">เบอร์โทร</dt>
                        <dd class="col-sm-8">@if($h->phone)<a href="tel:{{ $h->phone }}" class="mono fw-600">{{ phone_format($h->phone) }}</a>@else - @endif</dd>
                        <dt class="col-sm-4 text-muted fw-normal">ผู้ดูแล</dt>
                        <dd class="col-sm-8">{{ $h->caretaker_name ?: '-' }} @if($h->caretaker_phone)<a href="tel:{{ $h->caretaker_phone }}" class="mono ms-1">{{ phone_format($h->caretaker_phone) }}</a>@endif</dd>
                        <dt class="col-sm-4 text-muted fw-normal">ที่อยู่</dt>
                        <dd class="col-sm-8">{{ $h->address ?: '-' }} {{ $h->areaLabel() }}</dd>
                        <dt class="col-sm-4 text-muted fw-normal">ความยินยอม</dt>
                        <dd class="col-sm-8">{!! $h->consent_at ? '<span class="text-success"><i class="bi bi-check-circle"></i> '.e(thai_date($h->consent_at)).'</span>' : '<span class="text-warning">ยังไม่บันทึก</span>' !!}</dd>
                        @if($h->note)
                            <dt class="col-sm-4 text-muted fw-normal">ข้อมูลสำหรับทีม</dt>
                            <dd class="col-sm-8">{{ $h->note }}</dd>
                        @endif
                    </dl>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-clipboard-check text-primary"></i> บันทึกผลเยี่ยม / โทรติดตาม</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('vulnerable.check', $h) }}">
                        @csrf
                        <div class="check-btns mb-2">
                            @foreach(RiskOptions::CHECK as $k => [$l])
                                <input type="radio" class="btn-check" name="status" value="{{ $k }}" id="ck_{{ $k }}" required>
                                <label class="btn btn-outline-{{ $k === 'needs_help' ? 'danger' : ($k === 'safe' ? 'success' : ($k === 'evacuated' ? 'primary' : 'warning')) }}" for="ck_{{ $k }}">{{ $l }}</label>
                            @endforeach
                        </div>
                        <input name="note" class="form-control mb-2" maxlength="500" placeholder="หมายเหตุ เช่น ยาเหลือ 3 วัน ญาติจะมารับพรุ่งนี้">
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <button class="btn btn-primary" type="submit">บันทึกผล</button>
                            <span class="small text-muted">เลือก "ต้องการความช่วยเหลือ" ระบบจะเปิดเคสส่งเข้าคิวทีมทันที</span>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-clock-history text-primary"></i> ประวัติการเยี่ยม</div>
                @forelse($h->checks as $c)
                    <div class="list-row">
                        <span class="chip {{ RiskOptions::CHECK[$c->status][1] ?? '' }}">{{ RiskOptions::CHECK[$c->status][0] ?? $c->status }}</span>
                        <div class="flex-grow-1 small">
                            <div>{{ $c->user?->name ?? 'ระบบ' }}{{ $c->team ? ' · ทีม '.$c->team->name : '' }}</div>
                            @if($c->note)<div class="text-muted">{{ $c->note }}</div>@endif
                        </div>
                        <span class="small text-muted text-nowrap">{{ thai_date($c->created_at, 'ago') }}</span>
                    </div>
                @empty
                    <x-empty icon="clipboard" title="ยังไม่เคยเยี่ยม" />
                @endforelse
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-body p-2">
                    @if($h->lat !== null)
                        <div id="hhMap" class="map-box" style="height:260px"></div>
                        <div class="d-flex gap-2 p-2">
                            <a class="btn btn-sm btn-light" target="_blank" rel="noopener" href="https://www.google.com/maps/dir/?api=1&destination={{ $h->lat }},{{ $h->lng }}"><i class="bi bi-sign-turn-right me-1"></i>นำทาง</a>
                            <span class="small text-muted mono align-self-center">{{ $h->lat }}, {{ $h->lng }}</span>
                        </div>
                    @else
                        <x-empty icon="geo" title="ยังไม่มีพิกัด" text="ระบบใช้กลางตำบลแทน ทีมอาจหาบ้านไม่เจอ ควรเพิ่มพิกัด" />
                    @endif
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <form method="POST" action="{{ route('vulnerable.open-case', $h) }}" data-confirm="เปิดเคสตรวจเยี่ยม {{ $h->head_name }} ส่งเข้าคิวทีม?">
                        @csrf
                        <button class="btn btn-warning w-100" type="submit" @disabled($h->openCase?->isOpen())><i class="bi bi-send me-1"></i>สั่งทีมไปตรวจเยี่ยมเดี๋ยวนี้</button>
                    </form>
                    <div class="small text-muted mt-2">ปกติระบบเปิดเคสเองเมื่อน้ำรอบบ้านถึงระดับ{{ config('floodthai.water_levels.'.setting('household_trigger_level').'.short') }} ใช้ปุ่มนี้เมื่อรู้ข่าวก่อน</div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-life-preserver text-primary"></i> เคสที่เกี่ยวข้อง</div>
                @forelse($cases as $c)
                    <a href="{{ route('cases.show', $c) }}" class="list-row text-reset text-decoration-none">
                        <span class="mono small">{{ $c->code }}</span>
                        <span class="chip {{ $c->statusChip() }}">{{ $c->statusLabel() }}</span>
                        <span class="small text-muted ms-auto">{{ $c->createdLabel() }}</span>
                    </a>
                @empty
                    <div class="card-body small text-muted">ยังไม่มีเคส</div>
                @endforelse
            </div>

            <form method="POST" action="{{ route('vulnerable.destroy', $h) }}" data-confirm="ลบ {{ $h->head_name }} ออกจากทะเบียน?">@csrf @method('DELETE')
                <button class="btn btn-link text-danger btn-sm" type="submit"><i class="bi bi-trash me-1"></i>ลบออกจากทะเบียน</button>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    @if($h->lat !== null)
        <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
        <script src="{{ asset('js/map.js') }}?v={{ filemtime(public_path('js/map.js')) }}"></script>
        <script>
            (function () {
                const map = FloodMap.create(document.getElementById('hhMap'), { center: [{{ $h->lat }}, {{ $h->lng }}], zoom: 16 });
                L.marker([{{ $h->lat }}, {{ $h->lng }}]).addTo(map);
            })();
        </script>
    @endif
@endpush
