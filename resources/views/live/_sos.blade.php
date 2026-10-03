{{-- SOS ของทีมที่ยังไม่ปิด --}}
@foreach($sos as $s)
    @php $phone = $s->user?->phone ?: ($s->team?->phone ?: $s->team?->leader?->phone); @endphp
    <div class="sos-card {{ $s->status }}">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-{{ \App\Models\TeamSos::KINDS[$s->kind][1] ?? 'exclamation-triangle-fill' }} fs-4"></i>
            <div class="flex-grow-1 min-w-0">
                <div class="fw-bold">SOS {{ $s->team?->name }} · {{ $s->kindLabel() }}</div>
                <div class="small opacity-75">
                    {{ thai_date($s->created_at, 'ago') }}{{ $s->user ? ' · '.$s->user->name : '' }}{{ $s->helpRequest ? ' · งาน '.$s->helpRequest->code : '' }}
                    @if($s->status === 'ack') · รับทราบโดย {{ $s->acker?->name }}@endif
                </div>
                @if($s->note)<div class="small">{{ $s->note }}</div>@endif
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-2">
            @if($phone)<a href="tel:{{ $phone }}" class="btn btn-sm btn-light"><i class="bi bi-telephone"></i> {{ phone_format($phone) }}</a>@endif
            @if($s->lat)<a href="https://www.google.com/maps/dir/?api=1&destination={{ $s->lat }},{{ $s->lng }}" target="_blank" rel="noopener" class="btn btn-sm btn-light"><i class="bi bi-geo-alt"></i> ตำแหน่ง</a>@endif
            @can('dispatch.manage')
                @if($s->status === 'open')
                    <form method="POST" action="{{ route('sos.ack', $s) }}">@csrf<button class="btn btn-sm btn-warning" type="submit">รับทราบ กำลังประสาน</button></form>
                @endif
                <form method="POST" action="{{ route('sos.resolve', $s) }}" data-confirm="ปิด SOS ของทีม {{ $s->team?->name }}?">@csrf<button class="btn btn-sm btn-outline-light" type="submit">คลี่คลายแล้ว</button></form>
            @endcan
        </div>
    </div>
@endforeach
