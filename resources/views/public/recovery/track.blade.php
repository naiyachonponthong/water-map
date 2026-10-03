@extends('layouts.public')
@section('title', 'คำร้อง '.$claim->code)

@section('content')
    @use('App\Support\RecoveryOptions')
    <header class="pub-head">
        <div class="inner d-flex align-items-center gap-2">
            <a href="{{ route('public.recovery', $province) }}" class="text-white fs-4 me-1" aria-label="กลับ"><i class="bi bi-arrow-left"></i></a>
            <div><div class="fw-bold lh-sm">คำร้องเยียวยา {{ $claim->code }}</div><div class="small opacity-75">{{ $province->fullName() }}</div></div>
        </div>
    </header>

    <div class="pub-wrap">
        <div class="pub-float">
            <div class="card mb-3"><div class="card-body">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="chip {{ $claim->statusChip() }} fs-6">{{ $claim->statusLabel() }}</span>
                    <span class="small text-muted ms-auto">ยื่นเมื่อ {{ thai_date($claim->created_at, 'compact') }}</span>
                </div>
                <div>{{ RecoveryOptions::STATUS[$claim->status][2] ?? '' }}</div>
                @if($claim->status === 'rejected' && $claim->reject_reason)<div class="small text-danger mt-1">เหตุผล: {{ $claim->reject_reason }}</div>@endif
                @if(in_array($claim->status, ['approved', 'paid'], true) && $claim->approved_amount !== null)
                    <div class="mt-2">ยอดที่อนุมัติ <b class="mono">{{ number_format($claim->approved_amount, 2) }}</b> บาท</div>
                @endif
                @if($claim->status === 'paid')<div class="small text-success">จ่ายแล้ว {{ thai_date($claim->paid_at, 'compact') }}</div>@endif
            </div></div>

            @php $steps = ['submitted' => 'ยื่นคำร้อง', 'surveying' => 'นัดสำรวจ', 'surveyed' => 'สำรวจแล้ว', 'approved' => 'อนุมัติ', 'paid' => 'จ่ายแล้ว']; $idx = array_search($claim->status, array_keys($steps), true); @endphp
            @if($claim->status !== 'rejected')
                <div class="card mb-3"><div class="card-body">
                    <div class="d-flex justify-content-between small text-center">
                        @foreach($steps as $k => $l)
                            <div class="{{ $idx !== false && $loop->index <= $idx ? 'text-success fw-600' : 'text-muted' }}">
                                <i class="bi bi-{{ $idx !== false && $loop->index <= $idx ? 'check-circle-fill' : 'circle' }} fs-5 d-block"></i>{{ $l }}
                            </div>
                        @endforeach
                    </div>
                </div></div>
            @endif

            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-clock-history text-primary"></i> ความคืบหน้า</div>
                @foreach($events as $e)
                    <div class="list-row small"><span class="text-muted text-nowrap">{{ thai_date($e->created_at, 'compact') }}</span><span>{{ $e->note }}</span></div>
                @endforeach
            </div>

            <div class="card"><div class="card-body small">
                <div><b>{{ $claim->head_name }}</b> · {{ $claim->address }}</div>
                <div class="text-muted">แจ้งไว้: {{ $claim->houseLabel($claim->house_damage) }}{{ $claim->lossLabels() ? ' · '.implode(', ', $claim->lossLabels()) : '' }}</div>
                <div class="mt-2">สอบถาม โทร <a href="tel:{{ preg_replace('/\D+/', '', $hotline) }}">{{ $hotline }}</a> แจ้งเลขคำร้อง {{ $claim->code }}</div>
            </div></div>
        </div>
    </div>
@endsection
