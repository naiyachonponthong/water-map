{{-- ประกาศเตือนภัยที่มีผล (ศูนย์สั่งการ / ทีวี) --}}
@foreach($alerts as $a)
    <div class="alert-bar {{ $a->acknowledged_at ? '' : 'shadow' }}" style="--ac:{{ $a->color() }}">
        <i class="bi bi-{{ $a->icon() }} fs-5"></i>
        <div class="flex-grow-1 min-w-0">
            <div class="fw-bold text-truncate">{{ $a->levelLabel() }}: {{ $a->title }}</div>
            @if($a->body)<div class="small opacity-90 text-truncate">{{ $a->body }}</div>@endif
        </div>
        @auth
            @if(! $a->acknowledged_at && ! ($tv ?? false))
                <form method="POST" action="{{ route('alerts.ack', $a) }}">@csrf<button class="btn btn-sm btn-light" type="submit">รับทราบ</button></form>
            @endif
        @endauth
    </div>
@endforeach
