{{-- รายการทีมแนะนำสำหรับเคส (โหลดเข้า modal มอบหมายทีม) --}}
<div class="small text-muted mb-2">
    <span class="mono">{{ $case->code }}</span> · {{ $case->requester_name }} · น้ำ{{ $case->waterLabel() }} · {{ $case->people_count }} คน · {{ $case->areaLabel() }}
</div>
@forelse($suggestions as $i => $s)
    @php $t = $s['team']; @endphp
    <div class="d-flex align-items-center gap-3 p-2 rounded-3 mb-2 {{ $i === 0 && $s['fit'] ? 'bg-primary-subtle' : 'bg-light' }}" style="{{ $i === 0 && $s['fit'] ? 'background:var(--sb-primary-50)!important' : '' }}">
        <div class="flex-grow-1 min-w-0">
            <div class="fw-600">{{ $t->name }}
                <span class="chip chip-dot {{ $t->statusChip() }} ms-1">{{ $t->statusLabel() }}</span>
                @if($i === 0 && $s['fit'])<span class="chip chip-primary ms-1">แนะนำ</span>@endif
                @unless($s['fit'])<span class="chip chip-warning ms-1">อาจไม่เหมาะ</span>@endunless
            </div>
            <div class="small text-muted">
                @if($s['distance_m'] !== null){{ number_format($s['distance_m'] / 1000, 1) }} กม. · ราว {{ $s['eta'] }} นาที @endif
                @foreach($t->vehicles->where('status', '!=', 'maintenance') as $v) · <i class="bi bi-{{ $v->icon() }}"></i> {{ $v->typeLabel() }}@endforeach
            </div>
            @if($s['reasons'])<div class="small {{ $s['fit'] ? 'text-muted' : 'text-warning' }}">{{ implode(' · ', $s['reasons']) }}</div>@endif
        </div>
        <div class="d-flex flex-column gap-1 flex-shrink-0">
            <form method="POST" action="{{ route('dispatch.assign', $case) }}">@csrf
                <input type="hidden" name="team_id" value="{{ $t->id }}"><input type="hidden" name="mode" value="offer">
                <button class="btn btn-sm btn-primary w-100" type="submit"><i class="bi bi-send"></i> เสนองาน</button>
            </form>
            <form method="POST" action="{{ route('dispatch.assign', $case) }}">@csrf
                <input type="hidden" name="team_id" value="{{ $t->id }}"><input type="hidden" name="mode" value="accepted">
                <button class="btn btn-sm btn-light w-100" type="submit" title="โทรตกลงกับทีมแล้ว">รับแล้วทางโทร</button>
            </form>
        </div>
    </div>
@empty
    <x-empty icon="people" title="ไม่มีทีมที่พร้อม" text="ทุกทีมพักหรือไม่ออกปฏิบัติ หรือยังไม่ได้เพิ่มทีม" class="py-3" />
@endforelse

@if($others->count() > $suggestions->count())
    <details class="mt-2">
        <summary class="small text-muted">เลือกทีมอื่น (รวมทีมที่พักอยู่)</summary>
        <form method="POST" action="{{ route('dispatch.assign', $case) }}" class="d-flex gap-2 mt-2">@csrf
            <select name="team_id" class="form-select form-select-sm">
                @foreach($others as $t)<option value="{{ $t->id }}">{{ $t->name }} ({{ $t->statusLabel() }})</option>@endforeach
            </select>
            <input type="hidden" name="mode" value="offer">
            <button class="btn btn-sm btn-primary" type="submit">เสนองาน</button>
        </form>
    </details>
@endif
