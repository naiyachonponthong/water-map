@extends('layouts.app')
@section('title', 'สั่งการทีม')

@section('content')

    <x-page-head title="สั่งการทีม" sub="ลากการ์ดไปคอลัมน์ถัดไปเพื่ออัปเดต หรือใช้เมนู ... บนการ์ด กระดานรีเฟรชเองทุก 20 วินาที">
        <a href="{{ route('cases.index', ['tab' => 'triage']) }}" class="btn btn-light position-relative">
            <i class="bi bi-bell me-1"></i>รอคัดกรอง
            <span class="badge rounded-pill bg-danger ms-1" id="triageBadge">{{ $triage }}</span>
        </a>
        <a href="{{ route('teams.index') }}" class="btn btn-soft"><i class="bi bi-people me-1"></i>ทีม</a>
    </x-page-head>

    <div id="boardWrap" data-url="{{ route('dispatch.index', ['partial' => 1]) }}">
        @include('admin.dispatch._board')
    </div>

    <div class="card mt-3">
        <div class="card-header"><i class="bi bi-people text-primary"></i> ทีมในจังหวัด <span class="ch-actions small text-muted">พร้อม {{ $teams->where('status', 'available')->count() }} / ติดภารกิจ {{ $teams->where('status', 'busy')->count() }}</span></div>
        <div class="card-body">
            @if($teams->isEmpty())
                <x-empty icon="people" title="ยังไม่มีทีม" class="py-2"><a href="{{ route('teams.index') }}" class="btn btn-sm btn-primary">เพิ่มทีม</a></x-empty>
            @else
                <div class="hscroll-desktop d-flex gap-2 flex-wrap">
                    @foreach($teams as $t)
                        <a href="{{ route('teams.show', $t) }}" class="team-pill text-reset">
                            <span class="tp-dot" style="background:{{ $t->statusColor() }}"></span>
                            <span class="fw-600 small">{{ $t->name }}</span>
                            <span class="small text-muted">
                                @foreach($t->vehicles->where('status', '!=', 'maintenance')->unique('type') as $v)<i class="bi bi-{{ $v->icon() }}" title="{{ $v->typeLabel() }}"></i> @endforeach
                            </span>
                            @if($t->activeAssignments->isNotEmpty())
                                <span class="small text-primary">{{ $t->activeAssignments->map(fn ($a) => $a->helpRequest?->code)->filter()->implode(', ') }}</span>
                            @else
                                <span class="small text-muted">{{ $t->statusLabel() }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    @include('admin.dispatch._modals')
@endsection

@push('scripts')
    @include('admin.dispatch._scripts')
@endpush
