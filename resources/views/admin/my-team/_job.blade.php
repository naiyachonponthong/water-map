{{-- การ์ดงานหนึ่งงาน ใช้ในหน้างานของทีม --}}
@use('App\Support\CaseOptions')
@use('App\Support\TeamOptions')
@php $c = $a->helpRequest; $lv = config('floodthai.water_levels.'.$c->water_level); @endphp
<div class="card mb-3" style="border-left:5px solid {{ $c->priorityColor() }}">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
            <span class="mono small text-muted">{{ $c->code }}</span>
            <span class="chip {{ $a->statusChip() }}">{{ $a->statusLabel() }}</span>
            <span class="score-pill ms-auto" style="background:{{ $c->priorityColor() }}">{{ $c->priorityLabel() }}</span>
        </div>
        <div class="fw-bold fs-5">{{ $c->requester_name }}</div>
        <div class="small mb-2">
            <span class="lv-dot" style="background:{{ $lv['color'] }}"></span> {{ $lv['label'] }} · {{ $c->people_count }} คน{{ $c->floor_level ? ' · ชั้น '.$c->floor_level : '' }}
        </div>
        @if($c->vulnerable)
            <div class="mb-2">@foreach($c->vulnerable as $v)<span class="chip {{ $v === 'pets' ? '' : 'chip-danger' }} me-1 mb-1"><i class="bi bi-{{ CaseOptions::VULNERABLE[$v][1] ?? 'dot' }}"></i> {{ CaseOptions::VULNERABLE[$v][0] ?? $v }}</span>@endforeach</div>
        @endif
        <div class="small text-muted mb-1"><i class="bi bi-geo-alt"></i> {{ $c->address_text ?: $c->areaLabel() }}{{ $c->landmark ? ' · '.$c->landmark : '' }}</div>
        @if($c->needs_note)<div class="small bg-light rounded-3 p-2 mb-2" style="white-space:pre-line">{{ $c->needs_note }}</div>@endif
        @if($a->eta_minutes)<div class="small text-muted mb-2">ระยะประมาณ {{ number_format(($a->distance_m ?? 0) / 1000, 1) }} กม. · ราว {{ $a->eta_minutes }} นาที</div>@endif

        <div class="d-grid gap-2" style="grid-template-columns:1fr 1fr">
            <a href="{{ $c->mapsUrl() }}" target="_blank" rel="noopener" class="btn btn-soft"><i class="bi bi-sign-turn-right"></i> นำทาง</a>
            <a href="tel:{{ preg_replace('/\D+/', '', (string) ($c->contact_phone ?: $c->requester_phone)) }}" class="btn btn-light"><i class="bi bi-telephone"></i> โทร{{ $c->contact_phone ? 'คนในพื้นที่' : 'ผู้แจ้ง' }}</a>
        </div>

        <div class="mt-2">
            @if($a->status === 'offered')
                <div class="d-grid gap-2" style="grid-template-columns:2fr 1fr">
                    <form method="POST" action="{{ route('assignments.respond', $a) }}">@csrf<input type="hidden" name="accept" value="1">
                        <button class="btn btn-primary btn-lg w-100" type="submit"><i class="bi bi-check-lg"></i> รับงาน</button>
                    </form>
                    <button class="btn btn-outline-danger btn-lg" type="button" data-bs-toggle="collapse" data-bs-target="#decline{{ $a->id }}">ปฏิเสธ</button>
                </div>
                <form method="POST" action="{{ route('assignments.respond', $a) }}" class="collapse mt-2" id="decline{{ $a->id }}">@csrf<input type="hidden" name="accept" value="0">
                    <select name="reason" class="form-select mb-2">@foreach(TeamOptions::DECLINE_REASONS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                    <button class="btn btn-danger w-100" type="submit">ยืนยันปฏิเสธ</button>
                </form>
            @elseif($a->status === 'accepted')
                <form method="POST" action="{{ route('assignments.progress', $a) }}">@csrf<input type="hidden" name="step" value="en_route">
                    <button class="btn btn-primary btn-lg w-100" type="submit"><i class="bi bi-truck"></i> เริ่มเดินทาง</button>
                </form>
            @elseif($a->status === 'en_route')
                <form method="POST" action="{{ route('assignments.progress', $a) }}">@csrf<input type="hidden" name="step" value="on_site">
                    <button class="btn btn-primary btn-lg w-100" type="submit"><i class="bi bi-geo-alt-fill"></i> ถึงที่เกิดเหตุแล้ว</button>
                </form>
            @endif

            @if(in_array($a->status, ['accepted', 'en_route', 'on_site'], true))
                <button class="btn {{ $a->status === 'on_site' ? 'btn-success btn-lg' : 'btn-light' }} w-100 mt-2" type="button" data-bs-toggle="collapse" data-bs-target="#done{{ $a->id }}"><i class="bi bi-check2-circle"></i> ปิดงาน</button>
                <form method="POST" action="{{ route('assignments.complete', $a) }}" class="collapse mt-2 border rounded-4 p-3" id="done{{ $a->id }}">@csrf
                    <label class="form-label small">ผลการช่วยเหลือ</label>
                    <select name="outcome" class="form-select mb-2">
                        @foreach(CaseOptions::OUTCOMES as $k => $l)
                            @continue(in_array($k, ['duplicate', 'self_safe'], true))
                            <option value="{{ $k }}">{{ $l }}</option>
                        @endforeach
                    </select>
                    <label class="form-label small">ช่วยออกมาได้กี่คน</label>
                    <input type="number" name="people_rescued" class="form-control mb-2" min="0" max="500" value="{{ $c->people_count }}">
                    <input name="note" class="form-control mb-2" maxlength="500" placeholder="หมายเหตุ เช่น ส่งศูนย์พักพิงวัดโสธร">
                    <button class="btn btn-success w-100" type="submit">ยืนยันปิดงาน</button>
                </form>
            @endif
        </div>
    </div>
</div>
