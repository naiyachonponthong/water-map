@extends('layouts.app')
@section('title', $case->code)

@push('head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
@endpush

@section('content')
    @use('App\Support\CaseOptions')
    @php
        $tel = fn ($p) => preg_replace('/\D+/', '', (string) $p);
        $lv = config('floodthai.water_levels.'.$case->water_level);
    @endphp

    <div class="page-head">
        <div>
            <div class="small text-muted"><a href="{{ route('cases.index') }}"><i class="bi bi-arrow-left"></i> เคสทั้งหมด</a></div>
            <h1 class="d-flex flex-wrap align-items-center gap-2">
                <span class="mono">{{ $case->code }}</span>
                <span class="chip {{ $case->statusChip() }}" style="font-size:.85rem">{{ $case->statusLabel() }}</span>
                <span class="score-pill" style="background:{{ $case->priorityColor() }};font-size:.85rem">{{ $case->priorityLabel() }} {{ $case->priority_score }}{{ $case->priority_locked ? ' (กำหนดเอง)' : '' }}</span>
            </h1>
            <div class="ph-sub">
                <i class="bi bi-{{ CaseOptions::SOURCES[$case->source][1] }}"></i> แจ้งทาง{{ CaseOptions::SOURCES[$case->source][0] }}
                · {{ thai_date($case->created_at, 'compact') }}
                @if($case->isOpen()) · <span class="text-danger fw-600">รอมาแล้ว {{ $case->waitingLabel() }}</span>@endif
                @if($case->creator) · คีย์โดย {{ $case->creator->name }}@endif
            </div>
        </div>
        @if($canManage)
            <div class="ph-actions">
                @if($case->status === 'new')
                    <form method="POST" action="{{ route('cases.screen', $case) }}">@csrf<input type="hidden" name="action" value="start">
                        <button class="btn btn-soft" type="submit"><i class="bi bi-search me-1"></i>รับคัดกรอง</button>
                    </form>
                @endif
                @if(in_array($case->status, ['new', 'screening'], true))
                    <form method="POST" action="{{ route('cases.screen', $case) }}">@csrf<input type="hidden" name="action" value="pass">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle me-1"></i>ผ่านคัดกรอง เข้าคิวรอทีม</button>
                    </form>
                @endif
                <div class="dropdown">
                    <button class="btn btn-light" data-bs-toggle="dropdown" type="button"><i class="bi bi-three-dots"></i> จัดการ</button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#statusModal"><i class="bi bi-arrow-right-circle me-2"></i>เปลี่ยนสถานะ / ปิดเคส</button></li>
                        <li><button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#priorityModal"><i class="bi bi-sort-down me-2"></i>ปรับความเร่งด่วน</button></li>
                        @if($case->isOpen())<li><button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#mergeModal"><i class="bi bi-intersect me-2"></i>รวมเข้ากับเคสอื่น</button></li>@endif
                        <li><a class="dropdown-item" href="{{ route('cases.edit', $case) }}"><i class="bi bi-pencil me-2"></i>แก้ไขข้อมูล</a></li>
                    </ul>
                </div>
            </div>
        @endif
    </div>

    @if($case->possibleDuplicateOf)
        <div class="alert alert-warning">
            <i class="bi bi-intersect"></i>
            <div class="flex-grow-1">
                <b>อาจซ้ำกับ <a href="{{ route('cases.show', $case->possibleDuplicateOf) }}" class="mono">{{ $case->possibleDuplicateOf->code }}</a></b>
                ({{ $case->possibleDuplicateOf->requester_name }}, {{ $case->possibleDuplicateOf->statusLabel() }},
                ห่าง {{ number_format(\App\Support\Geo::distance($case->lat, $case->lng, $case->possibleDuplicateOf->lat, $case->possibleDuplicateOf->lng)) }} ม.)
                <div class="small">โทรถามผู้แจ้งก่อน ถ้าเป็นบ้านเดียวกันให้รวมเคส ถ้าคนละหลังกด ไม่ใช่เคสซ้ำ</div>
            </div>
            @if($canManage && $case->isOpen())
                <div class="d-flex gap-2 flex-shrink-0">
                    <form method="POST" action="{{ route('cases.merge', $case) }}" data-confirm="รวม {{ $case->code }} เข้ากับ {{ $case->possibleDuplicateOf->code }}?">@csrf
                        <input type="hidden" name="into" value="{{ $case->possibleDuplicateOf->code }}">
                        <button class="btn btn-sm btn-warning" type="submit">รวมเคส</button>
                    </form>
                    <form method="POST" action="{{ route('cases.not-duplicate', $case) }}">@csrf
                        <button class="btn btn-sm btn-light" type="submit">ไม่ใช่เคสซ้ำ</button>
                    </form>
                </div>
            @endif
        </div>
    @elseif($nearby)
        <div class="alert alert-primary small"><i class="bi bi-geo"></i>
            <div>มีเคสเปิดอยู่ใกล้กัน <a href="{{ route('cases.show', $nearby) }}" class="mono">{{ $nearby->code }}</a> ({{ $nearby->requester_name }}) ห่าง {{ number_format(\App\Support\Geo::distance($case->lat, $case->lng, $nearby->lat, $nearby->lng)) }} ม. ทีมเดียวช่วยได้ทั้งสองจุด</div>
        </div>
    @endif
    @if($case->outside_province)
        <div class="alert alert-warning small"><i class="bi bi-geo"></i><div>พิกัดอยู่นอกขอบเขตจังหวัด ตรวจตำแหน่งกับผู้แจ้งอีกครั้ง หรือส่งต่อศูนย์ของจังหวัดข้างเคียง</div></div>
    @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-people text-primary"></i> ทีมที่รับงาน
                    @can('dispatch.manage')
                        @if($case->isOpen() && ! ($case->assignment && $case->assignment->isActive()))
                            <div class="ch-actions"><button class="btn btn-sm btn-primary" data-assign="{{ $case->id }}"><i class="bi bi-send"></i> มอบหมายทีม</button></div>
                        @endif
                    @endcan
                </div>
                <div class="card-body">
                    @php $a = $case->assignment; @endphp
                    @if($a && $a->isActive())
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="app-ico teal" style="width:40px;height:40px;border-radius:13px;font-size:1.1rem"><i class="bi bi-people-fill"></i></span>
                            <div class="flex-grow-1 min-w-0">
                                <a href="{{ auth()->user()->can('teams.manage') ? route('teams.show', $a->team) : '#' }}" class="fw-600 text-reset">{{ $a->team->name }}</a>
                                <div class="small"><span class="chip {{ $a->statusChip() }}">{{ $a->statusLabel() }}</span>
                                    @if($a->eta_minutes)<span class="text-muted ms-1">{{ number_format(($a->distance_m ?? 0) / 1000, 1) }} กม. · ราว {{ $a->eta_minutes }} นาที</span>@endif
                                </div>
                            </div>
                            @php $teamPhone = $a->team->phone ?: $a->team->leader?->phone; @endphp
                            @if($teamPhone)<a href="tel:{{ $teamPhone }}" class="btn btn-sm btn-soft"><i class="bi bi-telephone"></i></a>@endif
                        </div>
                        @can('dispatch.manage')
                            <div class="d-flex flex-wrap gap-2">
                                @if($a->status === 'offered')
                                    <button class="btn btn-sm btn-primary" data-act="respond" data-a="{{ $a->id }}" data-accept="1">ทีมรับแล้ว</button>
                                    <button class="btn btn-sm btn-light" data-act="respond" data-a="{{ $a->id }}" data-accept="0">ทีมปฏิเสธ</button>
                                @endif
                                @if($a->status === 'accepted')<button class="btn btn-sm btn-soft" data-act="progress" data-a="{{ $a->id }}" data-step="en_route">เริ่มเดินทาง</button>@endif
                                @if(in_array($a->status, ['accepted', 'en_route'], true))<button class="btn btn-sm btn-soft" data-act="progress" data-a="{{ $a->id }}" data-step="on_site">ถึงที่เกิดเหตุ</button>@endif
                                @if($a->status !== 'offered')<button class="btn btn-sm btn-success" data-complete="{{ $a->id }}" data-people="{{ $case->people_count }}" data-code="{{ $case->code }}">ปิดงาน</button>@endif
                                <button class="btn btn-sm btn-light text-danger" data-act="cancel" data-a="{{ $a->id }}">ยกเลิกงาน</button>
                            </div>
                        @endcan
                    @else
                        <div class="text-muted small">{{ $case->isOpen() ? 'ยังไม่มีทีม' : 'ไม่มีงานค้าง' }}</div>
                    @endif

                    @php $history = $case->assignments->reject(fn ($x) => $x->id === $a?->id && $x->isActive()); @endphp
                    @if($history->isNotEmpty())
                        <hr>
                        <div class="small text-muted mb-1">ประวัติการมอบหมาย</div>
                        @foreach($history as $h)
                            <div class="d-flex justify-content-between small py-1">
                                <span>{{ $h->team?->name }} <span class="chip {{ $h->statusChip() }} ms-1">{{ $h->statusLabel() }}</span></span>
                                <span class="text-muted">{{ thai_date($h->offered_at, 'compact') }}</span>
                            </div>
                            @if($h->decline_reason)<div class="small text-muted ps-2">{{ $h->decline_reason }}</div>@endif
                        @endforeach
                    @endif
                </div>
            </div>
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-info-circle text-primary"></i> สถานการณ์</div>
                <div class="card-body">
                    <dl class="kv mb-0">
                        <dt>ระดับน้ำ</dt><dd><span class="lv-dot me-1" style="background:{{ $lv['color'] }}"></span><b>{{ $lv['label'] }}</b> <span class="text-muted">{{ $lv['range'] }}</span></dd>
                        <dt>จำนวนคน</dt><dd><b>{{ $case->people_count }}</b> คน{{ $case->floor_level ? ' · อยู่ชั้น '.$case->floor_level : '' }}</dd>
                        <dt>กลุ่มเปราะบาง</dt>
                        <dd>
                            @forelse($case->vulnerable ?? [] as $v)
                                <span class="chip {{ $v === 'pets' ? '' : 'chip-danger' }} me-1 mb-1"><i class="bi bi-{{ CaseOptions::VULNERABLE[$v][1] ?? 'dot' }}"></i> {{ CaseOptions::VULNERABLE[$v][0] ?? $v }}</span>
                            @empty<span class="text-muted">ไม่มี</span>@endforelse
                        </dd>
                        <dt>ต้องการ</dt>
                        <dd>@foreach($case->needs ?? [] as $n)<span class="chip chip-primary me-1 mb-1"><i class="bi bi-{{ CaseOptions::NEEDS[$n][1] ?? 'dot' }}"></i> {{ CaseOptions::NEEDS[$n][0] ?? $n }}</span>@endforeach</dd>
                        @if($case->needs_note)<dt>รายละเอียด</dt><dd style="white-space:pre-line">{{ $case->needs_note }}</dd>@endif
                        <dt>ที่อยู่</dt><dd>{{ $case->address_text ?: '-' }}@if($case->landmark)<div class="text-muted small">จุดสังเกต: {{ $case->landmark }}</div>@endif</dd>
                        <dt>พื้นที่</dt><dd>{{ $case->areaLabel() }}</dd>
                        <dt>พิกัด</dt>
                        <dd>
                            <span class="mono">{{ number_format($case->lat, 6) }}, {{ number_format($case->lng, 6) }}</span>
                            <span class="text-muted small">({{ ['gps' => 'GPS', 'map' => 'ปักหมุด', 'link' => 'จากลิงก์', 'staff' => 'เจ้าหน้าที่ระบุ'][$case->location_source] ?? $case->location_source }}{{ $case->accuracy_m ? ' ±'.$case->accuracy_m.' ม.' : '' }})</span>
                            <div class="mt-1 d-flex gap-2 flex-wrap">
                                <a href="{{ $case->mapsUrl() }}" target="_blank" rel="noopener" class="btn btn-sm btn-soft"><i class="bi bi-sign-turn-right"></i> นำทาง</a>
                                <button class="btn btn-sm btn-light" data-copy="{{ $case->lat }},{{ $case->lng }}"><i class="bi bi-clipboard"></i> คัดลอกพิกัด</button>
                            </div>
                        </dd>
                    </dl>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-person text-primary"></i> ผู้แจ้ง</div>
                <div class="card-body">
                    <dl class="kv mb-0">
                        <dt>ชื่อ</dt><dd>{{ $case->requester_name }}{{ $case->on_behalf ? ' (แจ้งแทน)' : '' }}</dd>
                        <dt>เบอร์</dt>
                        <dd>
                            @if($canManage)
                                <a href="tel:{{ $tel($case->requester_phone) }}" class="fw-600 mono">{{ phone_format($case->requester_phone) }}</a>
                            @else
                                <span class="mono">{{ $case->maskedPhone() }}</span>
                            @endif
                        </dd>
                        @if($case->contact_name || $case->contact_phone)
                            <dt>คนในพื้นที่</dt>
                            <dd>{{ $case->contact_name }}
                                @if($case->contact_phone)
                                    @if($canManage)<a href="tel:{{ $tel($case->contact_phone) }}" class="fw-600 mono ms-1">{{ phone_format($case->contact_phone) }}</a>@else<span class="mono ms-1">0xx-xxx-{{ substr($case->contact_phone, -4) }}</span>@endif
                                @endif
                            </dd>
                        @endif
                        @if($case->report_count > 1)<dt>แจ้งซ้ำ</dt><dd>{{ $case->report_count }} ครั้ง (ระบบรวมให้แล้ว)</dd>@endif
                        @if($case->merged->isNotEmpty())<dt>เคสที่รวมเข้ามา</dt><dd>@foreach($case->merged as $m)<a href="{{ route('cases.show', $m) }}" class="mono me-2">{{ $m->code }}</a>@endforeach</dd>@endif
                        @if($case->duplicateOf)<dt>รวมเข้ากับ</dt><dd><a href="{{ route('cases.show', $case->duplicateOf) }}" class="mono">{{ $case->duplicateOf->code }}</a></dd>@endif
                        @if($case->outcome)<dt>ผลการช่วยเหลือ</dt><dd>{{ CaseOptions::OUTCOMES[$case->outcome] ?? $case->outcome }}{{ $case->people_rescued !== null ? ' · ช่วยออกมา '.$case->people_rescued.' คน' : '' }}</dd>@endif
                    </dl>
                    @if($case->photos)
                        <div class="d-flex gap-2 flex-wrap mt-3">
                            @foreach($case->photoUrls() as $url)
                                <a href="{{ $url }}" target="_blank"><img src="{{ $url }}" class="photo-thumb" style="width:110px;height:110px" alt="รูปจากผู้แจ้ง" loading="lazy"></a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header"><i class="bi bi-clock-history text-primary"></i> บันทึกเหตุการณ์</div>
                <div class="card-body">
                    @if($canManage)
                        <form method="POST" action="{{ route('cases.note', $case) }}" class="mb-4">
                            @csrf
                            <textarea name="note" class="form-control mb-2" rows="2" maxlength="1000" placeholder="บันทึก เช่น โทรแล้ว ผู้แจ้งยืนยันว่ายังติดอยู่ชั้น 2" required></textarea>
                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                <div class="btn-group btn-group-sm" role="group">
                                    <input type="radio" class="btn-check" name="type" id="tNote" value="note" checked><label class="btn btn-outline-primary" for="tNote"><i class="bi bi-chat-left-text"></i> บันทึก</label>
                                    <input type="radio" class="btn-check" name="type" id="tCall" value="call"><label class="btn btn-outline-primary" for="tCall"><i class="bi bi-telephone"></i> โทรติดต่อ</label>
                                </div>
                                <div class="form-check small mb-0">
                                    <input class="form-check-input" type="checkbox" name="public" value="1" id="nPublic">
                                    <label class="form-check-label" for="nPublic">ให้ผู้แจ้งเห็นในหน้าติดตาม</label>
                                </div>
                                <button class="btn btn-sm btn-primary ms-auto" type="submit">บันทึก</button>
                            </div>
                        </form>
                    @endif
                    <div class="timeline">
                        @foreach($events as $e)
                            <div class="tl-item {{ $loop->first ? 'first' : '' }}">
                                <span class="tl-dot"><i class="bi bi-{{ $e->icon() }}"></i></span>
                                <div class="small"><b>{{ $e->summary() }}</b>
                                    <span class="text-muted">· {{ $e->user?->name ?? ($e->actor === 'requester' ? 'ผู้แจ้ง' : 'ระบบ') }}</span>
                                    @if($e->public && $e->actor === 'staff')<span class="chip ms-1" title="ผู้แจ้งเห็น"><i class="bi bi-eye"></i></span>@endif
                                </div>
                                <div class="tl-time">{{ thai_date($e->created_at, 'compact') }}</div>
                                @if($e->note)<div class="tl-note">{{ $e->note }}</div>@endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="dash-side">
                @can('evacuees.manage')
                    @php
                        $evacCount = \App\Models\Evacuee::where('help_request_id', $case->id)->count();
                        $openShelters = in_array($case->outcome, ['shelter', null], true) || $evacCount ? \App\Models\Shelter::inProvince($case->province_id)->whereIn('status', ['open', 'preparing'])->orderBy('name')->get(['id', 'name', 'capacity', 'occupancy']) : collect();
                    @endphp
                    @if($evacCount || ($case->outcome === 'shelter' && $openShelters->isNotEmpty()))
                        <div class="card">
                            <div class="card-header"><i class="bi bi-house-heart text-teal"></i> ศูนย์พักพิง</div>
                            <div class="card-body">
                                @if($evacCount)<div class="small mb-2"><i class="bi bi-check-circle text-success"></i> ลงทะเบียนเข้าศูนย์แล้ว {{ $evacCount }} คน</div>@endif
                                @if($openShelters->isNotEmpty())
                                    <form method="GET" class="d-flex gap-2" onsubmit="this.action=this.querySelector('select').value; return true">
                                        <input type="hidden" name="case" value="{{ $case->id }}">
                                        <select class="form-select form-select-sm">
                                            @foreach($openShelters as $sh)<option value="{{ route('shelters.register', $sh) }}">{{ $sh->name }}{{ $sh->capacity ? ' (ว่าง '.max(0, $sh->capacity - $sh->occupancy).')' : '' }}</option>@endforeach
                                        </select>
                                        <button class="btn btn-sm btn-primary text-nowrap" type="submit">ลงทะเบียน</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endif
                @endcan
                <div class="card">
                    <div class="card-body p-2"><div id="caseMiniMap" class="map-box" style="height:280px"></div></div>
                </div>
                <div class="card">
                    <div class="card-header"><i class="bi bi-sort-numeric-down-alt text-primary"></i> ที่มาของคะแนน</div>
                    <div class="card-body small">
                        @php $w = array_replace(config('floodthai.defaults.priority_weights'), (array) setting('priority_weights')); @endphp
                        <div class="d-flex justify-content-between"><span>ระดับน้ำ ขั้น {{ $case->water_level }} × {{ $w['water_level'] }}</span><b>{{ $case->water_level * $w['water_level'] }}</b></div>
                        @if($case->hasVulnerable('bedridden', 'sick', 'disabled'))<div class="d-flex justify-content-between"><span>ผู้ป่วย / ผู้พิการ</span><b>{{ $w['bedridden'] }}</b></div>@endif
                        @if($case->hasVulnerable('infant', 'elderly', 'pregnant'))<div class="d-flex justify-content-between"><span>เด็กเล็ก / ผู้สูงอายุ / ตั้งครรภ์</span><b>{{ $w['child_elderly'] }}</b></div>@endif
                        @if(in_array('food', $case->needs ?? [], true))<div class="d-flex justify-content-between"><span>ขาดอาหาร / น้ำดื่ม</span><b>{{ $w['no_food'] }}</b></div>@endif
                        @if($case->people_count > 3)<div class="d-flex justify-content-between"><span>คนจำนวนมาก</span><b>{{ min(10, $case->people_count - 3) }}</b></div>@endif
                        @if(in_array($case->status, CaseOptions::WAITING, true) && $case->waitingMinutes() >= 60)<div class="d-flex justify-content-between"><span>รอ {{ intdiv($case->waitingMinutes(), 60) }} ชม.</span><b>{{ intdiv($case->waitingMinutes(), 60) * $w['per_hour_waiting'] }}</b></div>@endif
                        <hr class="my-2"><div class="d-flex justify-content-between"><span>รวม</span><b>{{ $case->priority_score }}</b></div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header"><i class="bi bi-link-45deg text-primary"></i> ลิงก์ติดตามของผู้แจ้ง</div>
                    <div class="card-body small">
                        <p class="text-muted mb-2">ส่งให้ผู้แจ้งทาง SMS/LINE เพื่อดูสถานะและกด ปลอดภัยแล้ว เองได้</p>
                        <button class="btn btn-sm btn-soft w-100" data-copy="{{ 'ติดตามคำขอ '.$case->code.' '.$case->trackUrl() }}"><i class="bi bi-clipboard"></i> คัดลอกข้อความ + ลิงก์</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($canManage)
        {{-- เปลี่ยนสถานะ --}}
        <div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" method="POST" action="{{ route('cases.status', $case) }}">
                    @csrf
                    <div class="modal-header"><h5 class="modal-title">เปลี่ยนสถานะ {{ $case->code }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <label class="form-label req">สถานะ</label>
                        <select name="status" class="form-select mb-3" id="stSelect">
                            @foreach(CaseOptions::STATUSES as $k => [$label])
                                @continue($k === 'merged')
                                <option value="{{ $k }}" @selected($k === $case->status)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <div id="stFinal" class="d-none">
                            <label class="form-label req">ผลการช่วยเหลือ</label>
                            <select name="outcome" class="form-select mb-3">
                                <option value="">เลือก</option>
                                @foreach(CaseOptions::OUTCOMES as $k => $label)
                                    @continue($k === 'duplicate')
                                    <option value="{{ $k }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <label class="form-label">ช่วยออกมาได้กี่คน</label>
                            <input type="number" name="people_rescued" class="form-control mb-3" min="0" max="500" value="{{ $case->people_count }}">
                        </div>
                        <label class="form-label">หมายเหตุ</label>
                        <textarea name="note" class="form-control" rows="2" maxlength="500"></textarea>
                        <div class="form-text">การเปลี่ยนสถานะจะแสดงในหน้าติดตามของผู้แจ้ง</div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button><button class="btn btn-primary" type="submit">บันทึก</button></div>
                </form>
            </div>
        </div>

        {{-- ความเร่งด่วน --}}
        <div class="modal fade" id="priorityModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" method="POST" action="{{ route('cases.priority', $case) }}">
                    @csrf
                    <div class="modal-header"><h5 class="modal-title">ปรับความเร่งด่วน</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <div class="d-grid gap-2 mb-3">
                            <input type="radio" class="btn-check" name="priority" id="pAuto" value="auto" @checked(! $case->priority_locked)>
                            <label class="btn btn-outline-primary text-start" for="pAuto"><i class="bi bi-magic me-1"></i> คำนวณอัตโนมัติ</label>
                            @foreach(CaseOptions::PRIORITIES as $k => [$label, , $color])
                                <input type="radio" class="btn-check" name="priority" id="p{{ $k }}" value="{{ $k }}" @checked($case->priority_locked && $case->priority === $k)>
                                <label class="btn btn-outline-secondary text-start" for="p{{ $k }}"><span class="lv-dot me-2" style="background:{{ $color }}"></span>{{ $label }} (กำหนดเอง)</label>
                            @endforeach
                        </div>
                        <input name="note" class="form-control" maxlength="300" placeholder="เหตุผล เช่น ผู้ป่วยต้องใช้ออกซิเจน">
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button><button class="btn btn-primary" type="submit">บันทึก</button></div>
                </form>
            </div>
        </div>

        {{-- รวมเคส --}}
        <div class="modal fade" id="mergeModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" method="POST" action="{{ route('cases.merge', $case) }}">
                    @csrf
                    <div class="modal-header"><h5 class="modal-title">รวม {{ $case->code }} เข้ากับเคสอื่น</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <label class="form-label req">เลขเคสหลัก</label>
                        <input name="into" class="form-control mono text-uppercase @error('into') is-invalid @enderror" value="{{ old('into', $nearby?->code) }}" placeholder="FL24-6910-0001">
                        @error('into')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">ข้อมูลคน กลุ่มเปราะบาง และรูป จะรวมเข้าเคสหลัก เคสนี้จะปิดเป็น รวมกับเคสอื่น</div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button><button class="btn btn-warning" type="submit">รวมเคส</button></div>
                </form>
            </div>
        </div>
    @endif

    @can('dispatch.manage')
        @include('admin.dispatch._modals')
    @endcan
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="{{ asset('js/map.js') }}?v={{ filemtime(public_path('js/map.js')) }}"></script>
    <script src="{{ asset('js/cases.js') }}?v={{ filemtime(public_path('js/cases.js')) }}"></script>
    @can('dispatch.manage')
        @include('admin.dispatch._scripts')
    @endcan
    <script>
        (function () {
            const ll = [{{ $case->lat }}, {{ $case->lng }}];
            const map = FloodMap.create(document.getElementById('caseMiniMap'), { center: ll, zoom: 15 });
            L.marker(ll, { icon: FloodCases.caseIcon(@json($case->priorityColor()), true) }).addTo(map);
            @if($case->accuracy_m)
                L.circle(ll, { radius: {{ $case->accuracy_m }}, color: @json($case->priorityColor()), weight: 1, fillOpacity: .08 }).addTo(map);
            @endif
            @foreach(array_filter([$nearby, $case->possibleDuplicateOf]) as $o)
                L.marker([{{ $o->lat }}, {{ $o->lng }}], { icon: FloodCases.caseIcon(@json($o->priorityColor()), false) }).bindTooltip(@json($o->code)).addTo(map);
            @endforeach

            const st = document.getElementById('stSelect'), fin = document.getElementById('stFinal');
            if (st) { const sync = () => fin.classList.toggle('d-none', !['rescued', 'closed', 'cancelled'].includes(st.value)); st.addEventListener('change', sync); sync(); }
            @if($errors->has('into'))bootstrap.Modal.getOrCreateInstance(document.getElementById('mergeModal')).show();@endif
        })();
    </script>
@endpush
