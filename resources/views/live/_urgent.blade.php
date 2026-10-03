{{-- คิวเคสด่วน (โหลดใหม่ตาม snapshot) --}}
@forelse($urgent as $c)
    <a href="{{ route('cases.show', $c) }}" class="case-row py-2">
        <span class="prio-bar" style="background:{{ $c->priorityColor() }}"></span>
        <div class="cr-main">
            <div class="small fw-600 text-truncate"><span class="mono text-muted me-1">{{ $c->code }}</span>{{ $c->requester_name }}</div>
            <div class="cr-meta">
                <span>น้ำ{{ $c->waterLabel() }}</span><span>{{ $c->people_count }} คน</span>
                @if($c->hasVulnerable('bedridden', 'sick', 'disabled', 'infant'))<span class="text-danger"><i class="bi bi-heart-pulse"></i></span>@endif
                <span class="{{ $c->waitingMinutes() >= 60 ? 'text-danger fw-600' : '' }}">รอ {{ $c->waitingLabel() }}</span>
            </div>
        </div>
        <span class="score-pill align-self-center" style="background:{{ $c->priorityColor() }}">{{ $c->priority_score }}</span>
    </a>
@empty
    <x-empty icon="check2-circle" title="ไม่มีเคสรอทีม" class="py-3" />
@endforelse
