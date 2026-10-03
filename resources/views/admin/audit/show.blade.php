@php
    $keys = collect(array_keys(($log->before ?? []) + ($log->after ?? [])))->unique();
    $fmt = fn ($v) => is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (is_bool($v) ? ($v ? 'ใช่' : 'ไม่') : ($v === null || $v === '' ? '-' : (string) $v));
@endphp
<div class="small text-muted mb-3">
    {{ thai_date($log->created_at, 'datetime') }} · {{ $log->user?->name ?? 'ไม่ระบุ' }} · {{ $log->actionLabel() }} {{ $log->subjectLabel() }}
    @if($log->subject_id) #{{ $log->subject_id }}@endif
    @if($log->province) · {{ $log->province->name_th }}@endif
</div>
@if($keys->isEmpty())
    <x-empty icon="file-earmark" title="ไม่มีรายละเอียด" />
@else
    <div class="table-responsive">
        <table class="table table-sm">
            <thead><tr><th>ฟิลด์</th><th>ก่อน</th><th>หลัง</th></tr></thead>
            <tbody>
            @foreach($keys as $k)
                <tr>
                    <td class="mono small">{{ $k }}</td>
                    <td class="small text-danger text-break">{{ $fmt($log->before[$k] ?? null) }}</td>
                    <td class="small text-success text-break">{{ $fmt($log->after[$k] ?? null) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
<div class="small text-muted mt-2 text-break">{{ $log->user_agent }}</div>
