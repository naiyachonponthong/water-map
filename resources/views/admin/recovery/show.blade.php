@extends('layouts.app')
@section('title', $claim->code)

@push('head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
@endpush

@section('content')
    @use('App\Support\RecoveryOptions')

    <x-page-head :title="$claim->head_name" :sub="$claim->code.' · '.$claim->statusLabel()">
        <a href="{{ route('recovery.index', ['tab' => $claim->status]) }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>รายการ</a>
    </x-page-head>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card mb-3"><div class="card-body">
                <div class="d-flex flex-wrap gap-1 mb-2">
                    <span class="chip {{ $claim->statusChip() }}">{{ $claim->statusLabel() }}</span>
                    <span class="chip">แจ้ง: {{ $claim->houseLabel($claim->house_damage) }}</span>
                    @if($claim->verified_damage)<span class="chip chip-primary"><i class="bi bi-patch-check"></i> สำรวจพบ: {{ $claim->houseLabel($claim->verified_damage) }}</span>@endif
                    <span class="chip">{{ RecoveryOptions::TENURE[$claim->tenure] ?? $claim->tenure }}</span>
                </div>
                <dl class="row small mb-0">
                    <dt class="col-sm-3 text-muted fw-normal">เบอร์โทร</dt><dd class="col-sm-9"><a href="tel:{{ $claim->phone }}" class="mono fw-600">{{ phone_format($claim->phone) }}</a></dd>
                    <dt class="col-sm-3 text-muted fw-normal">ที่อยู่</dt><dd class="col-sm-9">{{ $claim->address }} <span class="text-muted">{{ $claim->areaLabel() }}</span></dd>
                    <dt class="col-sm-3 text-muted fw-normal">สมาชิก</dt><dd class="col-sm-9">{{ $claim->members }} คน</dd>
                    <dt class="col-sm-3 text-muted fw-normal">น้ำท่วม</dt><dd class="col-sm-9">{{ $claim->water_level ? config('floodthai.water_levels.'.$claim->water_level.'.label') : '-' }}{{ $claim->flood_days ? ' · '.$claim->flood_days.' วัน' : '' }}</dd>
                    <dt class="col-sm-3 text-muted fw-normal">ทรัพย์สิน</dt><dd class="col-sm-9">{{ implode(', ', $claim->lossLabels()) ?: '-' }}{{ $claim->crop_rai ? ' · พืชผล '.$claim->crop_rai.' ไร่' : '' }}{{ $claim->livestock ? ' · สัตว์ '.$claim->livestock.' ตัว' : '' }}</dd>
                    @if($claim->note)<dt class="col-sm-3 text-muted fw-normal">รายละเอียด</dt><dd class="col-sm-9">{{ $claim->note }}</dd>@endif
                </dl>
                @if($claim->photos)
                    <div class="d-flex flex-wrap gap-1 mt-2">@foreach($claim->photoUrls() as $u)<a href="{{ $u }}" target="_blank" rel="noopener"><img src="{{ $u }}" class="wr-thumb" alt="" loading="lazy"></a>@endforeach</div>
                @endif
            </div></div>

            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-shield-check text-success"></i> หลักฐานจากช่วงน้ำท่วม <span class="ch-actions"><span class="chip {{ $claim->evidence_score >= 40 ? 'chip-success' : ($claim->evidence_score > 0 ? 'chip-warning' : 'chip-danger') }}">คะแนน {{ $claim->evidence_score }}</span></span></div>
                <div class="card-body small">
                    @forelse(RecoveryOptions::EVIDENCE as $k => [$l, $pts])
                        @if(isset($claim->evidence[$k]))
                            <div><i class="bi bi-check-circle-fill text-success"></i> {{ $l }}
                                @if($k === 'help_request' && $claim->helpRequest) · <a href="{{ route('cases.show', $claim->helpRequest) }}">{{ $claim->helpRequest->code }}</a>@endif
                                @if($k === 'water_report') ({{ $claim->evidence[$k] }} รายการ)@endif
                            </div>
                        @endif
                    @empty
                    @endforelse
                    @unless($claim->evidence)<div class="text-muted">ไม่พบข้อมูลที่เชื่อมโยงได้ ควรสำรวจหน้างานละเอียดเป็นพิเศษ</div>@endunless
                    @if($nearby->isNotEmpty())
                        <div class="mt-2 text-warning"><i class="bi bi-exclamation-triangle"></i> มีคำร้องอื่นในรัศมีประมาณ 50 ม.:
                            @foreach($nearby as $n)<a href="{{ route('recovery.show', $n->id) }}">{{ $n->code }} {{ $n->head_name }}</a>@if(! $loop->last), @endif @endforeach
                        </div>
                    @endif
                    @can('recovery.manage')
                        <form method="POST" action="{{ route('recovery.action', $claim) }}" class="mt-2">@csrf<input type="hidden" name="action" value="evidence"><button class="btn btn-sm btn-light" type="submit"><i class="bi bi-arrow-repeat"></i> ตรวจหลักฐานใหม่</button></form>
                    @endcan
                </div>
            </div>

            @if($claim->surveyed_at)
                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-clipboard-check text-primary"></i> ผลสำรวจ</div>
                    <div class="card-body small">
                        <div><b>{{ $claim->houseLabel($claim->verified_damage) }}</b> · {{ $claim->surveyor?->name }} · {{ thai_date($claim->surveyed_at, 'compact') }}</div>
                        @if($claim->survey_note)<div class="mt-1">{{ $claim->survey_note }}</div>@endif
                        @if($claim->suggested_amount)<div class="mt-1">ยอดแนะนำตามอัตราที่ตั้งไว้ <b class="mono">{{ number_format($claim->suggested_amount, 2) }}</b> บาท</div>@endif
                        @if($claim->survey_photos)<div class="d-flex flex-wrap gap-1 mt-2">@foreach($claim->photoUrls('survey_photos') as $u)<a href="{{ $u }}" target="_blank" rel="noopener"><img src="{{ $u }}" class="wr-thumb" alt="" loading="lazy"></a>@endforeach</div>@endif
                    </div>
                </div>
            @endif

            <div class="card">
                <div class="card-header"><i class="bi bi-clock-history text-primary"></i> ประวัติ</div>
                @foreach($claim->events as $e)
                    <div class="list-row small">
                        <span class="text-muted text-nowrap">{{ thai_date($e->created_at, 'compact') }}</span>
                        <span class="flex-grow-1">{{ $e->note }}</span>
                        <span class="text-muted">{{ $e->user?->name }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="col-lg-5">
            @if($claim->lat !== null)
                <div class="card mb-3"><div class="card-body p-2">
                    <div id="rcMini" class="map-box" style="height:200px"></div>
                    <a class="btn btn-sm btn-light mt-2" target="_blank" rel="noopener" href="https://www.google.com/maps/dir/?api=1&destination={{ $claim->lat }},{{ $claim->lng }}"><i class="bi bi-sign-turn-right me-1"></i>นำทางไปสำรวจ</a>
                </div></div>
            @endif

            @can('recovery.manage')
                @if(in_array($claim->status, ['submitted', 'surveying'], true))
                    <form method="POST" action="{{ route('recovery.action', $claim) }}" class="card mb-3">
                        @csrf <input type="hidden" name="action" value="schedule">
                        <div class="card-header"><i class="bi bi-calendar-check text-primary"></i> มอบหมายผู้สำรวจ</div>
                        <div class="card-body">
                            <select name="surveyor_id" class="form-select mb-2" data-search data-placeholder="เลือกผู้สำรวจ">
                                <option value="">ไม่ระบุ</option>
                                @foreach($surveyors as $u)<option value="{{ $u->id }}" @selected($claim->surveyor_id === $u->id)>{{ $u->name }}</option>@endforeach
                            </select>
                            <input name="note" class="form-control mb-2" maxlength="255" placeholder="นัดวันเวลา เช่น พรุ่งนี้ช่วงเช้า">
                            <button class="btn btn-primary w-100" type="submit">บันทึกนัดสำรวจ</button>
                        </div>
                    </form>
                @endif
            @endcan

            @if(in_array($claim->status, ['submitted', 'surveying', 'surveyed'], true))
                @canany(['recovery.survey', 'recovery.manage'])
                    <form method="POST" action="{{ route('recovery.action', $claim) }}" enctype="multipart/form-data" class="card mb-3">
                        @csrf <input type="hidden" name="action" value="survey">
                        <div class="card-header"><i class="bi bi-clipboard-check text-primary"></i> บันทึกผลสำรวจหน้างาน</div>
                        <div class="card-body">
                            <label class="form-label">ความเสียหายของบ้านที่พบจริง</label>
                            <div class="d-grid gap-1 mb-3" style="grid-template-columns:1fr 1fr">
                                @foreach(RecoveryOptions::HOUSE as $k => [$l])
                                    <input type="radio" class="btn-check" name="verified_damage" value="{{ $k }}" id="vd{{ $k }}" @checked(($claim->verified_damage ?? $claim->house_damage) === $k)>
                                    <label class="btn btn-outline-secondary btn-sm" for="vd{{ $k }}">{{ $l }}</label>
                                @endforeach
                            </div>
                            <select name="water_level" class="form-select mb-2"><option value="">ระดับน้ำที่ท่วม (ตามคราบน้ำ)</option>@foreach(config('floodthai.water_levels') as $k => $lv)<option value="{{ $k }}" @selected($claim->water_level == $k)>{{ $lv['label'] }}</option>@endforeach</select>
                            <div class="d-flex flex-wrap gap-1 mb-2">
                                @foreach(RecoveryOptions::LOSSES as $k => [$l])
                                    <input type="checkbox" class="btn-check" name="losses[]" value="{{ $k }}" id="sl{{ $k }}" @checked(in_array($k, $claim->losses ?? []))>
                                    <label class="btn btn-outline-secondary btn-sm" for="sl{{ $k }}">{{ $l }}</label>
                                @endforeach
                            </div>
                            <input name="crop_rai" type="number" step="0.25" min="0" class="form-control mb-2" placeholder="พื้นที่เกษตรเสียหายจริง (ไร่)" value="{{ $claim->crop_rai }}">
                            <textarea name="survey_note" class="form-control mb-2" rows="2" maxlength="2000" placeholder="สิ่งที่พบ">{{ $claim->survey_note }}</textarea>
                            <label class="btn btn-soft w-100 mb-2"><i class="bi bi-camera me-1"></i>ถ่ายรูปหน้างาน<input type="file" name="survey_photos[]" accept="image/*" capture="environment" multiple class="d-none"></label>
                            <button class="btn btn-primary w-100" type="submit">บันทึกผลสำรวจ</button>
                        </div>
                    </form>
                @endcanany
            @endif

            @can('recovery.manage')
                @if(in_array($claim->status, ['surveyed', 'approved'], true))
                    <form method="POST" action="{{ route('recovery.action', $claim) }}" class="card mb-3">
                        @csrf <input type="hidden" name="action" value="approve">
                        <div class="card-header"><i class="bi bi-check2-square text-success"></i> พิจารณาอนุมัติ</div>
                        <div class="card-body">
                            <div class="input-group mb-2"><input name="amount" type="number" step="0.01" min="0" class="form-control mono" value="{{ $claim->approved_amount ?? $claim->suggested_amount }}" required><span class="input-group-text">บาท</span></div>
                            <input name="note" class="form-control mb-2" maxlength="255" placeholder="หมายเหตุ / เลขที่หนังสือ">
                            <button class="btn btn-success w-100" type="submit">อนุมัติ</button>
                        </div>
                    </form>
                @endif
                @if($claim->status === 'approved')
                    <form method="POST" action="{{ route('recovery.action', $claim) }}" class="card mb-3">
                        @csrf <input type="hidden" name="action" value="pay">
                        <div class="card-header"><i class="bi bi-cash-coin text-teal"></i> บันทึกการจ่าย</div>
                        <div class="card-body">
                            <div class="input-group mb-2"><input name="amount" type="number" step="0.01" min="0.01" max="{{ $claim->approved_amount }}" class="form-control mono" value="{{ $claim->approved_amount }}" required><span class="input-group-text">บาท</span></div>
                            <input name="payment_ref" class="form-control mb-2" maxlength="60" placeholder="เลขอ้างอิงการโอน / ใบสำคัญ">
                            <button class="btn btn-primary w-100" type="submit">บันทึกว่าจ่ายแล้ว</button>
                        </div>
                    </form>
                @endif
                @if($claim->isOpen())
                    <form method="POST" action="{{ route('recovery.action', $claim) }}" class="card mb-3" data-confirm="ยืนยันว่าคำร้องนี้ไม่ผ่านเกณฑ์?">
                        @csrf <input type="hidden" name="action" value="reject">
                        <div class="card-body">
                            <div class="input-group input-group-sm"><input name="reason" class="form-control" maxlength="255" placeholder="เหตุผลที่ไม่ผ่านเกณฑ์" required><button class="btn btn-outline-danger" type="submit">ไม่ผ่าน</button></div>
                        </div>
                    </form>
                @endif
            @endcan
        </div>
    </div>
@endsection

@push('scripts')
    @if($claim->lat !== null)
        <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
        <script src="{{ asset('js/map.js') }}?v={{ filemtime(public_path('js/map.js')) }}"></script>
        <script>
            (function () { const m = FloodMap.create(document.getElementById('rcMini'), { center: [{{ $claim->lat }}, {{ $claim->lng }}], zoom: 16 }); L.marker([{{ $claim->lat }}, {{ $claim->lng }}]).addTo(m); })();
        </script>
    @endif
@endpush
