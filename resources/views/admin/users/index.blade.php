@extends('layouts.app')
@section('title', 'ผู้ใช้และสิทธิ์')

@section('content')
    @php
        $me = auth()->user();
        $statusTabs = ['all' => 'ทั้งหมด', 'pending' => 'รออนุมัติ', 'active' => 'ใช้งาน', 'suspended' => 'ระงับ'];
        $statusChip = ['pending' => ['chip-warning', 'รออนุมัติ'], 'active' => ['chip-success', 'ใช้งาน'], 'suspended' => ['chip-danger', 'ระงับ']];
    @endphp

    <x-page-head title="ผู้ใช้และสิทธิ์" sub="อนุมัติบัญชีใหม่ กำหนดบทบาท และดูแลบัญชีเจ้าหน้าที่ของ{{ $me->isSuperAdmin() ? 'ทุกจังหวัด' : $currentProvince?->fullName() }}">
        <button class="btn btn-primary" data-form-modal="#userModal" data-action="{{ route('admin.users.store') }}" data-method="POST" data-title="เพิ่มผู้ใช้">
            <i class="bi bi-person-plus me-1"></i>เพิ่มผู้ใช้
        </button>
    </x-page-head>

    @if(session('credential'))
        @php $cr = session('credential'); @endphp
        <div class="alert alert-primary">
            <i class="bi bi-key"></i>
            <div class="flex-grow-1">
                <div class="fw-600">ข้อมูลเข้าสู่ระบบของ {{ $cr['name'] }}</div>
                <div>เบอร์โทร <b class="mono">{{ phone_format($cr['phone']) }}</b>
                    @if($cr['password']) · รหัสผ่าน <b class="mono">{{ $cr['password'] }}</b>@endif
                </div>
                <div class="small opacity-75">ระบบแสดงรหัสผ่านครั้งเดียว ส่งให้เจ้าของบัญชีแล้วแนะนำให้เปลี่ยนเองหลังเข้าระบบ</div>
            </div>
            @if($cr['password'])
                <button class="btn btn-sm btn-primary" data-copy="ระบบศูนย์ช่วยเหลือน้ำท่วม {{ url('/login') }} เบอร์ {{ $cr['phone'] }} รหัสผ่าน {{ $cr['password'] }}"><i class="bi bi-clipboard"></i> คัดลอก</button>
            @endif
        </div>
    @endif

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <ul class="nav nav-pills">
            @foreach($statusTabs as $key => $label)
                @php $n = $key === 'all' ? $counts->sum() : ($counts[$key] ?? 0); @endphp
                <li class="nav-item">
                    <a class="nav-link {{ $status === $key ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['status' => $key, 'page' => null]) }}">
                        {{ $label }}<span class="count">{{ $n }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
        <form class="ms-lg-auto d-flex flex-wrap gap-2" method="GET">
            <input type="hidden" name="status" value="{{ $status }}">
            @if($me->isSuperAdmin())
                <select name="province" class="form-select form-select-sm" style="width:170px" data-search data-placeholder="ทุกจังหวัด" onchange="this.form.submit()">
                    <option value="">ทุกจังหวัด</option>
                    @foreach($provinces as $p)
                        <option value="{{ $p->id }}" @selected(request('province') == $p->id)>{{ $p->name_th }}</option>
                    @endforeach
                </select>
            @endif
            <select name="role" class="form-select form-select-sm" style="width:170px" onchange="this.form.submit()">
                <option value="">ทุกบทบาท</option>
                @foreach($allRoles as $key => $label)
                    <option value="{{ $key }}" @selected(request('role') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <div class="input-group input-group-sm" style="width:230px">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="search" name="q" class="form-control" placeholder="ชื่อ เบอร์ หน่วยงาน" value="{{ request('q') }}">
            </div>
        </form>
    </div>

    <div class="card table-card">
        @if($users->isEmpty())
            <x-empty icon="people" title="ไม่พบผู้ใช้" text="ลองเปลี่ยนตัวกรอง หรือเพิ่มผู้ใช้ใหม่" />
        @else
            <div class="table-responsive">
                <table class="table table-hover table-stack">
                    <thead>
                    <tr>
                        <th>ผู้ใช้</th>
                        <th>บทบาท</th>
                        <th>หน่วยงาน</th>
                        @if($me->isSuperAdmin())<th>จังหวัด</th>@endif
                        <th>สถานะ</th>
                        <th>เข้าระบบล่าสุด</th>
                        <th class="text-end"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($users as $u)
                        @php
                            $role = $u->roles->first()?->name;
                            $fill = [
                                'name' => $u->name, 'phone' => $u->phone, 'email' => $u->email,
                                'organization' => $u->organization, 'position' => $u->position,
                                'role' => $role ?? $u->requested_role, 'province_id' => $u->province_id,
                            ];
                        @endphp
                        <tr>
                            <td class="td-main">
                                <div class="d-flex align-items-center gap-2">
                                    <x-avatar :user="$u" size="sm" />
                                    <div class="min-w-0">
                                        <div class="fw-600">{{ $u->name }} @if($u->id === $me->id)<span class="chip chip-primary ms-1">คุณ</span>@endif</div>
                                        <div class="small text-muted mono">{{ phone_format($u->phone) }}@if($u->line_user_id) <i class="bi bi-line text-success" title="ผูก LINE แล้ว"></i>@endif</div>
                                    </div>
                                </div>
                            </td>
                            <td data-label="บทบาท">
                                @if($role)
                                    <span class="chip chip-primary">{{ $allRoles[$role] ?? $role }}</span>
                                @else
                                    <span class="small text-muted">ขอเป็น {{ $allRoles[$u->requested_role] ?? '-' }}</span>
                                @endif
                            </td>
                            <td data-label="หน่วยงาน" class="small">{{ $u->organization ?: '-' }}@if($u->position)<div class="text-muted">{{ $u->position }}</div>@endif</td>
                            @if($me->isSuperAdmin())<td data-label="จังหวัด" class="small">{{ $u->province?->name_th ?? 'ทุกจังหวัด' }}</td>@endif
                            <td data-label="สถานะ"><span class="chip chip-dot {{ $statusChip[$u->status][0] }}">{{ $statusChip[$u->status][1] }}</span></td>
                            <td data-label="เข้าระบบล่าสุด" class="small text-muted">{{ $u->last_login_at ? thai_date($u->last_login_at, 'ago') : 'ยังไม่เคย' }}</td>
                            <td class="text-end text-nowrap">
                                @if($u->status === 'pending')
                                    <button class="btn btn-sm btn-primary" data-form-modal="#approveModal" data-action="{{ route('admin.users.approve', $u) }}" data-method="POST"
                                            data-title="อนุมัติ {{ $u->name }}" data-fill="{{ json_encode(['role' => $u->requested_role ?: 'team-member', 'who' => $u->name.' · '.($u->organization ?: '-')]) }}">
                                        <i class="bi bi-check2 me-1"></i>อนุมัติ
                                    </button>
                                @endif
                                <div class="dropdown d-inline-block">
                                    <button class="btn btn-sm btn-light btn-icon" data-bs-toggle="dropdown" type="button"><i class="bi bi-three-dots"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><button class="dropdown-item" type="button" data-form-modal="#userModal" data-action="{{ route('admin.users.update', $u) }}" data-method="PUT" data-title="แก้ไข {{ $u->name }}" data-fill="{{ json_encode($fill) }}"><i class="bi bi-pencil me-2"></i>แก้ไข</button></li>
                                        @if($u->id !== $me->id)
                                            <li>
                                                <form method="POST" action="{{ route('admin.users.reset-password', $u) }}" data-confirm="ตั้งรหัสผ่านใหม่ให้ {{ $u->name }}?">@csrf
                                                    <button class="dropdown-item" type="submit"><i class="bi bi-key me-2"></i>ตั้งรหัสผ่านใหม่</button>
                                                </form>
                                            </li>
                                            @if($u->two_factor_confirmed_at)
                                                <li>
                                                    <form method="POST" action="{{ route('admin.users.reset-2fa', $u) }}" data-confirm="ล้างยืนยันตัวตน 2 ชั้นของ {{ $u->name }}? (เช่น ทำมือถือหาย)">@csrf
                                                        <button class="dropdown-item" type="submit"><i class="bi bi-shield-x me-2"></i>ล้างยืนยัน 2 ชั้น</button>
                                                    </form>
                                                </li>
                                            @endif
                                            @if($u->status === 'active')
                                                <li>
                                                    <form method="POST" action="{{ route('admin.users.suspend', $u) }}" data-confirm="ระงับบัญชี {{ $u->name }}? ผู้ใช้จะถูกออกจากระบบทันที">@csrf
                                                        <button class="dropdown-item text-warning" type="submit"><i class="bi bi-pause-circle me-2"></i>ระงับบัญชี</button>
                                                    </form>
                                                </li>
                                            @elseif($u->status === 'suspended')
                                                <li>
                                                    <form method="POST" action="{{ route('admin.users.activate', $u) }}">@csrf
                                                        <button class="dropdown-item" type="submit"><i class="bi bi-play-circle me-2"></i>เปิดใช้งานอีกครั้ง</button>
                                                    </form>
                                                </li>
                                            @endif
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form method="POST" action="{{ route('admin.users.destroy', $u) }}" data-confirm="ลบบัญชี {{ $u->name }}?">@csrf @method('DELETE')
                                                    <button class="dropdown-item text-danger" type="submit"><i class="bi bi-trash me-2"></i>ลบ</button>
                                                </form>
                                            </li>
                                        @endif
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
    <div class="mt-3">{{ $users->links() }}</div>

    {{-- เพิ่ม/แก้ไขผู้ใช้ --}}
    <x-form-modal id="userModal" title="เพิ่มผู้ใช้" :action="route('admin.users.store')" size="lg">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label req">ชื่อ-นามสกุล</label>
                <input name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label req">เบอร์โทร</label>
                <input name="phone" type="tel" inputmode="numeric" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone') }}" required>
                @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label req">บทบาท</label>
                <select name="role" class="form-select @error('role') is-invalid @enderror" data-default="team-member">
                    @foreach($roles as $key => $label)
                        <option value="{{ $key }}" @selected(old('role', 'team-member') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            @if($me->isSuperAdmin())
                <div class="col-md-6">
                    <label class="form-label">จังหวัด</label>
                    <select name="province_id" class="form-select @error('province_id') is-invalid @enderror" data-search data-placeholder="เลือกจังหวัด" data-default="{{ $currentProvince?->id }}">
                        <option value="">ทุกจังหวัด (ผู้ดูแลระบบสูงสุด)</option>
                        @foreach($provinces as $p)
                            <option value="{{ $p->id }}" @selected(old('province_id', $currentProvince?->id) == $p->id)>{{ $p->name_th }}</option>
                        @endforeach
                    </select>
                    @error('province_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
            @endif
            <div class="col-md-6">
                <label class="form-label">หน่วยงาน / มูลนิธิ</label>
                <input name="organization" class="form-control" value="{{ old('organization') }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">ตำแหน่ง</label>
                <input name="position" class="form-control" value="{{ old('position') }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">อีเมล</label>
                <input name="email" type="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}">
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label">รหัสผ่าน</label>
                <input name="password" type="text" autocomplete="off" class="form-control @error('password') is-invalid @enderror">
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text" data-only="create">เว้นว่างให้ระบบสร้างรหัสผ่านให้</div>
                <div class="form-text d-none" data-only="edit">เว้นว่างถ้าไม่เปลี่ยน</div>
            </div>
        </div>
    </x-form-modal>

    {{-- อนุมัติ --}}
    <x-form-modal id="approveModal" title="อนุมัติบัญชี" :action="route('admin.users.index')" submit="อนุมัติ">
        <input type="text" name="who" class="form-control-plaintext fw-600 pt-0" readonly tabindex="-1">
        <label class="form-label req mt-2">กำหนดบทบาท</label>
        <select name="role" class="form-select">
            @foreach($roles as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </select>
        <div class="form-text">ค่าตั้งต้นคือบทบาทที่ผู้ใช้ขอมา ปรับได้ตามหน้าที่จริง</div>
    </x-form-modal>
@endsection
