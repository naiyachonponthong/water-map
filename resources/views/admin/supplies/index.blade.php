@extends('layouts.app')
@section('title', 'ของบริจาคและคลัง')

@section('content')
    @use('App\Support\ReliefOptions')

    <x-page-head title="ของบริจาคและคลัง" sub="คลังกลาง + คลังย่อยที่ศูนย์พักพิง ยอดคงเหลือคำนวณจากรายการรับ-จ่ายทั้งหมด ตรวจย้อนหลังได้">
        <button class="btn btn-soft" data-form-modal="#itemModal" data-action="{{ route('supplies.items.store') }}" data-method="POST" data-title="เพิ่มรายการของ"><i class="bi bi-plus-lg me-1"></i>เพิ่มรายการของ</button>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#moveModal" @disabled($items->isEmpty())><i class="bi bi-arrow-left-right me-1"></i>รับ / จ่าย / โอน</button>
    </x-page-head>

    @if($low->isNotEmpty())
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i>
            <div><b>ของในคลังกลางใกล้หมด:</b>
                @foreach($low as $l){{ $l['item']->name }} เหลือ {{ number_format($l['qty']) }} {{ $l['item']->unit }}@if(! $loop->last), @endif @endforeach
            </div>
        </div>
    @endif

    @if($needs->isNotEmpty())
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-basket text-warning"></i> ศูนย์พักพิงต้องการ</div>
            <div class="card-body d-flex flex-wrap gap-2">
                @foreach($needs as $n)
                    <span class="chip {{ $n->priority === 'urgent' ? 'chip-danger' : '' }}"><b>{{ $n->shelter->name }}</b>: {{ $n->item }}{{ $n->qty ? ' '.number_format($n->qty).' '.$n->unit : '' }}</span>
                @endforeach
            </div>
        </div>
    @endif

    <div class="card table-card mb-3">
        <div class="card-header"><i class="bi bi-box-seam text-primary"></i> ยอดคงเหลือ
            <form class="ch-actions" method="GET">
                <select name="category" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">ทุกหมวด</option>
                    @foreach(ReliefOptions::SUPPLY_CATEGORIES as $k => [$l])<option value="{{ $k }}" @selected(request('category') === $k)>{{ $l }}</option>@endforeach
                </select>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>รายการ</th>
                        <th class="text-end">คลังกลาง</th>
                        @foreach($shelters as $s)<th class="text-end small text-nowrap">{{ \Illuminate\Support\Str::limit($s->name, 18) }}</th>@endforeach
                        <th class="text-end">รวม</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $i)
                        @php $row = $table[$i->id] ?? collect(); $central = $row[0] ?? 0; @endphp
                        <tr class="{{ $i->is_active ? '' : 'opacity-50' }}">
                            <td><i class="bi bi-{{ $i->icon() }} text-muted me-1"></i>{{ $i->name }} <span class="small text-muted">{{ $i->unit }}</span></td>
                            <td class="text-end mono fw-600 {{ $i->min_stock && $central < $i->min_stock ? 'text-danger' : '' }}">{{ number_format($central) }}</td>
                            @foreach($shelters as $s)<td class="text-end mono">{{ ($row[$s->id] ?? 0) ? number_format($row[$s->id]) : '-' }}</td>@endforeach
                            <td class="text-end mono">{{ number_format($row->sum()) }}</td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-light btn-icon" data-form-modal="#itemModal" data-action="{{ route('supplies.items.update', $i) }}" data-method="PUT" data-title="แก้ไขรายการ"
                                    data-fill="{{ json_encode($i->only(['name', 'category', 'unit', 'min_stock', 'is_active'])) }}"><i class="bi bi-pencil"></i></button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ 4 + $shelters->count() }}"><x-empty icon="box-seam" title="ยังไม่มีรายการของ" text="เพิ่มรายการ เช่น น้ำดื่ม ข้าวสาร ถุงยังชีพ ยาสามัญ แล้วบันทึกรับเข้า" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><i class="bi bi-clock-history text-primary"></i> รับ-จ่ายล่าสุด</div>
        @forelse($movements as $m)
            <div class="list-row small">
                <span class="chip {{ $m->qty > 0 ? 'chip-success' : 'chip-warning' }}">{{ $m->kindLabel() }}</span>
                <div class="flex-grow-1 min-w-0">
                    <b>{{ $m->item->name }}</b> <span class="mono">{{ $m->qty > 0 ? '+' : '' }}{{ number_format($m->qty) }}</span> {{ $m->item->unit }}
                    · {{ $m->shelter?->name ?? 'คลังกลาง' }}
                    @if($m->donor) · ผู้บริจาค {{ $m->donor }}@endif
                    @if($m->note)<div class="text-muted">{{ $m->note }}</div>@endif
                </div>
                <span class="text-muted text-nowrap">{{ $m->user?->name }} · {{ thai_date($m->created_at, 'ago') }}</span>
            </div>
        @empty
            <div class="card-body small text-muted">ยังไม่มีรายการ</div>
        @endforelse
    </div>

    <x-form-modal id="itemModal" title="เพิ่มรายการของ" :action="route('supplies.items.store')">
        <div class="mb-3"><label class="form-label">ชื่อรายการ</label><input name="name" class="form-control" maxlength="120" value="{{ old('name') }}" placeholder="เช่น น้ำดื่ม 600 มล."></div>
        <div class="row g-2">
            <div class="col-6"><label class="form-label">หมวด</label>
                <select name="category" class="form-select" data-default="food">@foreach(ReliefOptions::SUPPLY_CATEGORIES as $k => [$l])<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
            </div>
            <div class="col-3"><label class="form-label">หน่วย</label><input name="unit" class="form-control" maxlength="20" value="{{ old('unit', 'ชิ้น') }}" data-default="ชิ้น"></div>
            <div class="col-3"><label class="form-label">ขั้นต่ำ</label><input name="min_stock" type="number" min="0" class="form-control" value="{{ old('min_stock', 0) }}" data-default="0"></div>
        </div>
        <div class="form-check form-switch mt-3" data-only="edit">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="itActive" data-default="1" checked><label class="form-check-label" for="itActive">ใช้งาน</label>
        </div>
    </x-form-modal>

    <div class="modal fade" id="moveModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" action="{{ route('supplies.move') }}">
                @csrf
                <div class="modal-header"><h5 class="modal-title">รับ / จ่าย / โอน</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="btn-group w-100 mb-3" role="group">
                        @foreach(['in' => 'รับเข้า', 'out' => 'จ่ายออก', 'transfer' => 'โอน', 'adjust' => 'ตรวจนับ'] as $k => $l)
                            <input type="radio" class="btn-check" name="kind" value="{{ $k }}" id="mk{{ $k }}" @checked(old('kind', 'in') === $k)>
                            <label class="btn btn-outline-primary" for="mk{{ $k }}">{{ $l }}</label>
                        @endforeach
                    </div>
                    <label class="form-label">รายการ</label>
                    <select name="supply_item_id" class="form-select mb-2" data-search>
                        @foreach($items->where('is_active', true) as $i)<option value="{{ $i->id }}" @selected(old('supply_item_id') == $i->id)>{{ $i->name }} ({{ $i->unit }})</option>@endforeach
                    </select>
                    <div class="row g-2 mb-2">
                        <div class="col-6 mv-from"><label class="form-label">จาก</label>
                            <select name="from" class="form-select"><option value="">คลังกลาง</option>@foreach($shelters as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select>
                        </div>
                        <div class="col-6 mv-to"><label class="form-label mv-to-label">เข้าที่</label>
                            <select name="to" class="form-select"><option value="">คลังกลาง</option>@foreach($shelters as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select>
                        </div>
                    </div>
                    <label class="form-label mv-qty-label">จำนวน</label>
                    <input name="qty" type="number" min="0" class="form-control mb-2" required value="{{ old('qty') }}">
                    <input name="donor" class="form-control mb-2 mv-donor" maxlength="150" placeholder="ผู้บริจาค / หน่วยงาน" value="{{ old('donor') }}">
                    <input name="note" class="form-control" maxlength="255" placeholder="หมายเหตุ" value="{{ old('note') }}">
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button><button class="btn btn-primary" type="submit">บันทึก</button></div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const m = document.getElementById('moveModal');
            const sync = () => {
                const k = m.querySelector('[name=kind]:checked').value;
                m.querySelector('.mv-from').classList.toggle('d-none', !['out', 'transfer'].includes(k));
                m.querySelector('.mv-to').classList.toggle('d-none', !['in', 'transfer', 'adjust'].includes(k));
                m.querySelector('.mv-to-label').textContent = k === 'adjust' ? 'คลังที่ตรวจนับ' : (k === 'transfer' ? 'ไปที่' : 'เข้าที่');
                m.querySelector('.mv-qty-label').textContent = k === 'adjust' ? 'จำนวนที่นับได้จริง' : 'จำนวน';
                m.querySelector('.mv-donor').classList.toggle('d-none', k !== 'in');
            };
            m.querySelectorAll('[name=kind]').forEach(r => r.addEventListener('change', sync));
            sync();
        })();
    </script>
@endpush
