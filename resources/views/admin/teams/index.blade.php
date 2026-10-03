@extends('layouts.app')
@section('title', 'ทีมกู้ภัยและทรัพยากร')

@section('content')
    @use('App\Support\TeamOptions')

    <x-page-head title="ทีมกู้ภัยและทรัพยากร" sub="ทีม สมาชิก และยานพาหนะที่ศูนย์มอบหมายงานได้">
        <button class="btn btn-primary" data-form-modal="#teamModal" data-action="{{ route('teams.store') }}" data-method="POST" data-title="เพิ่มทีม"><i class="bi bi-plus-lg me-1"></i>เพิ่มทีม</button>
    </x-page-head>

    <div class="row g-3 mb-3">
        @foreach(TeamOptions::STATUSES as $k => [$label, , $color])
            <div class="col-6 col-lg-3">
                <a href="{{ request('status') === $k ? route('teams.index') : route('teams.index', ['status' => $k]) }}" class="card card-lift text-reset {{ request('status') === $k ? 'border border-2 border-primary' : '' }}">
                    <div class="card-body stat">
                        <span class="st-icon" style="background:{{ $color }}1a;color:{{ $color }}"><i class="bi bi-circle-fill" style="font-size:.8rem"></i></span>
                        <div><div class="st-num">{{ $counts[$k] ?? 0 }}</div><div class="st-label">{{ $label }}</div></div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    @if($vehicleCounts->isNotEmpty())
        <div class="d-flex flex-wrap gap-2 mb-3">
            <span class="small text-muted align-self-center">ยานพาหนะพร้อมใช้:</span>
            @foreach($vehicleCounts as $type => $n)
                <span class="chip"><i class="bi bi-{{ TeamOptions::VEHICLES[$type][1] ?? 'gear' }}"></i> {{ TeamOptions::VEHICLES[$type][0] ?? $type }} {{ $n }}</span>
            @endforeach
        </div>
    @endif

    @if($teams->isEmpty())
        <div class="card">
            <x-empty icon="people" title="ยังไม่มีทีม" text="เพิ่มทีมกู้ภัย มูลนิธิ อปพร. หรืออาสาสมัครที่ศูนย์ประสานได้">
                <button class="btn btn-primary" data-form-modal="#teamModal" data-action="{{ route('teams.store') }}" data-method="POST" data-title="เพิ่มทีม">เพิ่มทีมแรก</button>
            </x-empty>
        </div>
    @else
        <div class="row g-3">
            @foreach($teams as $t)
                <div class="col-md-6 col-xxl-4">
                    <a href="{{ route('teams.show', $t) }}" class="card card-lift text-reset h-100 {{ $t->is_active ? '' : 'opacity-50' }}">
                        <div class="card-body">
                            <div class="d-flex align-items-start gap-2 mb-2">
                                <span class="app-ico {{ $t->status === 'available' ? 'teal' : ($t->status === 'busy' ? 'blue' : 'slate') }}" style="width:44px;height:44px;border-radius:14px;font-size:1.2rem"><i class="bi bi-people-fill"></i></span>
                                <div class="min-w-0 flex-grow-1">
                                    <div class="fw-bold text-truncate">{{ $t->name }}</div>
                                    <div class="small text-muted">{{ $t->typeLabel() }}{{ $t->district ? ' · อ.'.$t->district->name_th : '' }}</div>
                                </div>
                                <span class="chip chip-dot {{ $t->statusChip() }}">{{ $t->statusLabel() }}</span>
                            </div>
                            <div class="small mb-2">
                                <i class="bi bi-person-badge text-muted"></i> {{ $t->leader?->name ?? 'ยังไม่มีหัวหน้าทีม' }}
                                @if($t->phone || $t->leader)<span class="mono text-muted ms-1">{{ phone_format($t->phone ?: $t->leader?->phone) }}</span>@endif
                            </div>
                            <div class="d-flex flex-wrap gap-1">
                                <span class="chip"><i class="bi bi-people"></i> {{ $t->members_count }} คน</span>
                                @foreach($t->vehicles as $v)
                                    <span class="chip {{ $v->status === 'maintenance' ? 'text-decoration-line-through' : '' }}" title="{{ $v->name }}"><i class="bi bi-{{ $v->icon() }}"></i> {{ $v->typeLabel() }}{{ $v->capacity ? ' '.$v->capacity : '' }}</span>
                                @endforeach
                                @if($t->active_assignments_count)<span class="chip chip-primary"><i class="bi bi-life-preserver"></i> งาน {{ $t->active_assignments_count }}</span>@endif
                            </div>
                            @if($t->last_seen_at)
                                <div class="small text-muted mt-2"><i class="bi bi-broadcast"></i> ส่งพิกัด {{ thai_date($t->last_seen_at, 'ago') }}</div>
                            @endif
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif

    @include('admin.teams._form')
@endsection
