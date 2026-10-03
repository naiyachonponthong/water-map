@extends('layouts.app')
@section('title', 'ประวัติการใช้งาน')

@section('content')
    <x-page-head title="ประวัติการใช้งาน" sub="ทุกการเพิ่ม แก้ไข ลบ และการเข้าสู่ระบบ ถูกบันทึกไว้ 365 วัน" />

    <form method="GET" class="card mb-3">
        <div class="card-body d-flex flex-wrap gap-2 align-items-end">
            <div>
                <label class="form-label small">ผู้ใช้</label>
                <select name="user" class="form-select form-select-sm" style="width:200px" data-search data-placeholder="ทุกคน">
                    <option value="">ทุกคน</option>
                    @foreach($users as $u)<option value="{{ $u->id }}" @selected(request('user') == $u->id)>{{ $u->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="form-label small">การกระทำ</label>
                <select name="action" class="form-select form-select-sm" style="width:170px">
                    <option value="">ทั้งหมด</option>
                    @foreach($actions as $k => $v)<option value="{{ $k }}" @selected(request('action') === $k)>{{ $v }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="form-label small">ข้อมูล</label>
                <select name="subject" class="form-select form-select-sm" style="width:160px">
                    <option value="">ทั้งหมด</option>
                    @foreach($subjects as $k => $v)<option value="{{ $k }}" @selected(request('subject') === $k)>{{ $v }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="form-label small">วันที่</label>
                <input type="date" name="date" value="{{ request('date') }}" class="form-control form-control-sm">
            </div>
            @if(auth()->user()->isSuperAdmin())
                <div class="form-check mb-1">
                    <input class="form-check-input" type="checkbox" name="all" value="1" id="allProv" @checked(request()->boolean('all'))>
                    <label class="form-check-label small" for="allProv">ทุกจังหวัด</label>
                </div>
            @endif
            <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-funnel"></i> กรอง</button>
            @if(request()->hasAny(['user', 'action', 'subject', 'date', 'all']))
                <a href="{{ route('admin.audit.index') }}" class="btn btn-sm btn-light">ล้าง</a>
            @endif
        </div>
    </form>

    <div class="card table-card">
        @if($logs->isEmpty())
            <x-empty icon="clock-history" title="ไม่พบประวัติ" />
        @else
            <div class="table-responsive">
                <table class="table table-hover table-stack">
                    <thead><tr><th>เวลา</th><th>ผู้ใช้</th><th>การกระทำ</th><th>รายละเอียด</th><th>IP</th><th></th></tr></thead>
                    <tbody>
                    @foreach($logs as $log)
                        @php
                            $tone = match($log->action) { 'created', 'approved' => 'chip-success', 'deleted', 'suspended', 'login_failed' => 'chip-danger', 'updated', 'switch' => 'chip-primary', default => '' };
                        @endphp
                        <tr>
                            <td data-label="เวลา" class="small text-nowrap">{{ thai_date($log->created_at, 'compact') }}</td>
                            <td class="td-main">
                                <div class="d-flex align-items-center gap-2"><x-avatar :user="$log->user" size="xs" /><span class="small fw-600">{{ $log->user?->name ?? 'ไม่ระบุ' }}</span></div>
                            </td>
                            <td data-label="การกระทำ"><span class="chip {{ $tone }}">{{ $log->actionLabel() }}</span></td>
                            <td data-label="รายละเอียด" class="small">
                                {{ $log->description ?: trim($log->subjectLabel().' #'.$log->subject_id) }}
                                @if(request()->boolean('all') && $log->province)<span class="text-muted">· {{ $log->province->name_th }}</span>@endif
                            </td>
                            <td data-label="IP" class="small mono text-muted">{{ $log->ip }}</td>
                            <td class="text-end">
                                @if($log->before || $log->after)
                                    <button class="btn btn-sm btn-light btn-icon" data-audit="{{ route('admin.audit.show', $log) }}" title="ดูข้อมูลที่เปลี่ยน"><i class="bi bi-eye"></i></button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
    <div class="mt-3">{{ $logs->links() }}</div>

    <div class="modal fade" id="auditModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">ข้อมูลที่เปลี่ยน</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body" id="auditBody"></div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('click', async e => {
            const b = e.target.closest('[data-audit]');
            if (!b) return;
            const body = document.getElementById('auditBody');
            body.innerHTML = '<div class="text-center py-4 text-muted">กำลังโหลด...</div>';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('auditModal')).show();
            const res = await fetch(b.dataset.audit, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            body.innerHTML = res.ok ? await res.text() : '<div class="text-danger">โหลดข้อมูลไม่ได้</div>';
        });
    </script>
@endpush
