@extends('layouts.app')
@section('title', 'ข้อมูลส่วนตัว')

@section('content')
    <x-page-head title="ข้อมูลส่วนตัว" />

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body text-center py-4">
                    <x-avatar :user="$user" size="xl" class="mx-auto mb-2" />
                    <div class="fw-bold fs-5">{{ $user->name }}</div>
                    <div class="text-muted small">{{ $user->roleLabel() }}{{ $user->province ? ' · '.$user->province->fullName() : '' }}</div>
                    <div class="mt-3 d-flex justify-content-center gap-2 flex-wrap">
                        <span class="chip"><i class="bi bi-telephone"></i> {{ phone_format($user->phone) }}</span>
                        @if($user->line_user_id)<span class="chip chip-success"><i class="bi bi-line"></i> {{ $user->line_display_name ?: 'ผูก LINE แล้ว' }}</span>@endif
                    </div>
                </div>
                <div class="card-body border-top">
                    <div class="fw-600 mb-2"><i class="bi bi-line text-success me-1"></i>LINE</div>
                    @error('line')<div class="alert alert-danger small py-2"><i class="bi bi-exclamation-circle"></i><div>{{ $message }}</div></div>@enderror
                    @if($user->line_user_id)
                        <p class="small text-muted mb-2">เข้าสู่ระบบด้วย LINE ได้ และจะได้รับแจ้งเตือนงานผ่าน LINE ในเฟสถัดไป</p>
                        <form method="POST" action="{{ route('profile.line.unlink') }}" data-confirm="ยกเลิกการผูก LINE?">@csrf @method('DELETE')
                            <button class="btn btn-sm btn-light w-100" type="submit">ยกเลิกการผูก LINE</button>
                        </form>
                    @elseif($lineEnabled)
                        <p class="small text-muted mb-2">ผูกไว้เพื่อเข้าสู่ระบบด้วย LINE โดยไม่ต้องจำรหัสผ่าน</p>
                        <a href="{{ route('line.redirect') }}" class="btn btn-sm btn-line w-100"><i class="bi bi-line me-1"></i>ผูกบัญชี LINE</a>
                    @else
                        <p class="small text-muted mb-0">ผู้ดูแลระบบยังไม่ได้ตั้งค่า LINE Login</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <form method="POST" action="{{ route('profile.update') }}" class="card mb-3">
                @csrf @method('PUT')
                <div class="card-header"><i class="bi bi-person text-primary"></i> ข้อมูลทั่วไป</div>
                <div class="card-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label req">ชื่อ-นามสกุล</label>
                        <input name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $user->name) }}">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label req">เบอร์โทร</label>
                        <input name="phone" type="tel" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $user->phone) }}">
                        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">หน่วยงาน</label>
                        <input name="organization" class="form-control" value="{{ old('organization', $user->organization) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">ตำแหน่ง</label>
                        <input name="position" class="form-control" value="{{ old('position', $user->position) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">อีเมล</label>
                        <input name="email" type="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $user->email) }}">
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0 text-end pb-3 pe-3"><button class="btn btn-primary" type="submit">บันทึก</button></div>
            </form>

            <form method="POST" action="{{ route('profile.password') }}" class="card">
                @csrf @method('PUT')
                <div class="card-header"><i class="bi bi-key text-primary"></i> {{ $user->password ? 'เปลี่ยนรหัสผ่าน' : 'ตั้งรหัสผ่าน' }}</div>
                <div class="card-body row g-3">
                    @if($user->password)
                        <div class="col-md-4">
                            <label class="form-label req">รหัสผ่านเดิม</label>
                            <input name="current_password" type="password" class="form-control @error('current_password') is-invalid @enderror" autocomplete="current-password">
                            @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    @endif
                    <div class="col-md-4">
                        <label class="form-label req">รหัสผ่านใหม่</label>
                        <input name="password" type="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password">
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label req">ยืนยันรหัสผ่านใหม่</label>
                        <input name="password_confirmation" type="password" class="form-control" autocomplete="new-password">
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0 text-end pb-3 pe-3"><button class="btn btn-primary" type="submit">บันทึกรหัสผ่าน</button></div>
            </form>

            {{-- ยืนยันตัวตน 2 ชั้น --}}
            <div class="card mt-3" id="two-factor">
                <div class="card-header"><i class="bi bi-shield-lock text-primary"></i> ยืนยันตัวตน 2 ชั้น
                    <span class="ch-actions">{!! $user->hasTwoFactor() ? '<span class="chip chip-success">เปิดอยู่</span>' : ($user->mustUseTwoFactor() ? '<span class="chip chip-danger">บทบาทนี้ต้องเปิด</span>' : '<span class="chip">ปิดอยู่</span>') !!}</span>
                </div>
                <div class="card-body">
                    @if(session('recovery_codes'))
                        <div class="alert alert-warning">
                            <i class="bi bi-key"></i>
                            <div>
                                <div class="fw-600 mb-1">รหัสสำรอง ใช้ได้รหัสละครั้งเมื่อไม่มีมือถือ เก็บไว้ที่ปลอดภัย จะแสดงครั้งเดียว</div>
                                <div class="mono d-grid gap-1" style="grid-template-columns:repeat(2,minmax(0,1fr))">@foreach(session('recovery_codes') as $c)<span>{{ $c }}</span>@endforeach</div>
                                <button type="button" class="btn btn-sm btn-light mt-2" data-copy="{{ implode("\n", session('recovery_codes')) }}"><i class="bi bi-clipboard"></i> คัดลอก</button>
                            </div>
                        </div>
                    @endif

                    @if($user->hasTwoFactor())
                        <p class="small text-muted mb-3">เข้าสู่ระบบทุกครั้งต้องกรอกรหัส 6 หลักจากแอป Authenticator เปิดใช้เมื่อ {{ thai_date($user->two_factor_confirmed_at) }} · รหัสสำรองเหลือ {{ count($user->two_factor_recovery_codes ?? []) }} รหัส</p>
                        @unless($user->password)<div class="alert alert-info small py-2"><i class="bi bi-info-circle"></i><div>ตั้งรหัสผ่านด้านบนก่อน จึงจะสร้างรหัสสำรองใหม่หรือปิด 2 ชั้นได้</div></div>@endunless
                        <div class="row g-2">
                            <form method="POST" action="{{ route('profile.2fa.recovery') }}" class="col-md-6">@csrf
                                <div class="input-group input-group-sm"><input name="password" type="password" class="form-control @error('password') is-invalid @enderror" placeholder="รหัสผ่าน" autocomplete="current-password"><button class="btn btn-light" type="submit">สร้างรหัสสำรองใหม่</button></div>
                            </form>
                            @unless($user->mustUseTwoFactor())
                                <form method="POST" action="{{ route('profile.2fa.disable') }}" class="col-md-6" data-confirm="ปิดยืนยันตัวตน 2 ชั้น?">@csrf @method('DELETE')
                                    <div class="input-group input-group-sm"><input name="password" type="password" class="form-control" placeholder="รหัสผ่าน" autocomplete="current-password"><button class="btn btn-outline-danger" type="submit">ปิด 2 ชั้น</button></div>
                                </form>
                            @endunless
                        </div>
                        @error('password')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    @elseif($user->two_factor_secret)
                        @php $otpUri = \App\Support\Totp::uri($user->two_factor_secret, $user->phone, config('app.name', 'FloodThai')); @endphp
                        <div class="row g-3 align-items-center">
                            <div class="col-auto"><div id="otpQr" class="p-2 bg-white border rounded-3" data-uri="{{ $otpUri }}" style="width:176px;height:176px"></div></div>
                            <div class="col">
                                <ol class="small ps-3 mb-2">
                                    <li>ติดตั้ง Google Authenticator หรือ Microsoft Authenticator บนมือถือ</li>
                                    <li>สแกน QR นี้ (หรือพิมพ์รหัส <span class="mono user-select-all">{{ trim(chunk_split($user->two_factor_secret, 4, ' ')) }}</span>)</li>
                                    <li>กรอกรหัส 6 หลักที่แอปแสดง</li>
                                </ol>
                                <form method="POST" action="{{ route('profile.2fa.confirm') }}" class="d-flex gap-2" style="max-width:280px">@csrf
                                    <input name="code" class="form-control mono @error('code') is-invalid @enderror" inputmode="numeric" maxlength="7" placeholder="123456" autocomplete="one-time-code" required>
                                    <button class="btn btn-primary" type="submit">ยืนยัน</button>
                                </form>
                                @error('code')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    @else
                        <p class="small text-muted">เพิ่มความปลอดภัย แม้รหัสผ่านหลุด คนอื่นก็เข้าบัญชีไม่ได้ถ้าไม่มีมือถือของคุณ ใช้แอปฟรี Google Authenticator หรือ Microsoft Authenticator</p>
                        <form method="POST" action="{{ route('profile.2fa.enable') }}">@csrf<button class="btn btn-primary" type="submit"><i class="bi bi-shield-plus me-1"></i>เปิดยืนยันตัวตน 2 ชั้น</button></form>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @if(! $user->hasTwoFactor() && $user->two_factor_secret)
        {{-- สร้าง QR ในเบราว์เซอร์ รหัสลับไม่ถูกส่งออกไปที่อื่น --}}
        <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
        <script>
            (function () {
                const el = document.getElementById('otpQr');
                if (window.QRCode && el) new QRCode(el, { text: el.dataset.uri, width: 160, height: 160, correctLevel: QRCode.CorrectLevel.M });
                document.getElementById('two-factor').scrollIntoView({ block: 'center' });
            })();
        </script>
    @endif
@endpush
