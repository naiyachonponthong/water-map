@extends('layouts.public')
@section('title', 'ประกาศ '.$province->fullName())

@section('content')
    <header class="pub-head">
        <div class="inner d-flex align-items-center gap-2">
            <a href="{{ route('public.province', $province) }}" class="text-white fs-4 me-1" aria-label="กลับ"><i class="bi bi-arrow-left"></i></a>
            <div><div class="fw-bold lh-sm">ประกาศจากศูนย์</div><div class="small opacity-75">{{ $province->fullName() }}</div></div>
        </div>
    </header>

    <div class="pub-wrap">
        <div class="pub-float">
            @forelse($items as $a)
                @php $live = ! $a->expires_at || $a->expires_at->isFuture(); @endphp
                <div class="card mb-2 {{ $live ? '' : 'opacity-75' }}" style="border-left:5px solid {{ $a->color() }}">
                    <div class="card-body">
                        <div class="d-flex flex-wrap align-items-center gap-1 mb-1">
                            <span class="chip {{ $a->levelChip() }}">{{ $a->levelLabel() }}</span>
                            @if($a->pinned)<i class="bi bi-pin-angle-fill text-muted"></i>@endif
                            @unless($live)<span class="chip">สิ้นสุดแล้ว</span>@endunless
                            <span class="small text-muted ms-auto">{{ thai_date($a->published_at, 'compact') }}</span>
                        </div>
                        <div class="fw-bold">{{ $a->title }}</div>
                        <div class="small mt-1" style="white-space:pre-line">{{ $a->body }}</div>
                        @if($a->district_ids)<div class="small text-muted mt-1"><i class="bi bi-geo-alt"></i> {{ $a->districtNames() }}</div>@endif
                    </div>
                </div>
            @empty
                <div class="card"><x-empty icon="megaphone" title="ยังไม่มีประกาศ" /></div>
            @endforelse
            <div class="mt-3">{{ $items->links() }}</div>
        </div>
    </div>
@endsection
