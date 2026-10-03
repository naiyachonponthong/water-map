@extends('layouts.public')
@section('title', 'ค้นหาญาติในศูนย์พักพิง')

@section('content')
    <header class="pub-head">
        <div class="inner d-flex align-items-center gap-2">
            <a href="{{ route('public.province', $province) }}" class="text-white fs-4 me-1" aria-label="กลับ"><i class="bi bi-arrow-left"></i></a>
            <div><div class="fw-bold lh-sm">ค้นหาญาติในศูนย์พักพิง</div><div class="small opacity-75">{{ $province->fullName() }}</div></div>
        </div>
    </header>

    <div class="pub-wrap">
        <div class="pub-float">
            <form method="POST" action="{{ route('public.find.lookup', $province) }}" class="card mb-3">
                @csrf
                <div class="card-body">
                    <label class="form-label fw-600">เบอร์โทรของญาติที่ให้ไว้ตอนลงทะเบียน</label>
                    <div class="input-group input-group-lg">
                        <input name="phone" type="tel" inputmode="tel" class="form-control mono @error('phone') is-invalid @enderror" maxlength="15" value="{{ old('phone', $phone) }}" placeholder="08x-xxx-xxxx" required>
                        <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
                    </div>
                    @error('phone')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    <div class="form-text">เพื่อความเป็นส่วนตัว ต้องใส่เบอร์ให้ตรงทั้งหมด และแสดงเฉพาะคนที่ยินยอมให้ค้นหา ระบบไม่เก็บเบอร์ที่คุณค้น <a href="{{ route('public.privacy', $province) }}" target="_blank">ประกาศความเป็นส่วนตัว</a></div>
                </div>
            </form>

            @if($results !== null)
                @forelse($results as $r)
                    <div class="card mb-2"><div class="card-body">
                        <div class="fw-bold"><i class="bi bi-person-check text-success"></i> {{ $r['name'] }}</div>
                        <div class="mt-1">อยู่ที่ <b>{{ $r['shelter'] }}</b></div>
                        @if($r['address'])<div class="small text-muted">{{ $r['address'] }}</div>@endif
                        <div class="small text-muted">ลงทะเบียน {{ $r['since'] }}</div>
                        <div class="d-flex gap-2 mt-2">
                            <a class="btn btn-sm btn-primary" target="_blank" rel="noopener" href="https://www.google.com/maps/dir/?api=1&destination={{ $r['lat'] }},{{ $r['lng'] }}"><i class="bi bi-sign-turn-right"></i> นำทาง</a>
                            @if($r['contact'])<a class="btn btn-sm btn-light" href="tel:{{ preg_replace('/\D+/', '', $r['contact']) }}"><i class="bi bi-telephone"></i> โทรศูนย์</a>@endif
                        </div>
                    </div></div>
                @empty
                    <div class="card"><x-empty icon="search" title="ไม่พบ" text="อาจยังไม่ได้ลงทะเบียน ใช้เบอร์อื่น หรือไม่ยินยอมให้ค้นหา ลองโทรถามศูนย์พักพิงโดยตรง" />
                        <div class="card-body pt-0 text-center"><a href="{{ route('public.shelters', $province) }}">ดูรายชื่อศูนย์พักพิง</a></div>
                    </div>
                @endforelse
            @endif
        </div>
    </div>
@endsection
