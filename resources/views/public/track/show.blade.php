@extends('layouts.public')
@section('title', 'คำขอ '.$req->code)

@section('content')
    @php
        $tones = ['new' => 'danger', 'screening' => 'warning', 'queued' => 'warning', 'offered' => 'primary', 'accepted' => 'primary', 'en_route' => 'primary', 'on_site' => 'primary', 'rescued' => 'success', 'closed' => 'slate', 'merged' => 'slate', 'cancelled' => 'slate'];
        $progress = ['รับคำขอ' => ['new'], 'ตรวจสอบ' => ['screening'], 'จัดทีม' => ['queued', 'offered'], 'ทีมกำลังไป' => ['accepted', 'en_route', 'on_site'], 'ช่วยแล้ว' => ['rescued']];
        $order = array_keys($progress);
        $at = collect($progress)->search(fn ($s) => in_array($req->status, $s, true));
        $atIndex = $at === false ? null : array_search($at, $order, true);
        $tel = fn ($p) => preg_replace('/\D+/', '', $p);
    @endphp

    <header class="pub-head">
        <div class="inner d-flex align-items-center gap-2">
            <a href="{{ route('public.province', $req->province) }}" class="text-white fs-4 me-1" aria-label="กลับ"><i class="bi bi-arrow-left"></i></a>
            <div>
                <div class="fw-bold lh-sm">ติดตามคำขอ</div>
                <div class="small opacity-75 mono">{{ $req->code }}</div>
            </div>
            <a href="tel:{{ $tel($hotline) }}" class="btn btn-sm btn-danger ms-auto"><i class="bi bi-telephone-fill"></i> {{ $hotline }}</a>
        </div>
    </header>

    <div class="pub-wrap">
        <div class="pub-float">
            @if(session('submitted'))
                <div class="alert alert-success"><i class="bi bi-check-circle-fill"></i>
                    <div><b>ส่งคำขอแล้ว</b> เลขคำขอ <b class="mono">{{ $req->code }}</b> บันทึกหน้านี้ไว้ หรือกดแชร์ลิงก์ให้ญาติ เพื่อติดตามสถานะ</div>
                </div>
            @elseif(session('attached'))
                <div class="alert alert-primary"><i class="bi bi-info-circle-fill"></i>
                    <div>เบอร์นี้มีคำขอที่ยังเปิดอยู่แล้ว ระบบรวมข้อมูลใหม่เข้ากับคำขอเดิม <b class="mono">{{ $req->code }}</b> ไม่ต้องส่งซ้ำ</div>
                </div>
            @endif

            <div class="card mb-3">
                <div class="card-body track-status">
                    <div class="ts-ico tint-{{ $tones[$req->status] ?? 'primary' }}"><i class="bi bi-{{ \App\Support\CaseOptions::status($req->status, 3) }}"></i></div>
                    <h1 class="h4 fw-bold mb-1">{{ $req->statusLabel(true) }}</h1>
                    <div class="text-muted small">แจ้งเมื่อ {{ thai_date($req->created_at, 'compact') }} · {{ $req->areaLabel() }}</div>
                    @if($req->status === 'merged' && $req->duplicateOf)
                        <div class="small mt-2">ติดตามต่อที่คำขอ <b class="mono">{{ $req->duplicateOf->code }}</b> สถานะ: {{ $req->duplicateOf->statusLabel(true) }}</div>
                    @endif
                    @if($req->outcome && in_array($req->status, ['rescued', 'closed', 'cancelled'], true))
                        <div class="chip mt-2">{{ \App\Support\CaseOptions::OUTCOMES[$req->outcome] ?? '' }}</div>
                    @endif
                </div>

                @if($atIndex !== null)
                    <div class="px-3 pb-3">
                        <div class="d-flex justify-content-between position-relative">
                            @foreach($order as $i => $label)
                                <div class="text-center flex-fill" style="font-size:.72rem">
                                    <div class="mx-auto mb-1 rounded-circle" style="width:14px;height:14px;background:{{ $i <= $atIndex ? 'var(--sb-primary)' : '#e5e7eb' }};{{ $i === $atIndex ? 'box-shadow:0 0 0 4px var(--sb-primary-100)' : '' }}"></div>
                                    <span class="{{ $i <= $atIndex ? 'fw-600' : 'text-muted' }}">{{ $label }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="border-top p-3 d-flex gap-2">
                    <button type="button" class="btn btn-light flex-fill" data-copy="{{ $req->trackUrl() }}"><i class="bi bi-link-45deg"></i> คัดลอกลิงก์</button>
                    <a class="btn btn-line flex-fill" href="https://line.me/R/share?text={{ rawurlencode('ติดตามคำขอความช่วยเหลือน้ำท่วม '.$req->code.' '.$req->trackUrl()) }}"><i class="bi bi-line"></i> ส่งทาง LINE</a>
                </div>
            </div>

            @if($req->isOpen())
                <div class="card mb-3">
                    <div class="card-header">อัปเดตให้ศูนย์</div>
                    <div class="card-body d-grid gap-2">
                        <button class="btn btn-outline-danger text-start" data-bs-toggle="collapse" data-bs-target="#worseBox" type="button"><i class="bi bi-exclamation-triangle me-2"></i>สถานการณ์แย่ลง / น้ำขึ้น</button>
                        <div class="collapse" id="worseBox">
                            <form method="POST" action="{{ $req->signedAction('public.track.worse') }}" class="border rounded-4 p-3">
                                @csrf
                                <label class="form-label small">ตอนนี้น้ำสูงแค่ไหน</label>
                                <select name="water_level" class="form-select mb-2">
                                    @foreach($levels as $n => $lv)
                                        <option value="{{ $n }}" @selected($n === min(6, $req->water_level + 1))>{{ $lv['label'] }} ({{ $lv['range'] }})</option>
                                    @endforeach
                                </select>
                                <textarea name="note" class="form-control mb-2" rows="2" maxlength="500" placeholder="เกิดอะไรขึ้นเพิ่ม (ไม่บังคับ)"></textarea>
                                <button class="btn btn-danger w-100" type="submit">ส่งให้ศูนย์</button>
                            </form>
                        </div>

                        <button class="btn btn-light text-start" data-bs-toggle="collapse" data-bs-target="#noteBox" type="button"><i class="bi bi-chat-left-text me-2"></i>ส่งข้อมูลเพิ่มเติม</button>
                        <div class="collapse" id="noteBox">
                            <form method="POST" action="{{ $req->signedAction('public.track.note') }}" class="border rounded-4 p-3">
                                @csrf
                                <textarea name="note" class="form-control mb-2" rows="3" maxlength="500" required placeholder="เช่น ย้ายไปอยู่ชั้น 2 แล้ว แบตโทรศัพท์เหลือน้อย"></textarea>
                                <button class="btn btn-primary w-100" type="submit">ส่ง</button>
                            </form>
                        </div>

                        <form method="POST" action="{{ $req->signedAction('public.track.safe') }}" data-confirm="ยืนยันว่าปลอดภัยแล้ว ไม่ต้องการความช่วยเหลือ? ศูนย์จะปิดคำขอนี้และส่งทีมไปช่วยคนอื่น">
                            @csrf
                            <button class="btn btn-soft w-100 text-start" type="submit"><i class="bi bi-shield-check me-2"></i>ปลอดภัยแล้ว / ไม่ต้องการความช่วยเหลือแล้ว</button>
                        </form>
                    </div>
                </div>
            @endif

            <div class="card mb-3">
                <div class="card-header">ข้อมูลที่แจ้ง</div>
                <div class="card-body">
                    <dl class="kv mb-0">
                        <dt>ระดับน้ำ</dt><dd><span class="lv-dot me-1" style="background:{{ config('floodthai.water_levels.'.$req->water_level.'.color') }}"></span>{{ config('floodthai.water_levels.'.$req->water_level.'.label') }}</dd>
                        <dt>จำนวนคน</dt><dd>{{ $req->people_count }} คน</dd>
                        @if($req->vulnerable)<dt>กลุ่มเปราะบาง</dt><dd>{{ implode(', ', $req->vulnerableLabels()) }}</dd>@endif
                        <dt>ต้องการ</dt><dd>{{ implode(', ', $req->needLabels()) ?: '-' }}</dd>
                        <dt>ผู้แจ้ง</dt><dd>{{ $req->requester_name }} ({{ $req->maskedPhone() }})</dd>
                    </dl>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">ความคืบหน้า</div>
                <div class="card-body">
                    <div class="timeline">
                        @foreach($events as $e)
                            <div class="tl-item {{ $loop->first ? 'first' : '' }}">
                                <span class="tl-dot"><i class="bi bi-{{ $e->icon() }}"></i></span>
                                <div class="fw-600 small">{{ $e->actor === 'requester' && $e->type !== 'created' ? 'คุณ: ' : '' }}{{ $e->summary(true) }}</div>
                                <div class="tl-time">{{ thai_date($e->created_at, 'compact') }}</div>
                                @if($e->note && $e->public && ($e->actor === 'requester' || $e->type === 'note' || ($e->data['public_note'] ?? false)))<div class="tl-note">{{ $e->note }}</div>@endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><i class="bi bi-telephone-fill text-danger"></i> ด่วนมาก โทรเลย</div>
                @foreach($contacts as $c)
                    <a href="tel:{{ $tel($c['phone']) }}" class="contact-row">
                        <span class="small fw-600">{{ $c['label'] }}</span>
                        <span class="num mono">{{ phone_format($c['phone']) }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
@endsection
