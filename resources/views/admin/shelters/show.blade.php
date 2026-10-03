@extends('layouts.app')
@section('title', $shelter->name)

@push('head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
@endpush

@section('content')
    @use('App\Support\ReliefOptions')

    <x-page-head :title="$shelter->name" :sub="$shelter->typeLabel().' · '.$shelter->areaLabel().($shelter->contact_phone ? ' · '.$shelter->contact_phone : '')">
        <a href="{{ route('shelters.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>ศูนย์ทั้งหมด</a>
        @can('evacuees.manage')
            @if($shelter->status !== 'closed')
                <a href="{{ route('shelters.register', $shelter) }}" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>ลงทะเบียนเข้าศูนย์</a>
            @endif
        @endcan
    </x-page-head>

    <div class="row g-3 mb-3">
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-end gap-2 mb-2">
                        <div class="fs-1 fw-bold mono lh-1">{{ number_format($shelter->occupancy) }}</div>
                        <div class="text-muted mb-1">{{ $shelter->capacity ? 'จาก '.number_format($shelter->capacity).' คน · ว่าง '.number_format($shelter->available()) : 'คนในศูนย์' }}</div>
                        <span class="chip chip-dot {{ $shelter->statusChip() }} ms-auto mb-1">{{ $shelter->statusLabel() }}</span>
                    </div>
                    @if($shelter->capacity)
                        <div class="progress mb-3" style="height:10px"><div class="progress-bar" style="width:{{ $shelter->percent() }}%;background:{{ $shelter->percent() >= 90 ? '#dc2626' : '#0d9488' }}"></div></div>
                    @endif
                    <div class="d-flex flex-wrap gap-1 mb-3">
                        @foreach(ReliefOptions::AGE_GROUPS as $k => $l)
                            @if($summary['age'][$k] ?? 0)<span class="chip">{{ $l }} {{ $summary['age'][$k] }}</span>@endif
                        @endforeach
                        @if($summary['families'])<span class="chip"><i class="bi bi-people"></i> {{ $summary['families'] }} ครอบครัว</span>@endif
                        @foreach($summary['needs'] as $k => $n)
                            <span class="chip chip-danger"><i class="bi bi-{{ ReliefOptions::EVACUEE_NEEDS[$k][1] ?? 'dot' }}"></i> {{ ReliefOptions::EVACUEE_NEEDS[$k][0] ?? $k }} {{ $n }}</span>
                        @endforeach
                    </div>
                    <form method="POST" action="{{ route('shelters.status', $shelter) }}" class="d-flex flex-wrap gap-1">
                        @csrf
                        @foreach(ReliefOptions::SHELTER_STATUS as $k => [$l])
                            <button class="btn btn-sm {{ $shelter->status === $k ? 'btn-primary' : 'btn-light' }}" name="status" value="{{ $k }}" type="submit">{{ $l }}</button>
                        @endforeach
                    </form>
                    @if($shelter->facilities)
                        <div class="d-flex flex-wrap gap-2 small text-muted mt-3">
                            @foreach($shelter->facilities as $f)<span><i class="bi bi-{{ ReliefOptions::FACILITIES[$f][1] ?? 'dot' }}"></i> {{ ReliefOptions::FACILITIES[$f][0] ?? $f }}</span>@endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-basket text-warning"></i> ศูนย์ต้องการ <span class="ch-actions small text-muted">แสดงบนเว็บประชาชน</span></div>
                <div class="card-body pt-2">
                    @forelse($needs as $n)
                        <div class="d-flex align-items-center gap-2 py-1 {{ $n->status === 'fulfilled' ? 'opacity-50 text-decoration-line-through' : '' }}">
                            @if($n->priority === 'urgent')<span class="chip chip-danger">ด่วน</span>@endif
                            <span class="small flex-grow-1">{{ $n->item }}{{ $n->qty ? ' '.number_format($n->qty).' '.$n->unit : '' }}</span>
                            <form method="POST" action="{{ route('shelters.needs.toggle', $n) }}">@csrf<button class="btn btn-sm btn-light btn-icon" type="submit" title="{{ $n->status === 'open' ? 'ได้รับแล้ว' : 'เปิดอีกครั้ง' }}"><i class="bi bi-{{ $n->status === 'open' ? 'check2' : 'arrow-counterclockwise' }}"></i></button></form>
                        </div>
                    @empty
                        <div class="small text-muted mb-2">ยังไม่มีรายการ</div>
                    @endforelse
                    <form method="POST" action="{{ route('shelters.needs.store', $shelter) }}" class="row g-1 mt-2">
                        @csrf
                        <div class="col-12"><input name="item" class="form-control form-control-sm" maxlength="120" placeholder="เช่น นมผงเด็ก, ผ้าอ้อมผู้ใหญ่ ไซซ์ L" required></div>
                        <div class="col-4"><input name="qty" type="number" min="1" class="form-control form-control-sm" placeholder="จำนวน"></div>
                        <div class="col-3"><input name="unit" class="form-control form-control-sm" maxlength="20" placeholder="หน่วย"></div>
                        <div class="col-5 d-flex gap-1">
                            <select name="priority" class="form-select form-select-sm"><option value="normal">ปกติ</option><option value="urgent">ด่วน</option></select>
                            <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-plus-lg"></i></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-3">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-box-seam text-primary"></i> ของในศูนย์</div>
                <div class="card-body pt-2 small">
                    @forelse($stock as $r)
                        <div class="d-flex justify-content-between py-1 border-bottom"><span>{{ $r['item']->name }}</span><span class="mono fw-600 {{ $r['qty'] <= 0 ? 'text-danger' : '' }}">{{ number_format($r['qty']) }} {{ $r['item']->unit }}</span></div>
                    @empty
                        <div class="text-muted">ยังไม่มีของในคลังศูนย์</div>
                    @endforelse
                    @can('supplies.manage')<a href="{{ route('supplies.index') }}" class="d-block mt-2">จัดการคลัง</a>@endcan
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><i class="bi bi-people text-primary"></i> รายชื่อผู้อพยพ
            <form class="ch-actions d-flex gap-2" method="GET">
                <select name="show" class="form-select form-select-sm" style="width:140px" onchange="this.form.submit()">
                    <option value="">อยู่ในศูนย์</option>
                    <option value="all" @selected(request('show') === 'all')>ทั้งหมด</option>
                </select>
                <input type="search" name="q" class="form-control form-control-sm" style="width:200px" placeholder="ชื่อ รหัส เบอร์" value="{{ request('q') }}">
            </form>
        </div>
        @forelse($evacuees as $e)
            <div class="list-row {{ $e->status !== 'in' ? 'opacity-50' : '' }}">
                <span class="avatar avatar-sm flex-shrink-0">{{ mb_substr($e->name, 0, 1) }}</span>
                <div class="flex-grow-1 min-w-0">
                    <div class="d-flex flex-wrap align-items-center gap-1">
                        <span class="fw-600 me-1">{{ $e->name }}</span>
                        <span class="small mono text-muted">{{ $e->code }}</span>
                        @if($e->family_code)<span class="chip" title="ครอบครัว"><i class="bi bi-people"></i> {{ $e->family_code }}</span>@endif
                        @if($e->ageLabel())<span class="chip">{{ $e->ageLabel() }}</span>@endif
                        @foreach($e->needLabels() as $n)<span class="chip chip-danger">{{ $n }}</span>@endforeach
                        @if($e->status !== 'in')<span class="chip">ออกแล้ว: {{ $e->out_reason }}</span>@endif
                    </div>
                    <div class="small text-muted">
                        เข้า {{ thai_date($e->checked_in_at, 'compact') }}
                        @if($e->phone) · <a href="tel:{{ $e->phone }}" class="mono">{{ phone_format($e->phone) }}</a>@endif
                        @if($e->help_request_id) · <a href="{{ route('cases.show', $e->help_request_id) }}">จากเคส</a>@endif
                        @unless($e->allow_lookup) · <i class="bi bi-eye-slash" title="ไม่ให้ค้นหา"></i>@endunless
                    </div>
                    @if($e->note)<div class="small">{{ $e->note }}</div>@endif
                </div>
                @can('evacuees.manage')
                    @if($e->status === 'in')
                        <div class="d-flex gap-1 flex-shrink-0">
                            <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#outModal" data-ev-action="{{ route('evacuees.checkout', $e) }}" data-ev-name="{{ $e->name }}" data-ev-family="{{ $e->family_code ? 1 : 0 }}">ออก</button>
                            <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#moveModal" data-ev-action="{{ route('evacuees.transfer', $e) }}" data-ev-name="{{ $e->name }}" data-ev-family="{{ $e->family_code ? 1 : 0 }}">ย้าย</button>
                        </div>
                    @endif
                @endcan
            </div>
        @empty
            <x-empty icon="people" title="ยังไม่มีผู้อพยพ" />
        @endforelse
    </div>
    <div class="mt-3">{{ $evacuees->links() }}</div>

    @can('shelters.manage')
        <div class="d-flex flex-wrap gap-2 mt-3">
            <button class="btn btn-light btn-sm" data-form-modal="#shelterModal" data-action="{{ route('shelters.update', $shelter) }}" data-method="PUT" data-title="แก้ไขศูนย์"
                data-fill="{{ json_encode($shelter->only(['name', 'type', 'address', 'lat', 'lng', 'capacity', 'status', 'facilities', 'contact_name', 'contact_phone', 'note', 'is_public'])) }}"><i class="bi bi-pencil me-1"></i>แก้ไขข้อมูลศูนย์</button>
            <button class="btn btn-light btn-sm" data-bs-toggle="collapse" data-bs-target="#staffBox"><i class="bi bi-person-badge me-1"></i>เจ้าหน้าที่ประจำศูนย์ ({{ $shelter->staff->count() }})</button>
        </div>
        <div class="collapse mt-2" id="staffBox">
            <form method="POST" action="{{ route('shelters.staff', $shelter) }}" class="card card-body">
                @csrf @method('PUT')
                @php $staffUsers = \App\Models\User::where('province_id', $shelter->province_id)->where('status', 'active')->role('shelter-staff')->orderBy('name')->get(['id', 'name']); @endphp
                <select name="user_ids[]" class="form-select mb-2" multiple data-search data-placeholder="เลือกเจ้าหน้าที่ศูนย์พักพิง">
                    @foreach($staffUsers as $u)<option value="{{ $u->id }}" @selected($shelter->staff->contains('id', $u->id))>{{ $u->name }}</option>@endforeach
                </select>
                <div class="form-text mb-2">เจ้าหน้าที่ที่ถูกกำหนดจะเห็นเฉพาะศูนย์ของตัวเอง</div>
                <div><button class="btn btn-primary btn-sm" type="submit">บันทึก</button></div>
            </form>
        </div>
        @include('admin.shelters._form', ['province' => $shelter->province])
    @endcan

    {{-- ออกจากศูนย์ --}}
    <div class="modal fade" id="outModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST">
                @csrf
                <div class="modal-header"><h5 class="modal-title">บันทึกออกจากศูนย์</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="fw-600 mb-2" data-ev-label></div>
                    <select name="reason" class="form-select mb-2">@foreach(ReliefOptions::OUT_REASONS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                    <div class="form-check" data-ev-fam><input class="form-check-input" type="checkbox" name="family" value="1" id="outFam" checked><label class="form-check-label" for="outFam">ทั้งครอบครัว</label></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button><button class="btn btn-primary" type="submit">บันทึก</button></div>
            </form>
        </div>
    </div>
    {{-- ย้ายศูนย์ --}}
    <div class="modal fade" id="moveModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST">
                @csrf
                <div class="modal-header"><h5 class="modal-title">ย้ายไปศูนย์อื่น</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="fw-600 mb-2" data-ev-label></div>
                    <select name="to" class="form-select mb-2" required>
                        @foreach($others as $o)<option value="{{ $o->id }}">{{ $o->name }}{{ $o->capacity ? ' (ว่าง '.max(0, $o->capacity - $o->occupancy).')' : '' }}</option>@endforeach
                    </select>
                    <div class="form-check" data-ev-fam><input class="form-check-input" type="checkbox" name="family" value="1" id="mvFam" checked><label class="form-check-label" for="mvFam">ทั้งครอบครัว</label></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button><button class="btn btn-primary" type="submit" @disabled($others->isEmpty())>ย้าย</button></div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="{{ asset('js/map.js') }}?v={{ filemtime(public_path('js/map.js')) }}"></script>
    <script>
        ['outModal', 'moveModal'].forEach(id => document.getElementById(id).addEventListener('show.bs.modal', e => {
            const b = e.relatedTarget, m = e.target;
            m.querySelector('form').action = b.dataset.evAction;
            m.querySelector('[data-ev-label]').textContent = b.dataset.evName;
            m.querySelector('[data-ev-fam]').classList.toggle('d-none', b.dataset.evFamily !== '1');
        }));
    </script>
@endpush
