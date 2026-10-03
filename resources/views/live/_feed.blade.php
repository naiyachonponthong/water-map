{{-- ความเคลื่อนไหวของเคสล่าสุด --}}
@forelse($feed as $e)
    @php $c = $e->helpRequest; @endphp
    <div class="feed-item">
        <span class="tl-dot position-static flex-shrink-0" style="width:26px;height:26px;border-radius:50%;display:grid;place-items:center;background:{{ $c?->priority === 'critical' ? '#fef1f1' : '#f3f4f6' }};color:{{ $c?->priority === 'critical' ? '#dc2626' : 'var(--sb-muted)' }}">
            <i class="bi bi-{{ $e->icon() }}"></i>
        </span>
        <div class="fi-body">
            <div>
                @if($c)<a href="{{ route('cases.show', $c) }}" class="mono">{{ $c->code }}</a>@endif
                <b>{{ $e->summary() }}</b>
            </div>
            @if($e->note && $e->type !== 'created')<div class="small text-muted text-truncate">{{ $e->note }}</div>@endif
            <div class="fi-time">{{ thai_date($e->created_at, 'ago') }}{{ $e->user ? ' · '.$e->user->name : ($e->actor === 'requester' ? ' · ผู้แจ้ง' : '') }}</div>
        </div>
    </div>
@empty
    <x-empty icon="activity" title="ยังไม่มีความเคลื่อนไหว" class="py-3" />
@endforelse
