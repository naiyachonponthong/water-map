@extends('layouts.guest')
@section('title', 'ขอใช้งานระบบ')

@section('content')
    <h2 class="fw-bold mb-1" style="letter-spacing:-.02em">ขอใช้งานระบบ</h2>
    <p class="text-muted mb-4">ผู้อำนวยการศูนย์ของจังหวัดจะตรวจสอบและอนุมัติบัญชีก่อนใช้งาน</p>

    @if($line)
        <div class="alert alert-success py-2">
            <img src="{{ $line['picture'] }}" class="avatar avatar-sm" alt="" referrerpolicy="no-referrer">
            <div class="small">เชื่อม LINE <b>{{ $line['name'] }}</b> แล้ว ครั้งหน้าเข้าสู่ระบบด้วย LINE ได้เลย</div>
        </div>
    @endif

    <form method="POST" action="{{ route('register.store') }}" novalidate>
        @csrf
        <div class="mb-3">
            <label class="form-label req" for="name">ชื่อ-นามสกุล</label>
            <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $line['name'] ?? '') }}" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label class="form-label req" for="phone">เบอร์โทร</label>
            <input type="tel" inputmode="numeric" class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone') }}" placeholder="08x-xxx-xxxx" required>
            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text">ใช้เป็นชื่อผู้ใช้ และให้ศูนย์ติดต่อกลับ</div>
        </div>
        <div class="row g-3 mb-3">
            <div class="col-sm-6">
                <label class="form-label req" for="province_id">จังหวัดที่ปฏิบัติงาน</label>
                <select class="form-select @error('province_id') is-invalid @enderror" id="province_id" name="province_id" data-search data-placeholder="เลือกจังหวัด" required>
                    <option value="">เลือกจังหวัด</option>
                    @foreach($provinces as $p)
                        <option value="{{ $p->id }}" @selected(old('province_id') == $p->id)>{{ $p->name_th }}</option>
                    @endforeach
                </select>
                @error('province_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-sm-6">
                <label class="form-label req" for="requested_role">บทบาทที่ขอ</label>
                <select class="form-select @error('requested_role') is-invalid @enderror" id="requested_role" name="requested_role" required>
                    @foreach($roles as $key => $label)
                        <option value="{{ $key }}" @selected(old('requested_role', 'team-member') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="row g-3 mb-3">
            <div class="col-sm-7">
                <label class="form-label req" for="organization">หน่วยงาน / มูลนิธิ</label>
                <input class="form-control @error('organization') is-invalid @enderror" id="organization" name="organization" value="{{ old('organization') }}" placeholder="เช่น มูลนิธิสว่างประชาสามัคคี" required>
                @error('organization')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-sm-5">
                <label class="form-label" for="position">ตำแหน่ง</label>
                <input class="form-control" id="position" name="position" value="{{ old('position') }}">
            </div>
        </div>
        <div class="row g-3 mb-4">
            <div class="col-sm-6">
                <label class="form-label {{ $line ? '' : 'req' }}" for="password">รหัสผ่าน</label>
                <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" autocomplete="new-password" {{ $line ? '' : 'required' }}>
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-sm-6">
                <label class="form-label {{ $line ? '' : 'req' }}" for="password_confirmation">ยืนยันรหัสผ่าน</label>
                <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" autocomplete="new-password">
            </div>
            <div class="col-12 form-text mt-1">อย่างน้อย 8 ตัว มีทั้งตัวอักษรและตัวเลข{{ $line ? ' (เว้นว่างได้ ถ้าจะเข้าด้วย LINE อย่างเดียว)' : '' }}</div>
        </div>
        <button type="submit" class="btn btn-primary btn-lg w-100">ส่งคำขอใช้งาน</button>
    </form>

    <div class="text-center mt-4 small">มีบัญชีแล้ว? <a href="{{ route('login') }}" class="fw-600">เข้าสู่ระบบ</a></div>
@endsection
