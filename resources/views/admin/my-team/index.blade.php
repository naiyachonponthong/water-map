@extends('layouts.app')
@section('title', 'งานของทีมฉัน')

@section('content')
    @use('App\Support\TeamOptions')

    <div class="page-head">
        <div>
            <h1>{{ $team->name }}</h1>
            <div class="ph-sub">{{ $team->vehicles->where('status', '!=', 'maintenance')->map(fn ($v) => $v->typeLabel())->implode(' · ') ?: 'ยังไม่ได้บันทึกยานพาหนะ' }}</div>
        </div>
        <div class="ph-actions"><a href="{{ route('field.app') }}" class="btn btn-primary"><i class="bi bi-phone me-1"></i>เปิดแอปภาคสนาม</a></div>
    </div>

    {{-- สถานะทีม --}}
    <div class="card mb-3">
        <div class="card-body">
            <div class="small text-muted mb-2">สถานะทีมตอนนี้</div>
            <div class="d-grid gap-2" style="grid-template-columns:repeat(4,1fr)">
                @foreach(TeamOptions::STATUSES as $k => [$label, , $color])
                    <form method="POST" action="{{ route('teams.status', $team) }}">@csrf<input type="hidden" name="status" value="{{ $k }}">
                        <button type="submit" class="btn w-100 px-1 {{ $team->status === $k ? 'text-white' : 'btn-light' }}" style="{{ $team->status === $k ? 'background:'.$color : '' }};font-size:.82rem">{{ $label }}</button>
                    </form>
                @endforeach
            </div>
            <div class="small text-muted mt-2" id="gpsNote"><i class="bi bi-broadcast"></i> {{ $team->last_seen_at ? 'ส่งพิกัดล่าสุด '.thai_date($team->last_seen_at, 'ago') : 'ยังไม่ได้ส่งพิกัด' }}</div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            @if($offers->isNotEmpty())
                <h2 class="h6 fw-bold text-danger"><i class="bi bi-bell-fill"></i> ศูนย์เสนองาน รอทีมตอบ ({{ $offers->count() }})</h2>
                @foreach($offers as $a) @include('admin.my-team._job', ['a' => $a]) @endforeach
            @endif

            <h2 class="h6 fw-bold">งานที่กำลังทำ ({{ $active->count() }})</h2>
            @forelse($active as $a)
                @include('admin.my-team._job', ['a' => $a])
            @empty
                <div class="card mb-3"><x-empty icon="check2-circle" title="ไม่มีงานค้าง" text="{{ $selfAssign ? 'หยิบเคสใกล้ทีมจากรายการด้านข้างได้เลย' : 'รอศูนย์มอบหมายงาน หน้านี้จะรีเฟรชเองทุก 30 วินาที' }}" class="py-3" /></div>
            @endforelse
        </div>

        <div class="col-lg-5">
            @if($selfAssign)
                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-geo text-primary"></i> เคสรอทีมใกล้คุณ</div>
                    @forelse($nearby as $c)
                        <div class="case-row">
                            <span class="prio-bar" style="background:{{ $c->priorityColor() }}"></span>
                            <div class="cr-main">
                                <div class="fw-600">{{ $c->requester_name }} <span class="score-pill ms-1" style="background:{{ $c->priorityColor() }}">{{ $c->priorityLabel() }}</span></div>
                                <div class="cr-meta"><span>น้ำ{{ $c->waterLabel() }}</span><span>{{ $c->people_count }} คน</span><span>{{ number_format($c->distance_m / 1000, 1) }} กม.</span><span>{{ $c->areaLabel() }}</span></div>
                            </div>
                            <form method="POST" action="{{ route('my-team.pick', $c) }}" class="align-self-center" data-confirm="รับเคส {{ $c->code }} ({{ $c->requester_name }})?">@csrf
                                <button class="btn btn-sm btn-primary" type="submit">รับเคสนี้</button>
                            </form>
                        </div>
                    @empty
                        <x-empty icon="geo" title="{{ $team->position() ? 'ไม่มีเคสรอทีม' : 'ยังไม่ทราบตำแหน่งทีม' }}" text="{{ $team->position() ? '' : 'อนุญาตตำแหน่งในเบราว์เซอร์ หรือให้ศูนย์ตั้งพิกัดฐาน' }}" class="py-3" />
                    @endforelse
                </div>
            @endif

            <div class="card">
                <div class="card-header"><i class="bi bi-check2-all text-success"></i> ปิดงานวันนี้ ({{ $doneToday->count() }})</div>
                @forelse($doneToday as $a)
                    <div class="d-flex justify-content-between px-3 py-2 border-bottom small">
                        <span><span class="mono text-muted">{{ $a->helpRequest?->code }}</span> {{ $a->helpRequest?->requester_name }}</span>
                        <span>{{ $a->people_rescued ?? 0 }} คน · {{ $a->done_at->format('H:i') }}</span>
                    </div>
                @empty
                    <div class="card-body small text-muted">ยังไม่มี</div>
                @endforelse
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            // ส่งพิกัดทุก 60 วินาทีเมื่อทีมไม่ได้พัก/ไม่ออกปฏิบัติ
            const active = @json(in_array($team->status, ['available', 'busy'], true));
            const url = @json(route('my-team.location'));
            const token = document.querySelector('meta[name=csrf-token]').content;
            const send = () => navigator.geolocation && navigator.geolocation.getCurrentPosition(p => {
                fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' }, body: JSON.stringify({ lat: p.coords.latitude, lng: p.coords.longitude }) })
                    .then(r => r.ok && (document.getElementById('gpsNote').innerHTML = '<i class="bi bi-broadcast text-success"></i> ส่งพิกัดแล้ว ' + new Date().toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' })));
            }, () => {}, { enableHighAccuracy: true, timeout: 15000, maximumAge: 30000 });
            if (active) { send(); setInterval(send, 60000); }

            // รีเฟรชเพื่อดูงานใหม่ ถ้าไม่ได้กำลังกรอกฟอร์มอยู่
            const reload = () => { if (!document.querySelector('.collapse.show') && document.visibilityState === 'visible') location.reload(); };
            setInterval(reload, 30000);
            if (window.FloodLive) {
                FloodLive.channel('team.{{ $team->id }}').on('case.changed', e => {
                    if (e.status === 'offered') { FloodLive.sound.beep(2); if (navigator.vibrate) navigator.vibrate([300, 150, 300]); }
                    setTimeout(reload, 500);
                });
            }
        })();
    </script>
@endpush
