@extends('layouts.app')
@section('title', $team->name)

@section('content')
    @use('App\Support\TeamOptions')
    @php
        $fill = $team->only(['name', 'type', 'leader_id', 'phone', 'district_id', 'base_address', 'base_lat', 'base_lng', 'note', 'is_active']);
        $leaders = $team->members->merge($candidates);
    @endphp

    <div class="page-head">
        <div>
            <div class="small text-muted"><a href="{{ route('teams.index') }}"><i class="bi bi-arrow-left"></i> ทีมทั้งหมด</a></div>
            <h1 class="d-flex flex-wrap align-items-center gap-2">{{ $team->name }} <span class="chip chip-dot {{ $team->statusChip() }}" style="font-size:.85rem">{{ $team->statusLabel() }}</span></h1>
            <div class="ph-sub">{{ $team->typeLabel() }}{{ $team->district ? ' · พื้นที่ อ.'.$team->district->name_th : '' }}{{ $team->is_active ? '' : ' · ปิดใช้งาน' }}</div>
        </div>
        <div class="ph-actions">
            <div class="dropdown">
                <button class="btn btn-soft" data-bs-toggle="dropdown" type="button"><i class="bi bi-circle-fill me-1" style="color:{{ $team->statusColor() }};font-size:.6rem"></i>เปลี่ยนสถานะ</button>
                <ul class="dropdown-menu dropdown-menu-end">
                    @foreach(TeamOptions::STATUSES as $k => [$label, , $color])
                        <li>
                            <form method="POST" action="{{ route('teams.status', $team) }}">@csrf<input type="hidden" name="status" value="{{ $k }}">
                                <button class="dropdown-item" type="submit"><i class="bi bi-circle-fill me-2" style="color:{{ $color }};font-size:.6rem"></i>{{ $label }}</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </div>
            <button class="btn btn-light" data-form-modal="#teamModal" data-action="{{ route('teams.update', $team) }}" data-method="PUT" data-title="แก้ไขทีม" data-fill="{{ json_encode($fill) }}"><i class="bi bi-pencil"></i> แก้ไข</button>
            <form method="POST" action="{{ route('teams.destroy', $team) }}" data-confirm="ลบทีม {{ $team->name }}?">@csrf @method('DELETE')
                <button class="btn btn-light text-danger btn-icon" type="submit" title="ลบทีม"><i class="bi bi-trash"></i></button>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><x-stat label="งานที่ปิดแล้ว" :value="$stats['done']" icon="check2-circle" tint="success" /></div>
        <div class="col-6 col-lg-3"><x-stat label="คนที่ช่วยออกมา" :value="$stats['rescued']" icon="people" tint="primary" /></div>
        <div class="col-6 col-lg-3"><x-stat label="เวลาเฉลี่ยถึงที่เกิดเหตุ" :value="$stats['avg_response'] !== null ? $stats['avg_response'].' นาที' : '-'" icon="stopwatch" tint="info" /></div>
        <div class="col-6 col-lg-3"><x-stat label="ปฏิเสธ / ไม่ตอบ" :value="$stats['declined']" icon="x-circle" tint="warning" /></div>
    </div>

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-info-circle text-primary"></i> ข้อมูลทีม</div>
                <div class="card-body">
                    <dl class="kv mb-0">
                        <dt>หัวหน้าทีม</dt><dd>{{ $team->leader?->name ?? '-' }}@if($team->leader) <a href="tel:{{ $team->leader->phone }}" class="mono ms-1">{{ phone_format($team->leader->phone) }}</a>@endif</dd>
                        <dt>เบอร์กลาง</dt><dd>@if($team->phone)<a href="tel:{{ $team->phone }}" class="mono">{{ phone_format($team->phone) }}</a>@else - @endif</dd>
                        <dt>ฐาน</dt><dd>{{ $team->base_address ?: '-' }}@if($team->base_lat)<div class="small text-muted mono">{{ $team->base_lat }}, {{ $team->base_lng }}</div>@endif</dd>
                        <dt>พิกัดล่าสุด</dt><dd>{{ $team->last_seen_at ? thai_date($team->last_seen_at, 'ago') : 'ยังไม่เคยส่ง' }}</dd>
                        @if($team->note)<dt>หมายเหตุ</dt><dd>{{ $team->note }}</dd>@endif
                    </dl>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-truck text-primary"></i> ยานพาหนะ
                    <div class="ch-actions"><button class="btn btn-sm btn-primary" data-form-modal="#vehicleModal" data-action="{{ route('teams.vehicles.store', $team) }}" data-method="POST" data-title="เพิ่มยานพาหนะ"><i class="bi bi-plus-lg"></i></button></div>
                </div>
                @forelse($team->vehicles as $v)
                    <div class="d-flex align-items-center gap-3 px-3 py-2 border-bottom">
                        <span class="st-icon tint-blue d-grid" style="width:38px;height:38px;border-radius:12px;place-items:center"><i class="bi bi-{{ $v->icon() }}"></i></span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-600">{{ $v->name }} <span class="chip ms-1 {{ $v->status === 'ready' ? 'chip-success' : ($v->status === 'maintenance' ? 'chip-danger' : 'chip-primary') }}">{{ TeamOptions::VEHICLE_STATUSES[$v->status] }}</span></div>
                            <div class="small text-muted">{{ $v->typeLabel() }}{{ $v->plate ? ' · '.$v->plate : '' }}{{ $v->capacity ? ' · '.$v->capacity.' คน' : '' }}</div>
                        </div>
                        <button class="btn btn-sm btn-light btn-icon" data-form-modal="#vehicleModal" data-action="{{ route('vehicles.update', $v) }}" data-method="PUT" data-title="แก้ไขยานพาหนะ" data-fill="{{ json_encode($v->only(['type', 'name', 'plate', 'capacity', 'status'])) }}"><i class="bi bi-pencil"></i></button>
                        <form method="POST" action="{{ route('vehicles.destroy', $v) }}" data-confirm="ลบ {{ $v->name }}?">@csrf @method('DELETE')<button class="btn btn-sm btn-light btn-icon text-danger" type="submit"><i class="bi bi-trash"></i></button></form>
                    </div>
                @empty
                    <x-empty icon="truck" title="ยังไม่มียานพาหนะ" text="ระบบใช้ข้อมูลนี้แนะนำทีมที่เข้าพื้นที่น้ำลึกได้" class="py-3" />
                @endforelse
            </div>

            <div class="card">
                <div class="card-header"><i class="bi bi-people text-primary"></i> สมาชิก <span class="ch-actions small text-muted">{{ $team->members->count() }} คน</span></div>
                @foreach($team->members as $m)
                    <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom">
                        <x-avatar :user="$m" size="sm" />
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-600">{{ $m->name }} @if($m->pivot->role_in_team === 'leader')<span class="chip chip-primary ms-1">หัวหน้าทีม</span>@endif</div>
                            <div class="small text-muted mono">{{ phone_format($m->phone) }}</div>
                        </div>
                        @if($m->pivot->role_in_team !== 'leader')
                            <form method="POST" action="{{ route('teams.members.leader', [$team, $m]) }}">@csrf<button class="btn btn-sm btn-light" type="submit" title="ตั้งเป็นหัวหน้าทีม"><i class="bi bi-star"></i></button></form>
                        @endif
                        <form method="POST" action="{{ route('teams.members.destroy', [$team, $m]) }}" data-confirm="นำ {{ $m->name }} ออกจากทีม?">@csrf @method('DELETE')<button class="btn btn-sm btn-light btn-icon text-danger" type="submit"><i class="bi bi-x-lg"></i></button></form>
                    </div>
                @endforeach
                <form method="POST" action="{{ route('teams.members.store', $team) }}" class="card-body d-flex gap-2 flex-wrap">
                    @csrf
                    <div class="flex-grow-1" style="min-width:200px">
                        <select name="user_id" class="form-select form-select-sm" data-search data-placeholder="เพิ่มสมาชิกจากผู้ใช้ในจังหวัด">
                            <option value="">เพิ่มสมาชิกจากผู้ใช้ในจังหวัด</option>
                            @foreach($candidates as $u)<option value="{{ $u->id }}">{{ $u->name }} · {{ phone_format($u->phone) }}{{ $u->organization ? ' · '.$u->organization : '' }}</option>@endforeach
                        </select>
                    </div>
                    <select name="role_in_team" class="form-select form-select-sm" style="width:120px"><option value="member">สมาชิก</option><option value="leader">หัวหน้าทีม</option></select>
                    <button class="btn btn-sm btn-primary" type="submit">เพิ่ม</button>
                    <div class="form-text w-100 mt-0">ยังไม่มีบัญชี? ให้สมาชิกสมัครที่หน้าเข้าสู่ระบบ หรือเพิ่มในเมนู ผู้ใช้และสิทธิ์ ก่อน</div>
                </form>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card table-card">
                <div class="card-header"><i class="bi bi-clock-history text-primary"></i> งานล่าสุดของทีม</div>
                @if($assignments->isEmpty())
                    <x-empty icon="life-preserver" title="ยังไม่เคยรับงาน" />
                @else
                    <div class="table-responsive">
                        <table class="table table-hover table-stack">
                            <thead><tr><th>เคส</th><th>สถานะงาน</th><th>รับงาน</th><th>ถึงใน</th><th>ช่วยได้</th></tr></thead>
                            <tbody>
                            @foreach($assignments as $a)
                                <tr>
                                    <td class="td-main"><a href="{{ route('cases.show', $a->help_request_id) }}" class="mono">{{ $a->helpRequest?->code }}</a> <span class="small">{{ $a->helpRequest?->requester_name }}</span></td>
                                    <td data-label="สถานะงาน"><span class="chip {{ $a->statusChip() }}">{{ $a->statusLabel() }}</span>@if($a->decline_reason)<div class="small text-muted">{{ $a->decline_reason }}</div>@endif</td>
                                    <td data-label="รับงาน" class="small">{{ thai_date($a->offered_at, 'compact') }}</td>
                                    <td data-label="ถึงใน" class="small">{{ $a->responseMinutes() !== null ? $a->responseMinutes().' นาที' : '-' }}</td>
                                    <td data-label="ช่วยได้" class="small">{{ $a->people_rescued ?? '-' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @include('admin.teams._form', ['leaders' => $leaders])

    <x-form-modal id="vehicleModal" title="เพิ่มยานพาหนะ" :action="route('teams.vehicles.store', $team)">
        <div class="row g-3">
            <div class="col-6">
                <label class="form-label req">ประเภท</label>
                <select name="type" class="form-select" data-default="boat">
                    @foreach(TeamOptions::VEHICLES as $k => [$l])<option value="{{ $k }}">{{ $l }}</option>@endforeach
                </select>
            </div>
            <div class="col-6">
                <label class="form-label req">สถานะ</label>
                <select name="status" class="form-select" data-default="ready">
                    @foreach(TeamOptions::VEHICLE_STATUSES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                </select>
            </div>
            <div class="col-12">
                <label class="form-label req">ชื่อ</label>
                <input name="name" class="form-control" placeholder="เช่น เรือท้องแบน ลำ 1">
            </div>
            <div class="col-7">
                <label class="form-label">ทะเบียน / หมายเลข</label>
                <input name="plate" class="form-control">
            </div>
            <div class="col-5">
                <label class="form-label">จุคน</label>
                <input name="capacity" type="number" min="1" max="200" class="form-control">
            </div>
        </div>
    </x-form-modal>
@endsection
