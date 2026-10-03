@extends('layouts.app')
@section('title', 'ตั้งค่า')

@push('head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
@endpush

@section('content')
    @php $me = auth()->user(); @endphp
    <x-page-head title="ตั้งค่า" sub="ค่าของ{{ $province->fullName() }} มีผลกับหน้าเว็บประชาชนและศูนย์สั่งการของจังหวัดนี้" />

    <ul class="nav nav-pills mb-3 flex-nowrap overflow-auto" style="scrollbar-width:none">
        @foreach($tabs as $key => [$label, $icon])
            <li class="nav-item"><a class="nav-link text-nowrap {{ $tab === $key ? 'active' : '' }}" href="{{ route('admin.settings.index', ['tab' => $key]) }}"><i class="bi bi-{{ $icon }} me-1"></i>{{ $label }}</a></li>
        @endforeach
    </ul>

    {{-- ทั่วไป --}}
    @if($tab === 'general')
        <form method="POST" action="{{ route('admin.settings.general') }}" class="row g-3">
            @csrf @method('PUT')
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><i class="bi bi-palette text-primary"></i> หน้าตาและการติดต่อ</div>
                    <div class="card-body">
                        <label class="form-label">สีหลักของระบบ</label>
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            @foreach($presets as $hex => $name)
                                <button type="button" class="color-swatch {{ strtoupper($s['theme_color']) === $hex ? 'active' : '' }}" style="background:{{ $hex }}" title="{{ $name }}" data-color="{{ $hex }}"></button>
                            @endforeach
                        </div>
                        <div class="input-group mb-3" style="max-width:220px">
                            <input type="color" class="form-control form-control-color" id="colorPicker" value="{{ $s['theme_color'] }}">
                            <input type="text" name="theme_color" id="colorText" class="form-control mono" value="{{ old('theme_color', $s['theme_color']) }}" maxlength="7">
                        </div>

                        <label class="form-label req">สายด่วนหลัก (ปุ่ม โทรกู้ภัย)</label>
                        <input name="hotline" class="form-control mono mb-1" value="{{ old('hotline', $s['hotline']) }}" style="max-width:220px">
                        <div class="form-text mb-3">แสดงเป็นปุ่มโทรด่วนบนทุกหน้าของเว็บประชาชน เช่น 1784 หรือเบอร์ศูนย์ของจังหวัด</div>

                        <label class="form-label">ข้อความประกาศสั้นหน้าแรก</label>
                        <textarea name="public_notice" class="form-control" rows="3" maxlength="300" placeholder="เช่น ศูนย์พักพิงเปิดเพิ่มที่โรงเรียนวัดโสธร">{{ old('public_notice', $s['public_notice']) }}</textarea>

                        <hr class="my-4">
                        <div class="fw-600 mb-2"><i class="bi bi-shield-lock text-primary"></i> ประกาศความเป็นส่วนตัว (PDPA)</div>
                        <label class="form-label">หน่วยงานผู้ควบคุมข้อมูลส่วนบุคคล</label>
                        <input name="privacy_controller" class="form-control mb-2" maxlength="200" value="{{ old('privacy_controller', $s['privacy_controller']) }}" placeholder="เช่น ศูนย์บัญชาการเหตุการณ์จังหวัด... / องค์การบริหารส่วนจังหวัด...">
                        <label class="form-label">ช่องทางติดต่อเจ้าหน้าที่คุ้มครองข้อมูล (DPO)</label>
                        <input name="privacy_contact" class="form-control mb-2" maxlength="300" value="{{ old('privacy_contact', $s['privacy_contact']) }}" placeholder="อีเมล เบอร์โทร ที่อยู่">
                        <label class="form-label">ข้อความเพิ่มเติม</label>
                        <textarea name="privacy_extra" class="form-control" rows="3" maxlength="3000">{{ old('privacy_extra', $s['privacy_extra']) }}</textarea>
                        <div class="form-text">แสดงที่หน้า <a href="{{ route('public.privacy', $province) }}" target="_blank">ประกาศความเป็นส่วนตัว</a> ควรให้ฝ่ายกฎหมายหรือ DPO ของหน่วยงานตรวจทานก่อนเปิดใช้จริง</div>

                        @if($me->isSuperAdmin())
                            <hr class="my-4">
                            <label class="form-label">ผู้ติดต่อขอเปิดศูนย์ (ทุกจังหวัด)</label>
                            <input name="center_contact" class="form-control" value="{{ old('center_contact', $s['center_contact']) }}" placeholder="เช่น คุณท็อป 083-426-5959">
                            <div class="form-text">แสดงในหน้าเว็บของจังหวัดที่ยังไม่เปิดศูนย์สั่งการ</div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><i class="bi bi-crosshair text-primary"></i> จุดกลางแผนที่</div>
                    <div class="card-body">
                        <div id="centerMap" class="map-box sm mb-3"></div>
                        <div class="row g-2">
                            <div class="col-5"><label class="form-label">ละติจูด</label><input name="center_lat" id="cLat" class="form-control mono" value="{{ old('center_lat', $province->center_lat) }}"></div>
                            <div class="col-5"><label class="form-label">ลองจิจูด</label><input name="center_lng" id="cLng" class="form-control mono" value="{{ old('center_lng', $province->center_lng) }}"></div>
                            <div class="col-2"><label class="form-label">ซูม</label><input name="default_zoom" type="number" min="7" max="15" class="form-control" value="{{ old('default_zoom', $province->default_zoom) }}"></div>
                        </div>
                        <div class="form-text">คลิกบนแผนที่หรือลากหมุดเพื่อตั้งจุดกลาง</div>
                    </div>
                </div>
            </div>
            <div class="col-12 text-end"><button class="btn btn-primary" type="submit"><i class="bi bi-check2 me-1"></i>บันทึก</button></div>
        </form>
    @endif

    {{-- สวิตช์ --}}
    @if($tab === 'switches')
        <form method="POST" action="{{ route('admin.settings.switches') }}">
            @csrf @method('PUT')
            <div class="card">
                <div class="card-body d-grid gap-2">
                    <div class="switch-card">
                        <span class="sc-ico tint-success"><i class="bi bi-broadcast-pin"></i></span>
                        <div class="sc-text">
                            <div class="sc-title">เปิดศูนย์สั่งการ</div>
                            <div class="sc-sub">
                                @if($me->isSuperAdmin())
                                    เปิดเมื่อมีหน่วยงานรับเป็นศูนย์อำนวยการของจังหวัด
                                @else
                                    เปิด/ปิดโดยผู้ดูแลระบบสูงสุด {{ $s['center_contact'] ? '('.$s['center_contact'].')' : '' }}
                                @endif
                            </div>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" type="checkbox" name="command_open" value="1" id="swCommand" @checked($province->command_open) @disabled(! $me->isSuperAdmin())>
                        </div>
                    </div>
                    <div class="switch-card">
                        <span class="sc-ico tint-danger"><i class="bi bi-life-preserver"></i></span>
                        <div class="sc-text">
                            <div class="sc-title">รับแจ้งขอความช่วยเหลือทางเว็บ</div>
                            <div class="sc-sub">ปิดไว้ หน้าเว็บจะแสดงเบอร์ฉุกเฉินให้โทรแทน ต้องเปิดศูนย์สั่งการก่อน</div>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" type="checkbox" name="web_help_open" value="1" id="swHelp" @checked($province->web_help_open)>
                        </div>
                    </div>
                    <div class="switch-card">
                        <span class="sc-ico tint-teal"><i class="bi bi-hand-index-thumb"></i></span>
                        <div class="sc-text">
                            <div class="sc-title">ให้ทีมกู้ภัยหยิบเคสเองได้</div>
                            <div class="sc-sub">ปิดไว้ ทีมรับงานได้เฉพาะที่ศูนย์มอบหมายให้</div>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" type="checkbox" name="team_self_assign" value="1" @checked($s['team_self_assign'])>
                        </div>
                    </div>
                    <div class="switch-card">
                        <span class="sc-ico tint-blue"><i class="bi bi-droplet-half"></i></span>
                        <div class="sc-text">
                            <div class="sc-title">เปิดรับรายงานระดับน้ำจากประชาชน</div>
                            <div class="sc-sub">ประชาชนแจ้งระดับน้ำพร้อมรูปได้ แสดงบนแผนที่สาธารณะ</div>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" type="checkbox" name="reports_open" value="1" @checked($s['reports_open'])>
                        </div>
                    </div>
                    <div class="switch-card">
                        <span class="sc-ico tint-warning"><i class="bi bi-eye"></i></span>
                        <div class="sc-text">
                            <div class="sc-title">ตรวจรายงานก่อนแสดง</div>
                            <div class="sc-sub">เปิดเมื่อพบรายงานปลอมมาก รายงานใหม่จะรอผู้ตรวจอนุมัติก่อนขึ้นแผนที่</div>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" type="checkbox" name="report_premoderate" value="1" @checked($s['report_premoderate'])>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0 text-end pb-3 pe-3">
                    @if(! $me->isSuperAdmin() && $province->command_open)<input type="hidden" name="command_open" value="1">@endif
                    <button class="btn btn-primary" type="submit"><i class="bi bi-check2 me-1"></i>บันทึก</button>
                </div>
            </div>
        </form>
    @endif

    {{-- การทำงาน --}}
    @if($tab === 'operation')
        <form method="POST" action="{{ route('admin.settings.operation') }}" class="row g-3">
            @csrf @method('PUT')
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><i class="bi bi-sliders text-primary"></i> เกณฑ์ของระบบ</div>
                    <div class="card-body">
                        @foreach([
                            'duplicate_radius_m' => ['รัศมีตรวจเคสซ้ำ', 'เมตร', 'เคสใหม่ที่อยู่ใกล้เคสเดิมในรัศมีนี้ จะถูกแนบเป็นข้อมูลเพิ่ม'],
                            'duplicate_window_hours' => ['ช่วงเวลาตรวจเคสซ้ำ', 'ชั่วโมง', 'นับย้อนหลังจากเวลาที่แจ้ง'],
                            'report_expire_hours' => ['อายุรายงานระดับน้ำ', 'ชั่วโมง', 'เกินนี้รายงานจะหายจากแผนที่'],
                            'risk_alert_radius_m' => ['รัศมีเตือนจุดเสี่ยง', 'เมตร', 'รายงานน้ำในรัศมีนี้จะทำให้จุดเสี่ยงขึ้นเตือน'],
                            'offer_timeout_min' => ['เวลารอทีมตอบรับงาน', 'นาที', 'เสนองานแล้วทีมไม่ตอบในเวลานี้ เคสจะกลับเข้าคิวรอทีม'],
                            'rain_warning_mm' => ['ฝนพยากรณ์ที่ประกาศเตือนภัย', 'มม./วัน', 'กรมอุตุฯ ถือว่าฝนหนักตั้งแต่ 35.1 มม.'],
                            'rain_critical_mm' => ['ฝนพยากรณ์ที่ประกาศวิกฤต', 'มม./วัน', 'ฝนหนักมากตั้งแต่ 90.1 มม.'],
                            'proactive_cooldown_hours' => ['เว้นช่วงเปิดเคสตรวจเยี่ยมซ้ำ', 'ชั่วโมง', 'ครัวเรือนเปราะบางที่เพิ่งเยี่ยมว่าปลอดภัย จะไม่ถูกเปิดเคสใหม่ในช่วงนี้'],
                        ] as $key => [$label, $unit, $help])
                            <div class="mb-3">
                                <label class="form-label">{{ $label }}</label>
                                <div class="input-group" style="max-width:240px">
                                    <input type="number" name="{{ $key }}" class="form-control mono" value="{{ old($key, $s[$key]) }}">
                                    <span class="input-group-text">{{ $unit }}</span>
                                </div>
                                <div class="form-text">{{ $help }}</div>
                            </div>
                        @endforeach
                        <div class="mb-3">
                            <label class="form-label">เปิดเคสตรวจเยี่ยมครัวเรือนเปราะบางเมื่อน้ำถึง</label>
                            <select name="household_trigger_level" class="form-select" style="max-width:240px">
                                @foreach(config('floodthai.water_levels') as $k => $lv)<option value="{{ $k }}" @selected(old('household_trigger_level', $s['household_trigger_level']) == $k)>{{ $lv['label'] }}</option>@endforeach
                            </select>
                            <div class="form-text">ระบบเปิดเคสให้ทีมไปถึงก่อนน้ำสูงจนออกจากบ้านไม่ได้</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><i class="bi bi-sort-numeric-down-alt text-primary"></i> น้ำหนักคะแนนความเร่งด่วน</div>
                    <div class="card-body">
                        @foreach([
                            'water_level' => ['ระดับน้ำ (คูณขั้น 1-6)', 'ต่อขั้น'],
                            'bedridden' => ['มีผู้ติดเตียง / ผู้ป่วย', 'คะแนน'],
                            'child_elderly' => ['มีเด็กเล็ก / ผู้สูงอายุ', 'คะแนน'],
                            'no_food' => ['ไม่มีอาหาร / น้ำดื่ม', 'คะแนน'],
                            'per_hour_waiting' => ['รอเกิน 1 ชม.', 'ต่อชั่วโมง'],
                        ] as $key => [$label, $unit])
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <label class="flex-grow-1 small fw-600" for="w_{{ $key }}">{{ $label }}</label>
                                <div class="input-group input-group-sm" style="width:150px">
                                    <input type="number" id="w_{{ $key }}" name="priority_weights[{{ $key }}]" class="form-control mono" value="{{ old('priority_weights.'.$key, $s['priority_weights'][$key]) }}">
                                    <span class="input-group-text">{{ $unit }}</span>
                                </div>
                            </div>
                        @endforeach
                        <div class="alert alert-primary small mb-0"><i class="bi bi-info-circle"></i>
                            <div>ตัวอย่าง: น้ำระดับอก (ขั้น 4) มีผู้ติดเตียง รอมา 2 ชม. = 4×{{ $s['priority_weights']['water_level'] }} + {{ $s['priority_weights']['bedridden'] }} + 2×{{ $s['priority_weights']['per_hour_waiting'] }}
                                = <b>{{ 4 * $s['priority_weights']['water_level'] + $s['priority_weights']['bedridden'] + 2 * $s['priority_weights']['per_hour_waiting'] }}</b> คะแนน</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 text-end"><button class="btn btn-primary" type="submit"><i class="bi bi-check2 me-1"></i>บันทึก</button></div>
        </form>
    @endif

    {{-- เบอร์ฉุกเฉิน --}}
    @if($tab === 'contacts')
        <div class="card table-card">
            <div class="card-header">
                <i class="bi bi-telephone text-primary"></i> เบอร์ฉุกเฉินบนหน้าเว็บประชาชน
                <div class="ch-actions">
                    <button class="btn btn-sm btn-primary" data-form-modal="#contactModal" data-action="{{ route('admin.contacts.store') }}" data-method="POST" data-title="เพิ่มเบอร์ฉุกเฉิน"><i class="bi bi-plus-lg"></i> เพิ่ม</button>
                </div>
            </div>
            @if($contacts->isEmpty())
                <x-empty icon="telephone" title="ยังไม่มีเบอร์ของจังหวัด" text="ระหว่างนี้หน้าเว็บจะแสดงเบอร์ระดับประเทศ (1784, 1669, 191)" />
            @else
                <div class="list-group list-group-flush" id="contactList">
                    @foreach($contacts as $c)
                        <div class="list-group-item d-flex align-items-center gap-3 py-2" data-id="{{ $c->id }}">
                            <i class="bi bi-grip-vertical text-muted" style="cursor:grab" title="ลากเพื่อเรียงลำดับ"></i>
                            <span class="st-icon tint-danger d-grid" style="width:38px;height:38px;border-radius:12px;place-items:center"><i class="bi bi-telephone-fill"></i></span>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-600">{{ $c->label }} @unless($c->is_active)<span class="chip ms-1">ซ่อน</span>@endunless</div>
                                <div class="small text-muted"><span class="mono">{{ $c->phone }}</span>@if($c->note) · {{ $c->note }}@endif</div>
                            </div>
                            <button class="btn btn-sm btn-light btn-icon" data-form-modal="#contactModal" data-action="{{ route('admin.contacts.update', $c) }}" data-method="PUT" data-title="แก้ไขเบอร์ฉุกเฉิน"
                                    data-fill="{{ json_encode($c->only(['label', 'phone', 'note', 'is_active'])) }}"><i class="bi bi-pencil"></i></button>
                            <form method="POST" action="{{ route('admin.contacts.destroy', $c) }}" data-confirm="ลบ {{ $c->label }}?">@csrf @method('DELETE')
                                <button class="btn btn-sm btn-light btn-icon text-danger" type="submit"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <x-form-modal id="contactModal" title="เพิ่มเบอร์ฉุกเฉิน" :action="route('admin.contacts.store')">
            <label class="form-label req">ชื่อหน่วยงาน</label>
            <input name="label" class="form-control mb-3" placeholder="เช่น หน่วยกู้ภัยฉะเชิงเทรา" value="{{ old('label') }}">
            <label class="form-label req">เบอร์โทร</label>
            <input name="phone" class="form-control mono mb-3" inputmode="tel" value="{{ old('phone') }}">
            <label class="form-label">หมายเหตุ</label>
            <input name="note" class="form-control mb-3" placeholder="เช่น ตลอด 24 ชม." value="{{ old('note') }}">
            <div class="form-check form-switch">
                <input type="hidden" name="is_active" value="0">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="cActive" checked data-default="1">
                <label class="form-check-label" for="cActive">แสดงบนหน้าเว็บ</label>
            </div>
        </x-form-modal>
    @endif

    {{-- ลิงก์ --}}
    @if($tab === 'links')
        <div class="card table-card">
            <div class="card-header">
                <i class="bi bi-link-45deg text-primary"></i> ลิงก์ข้อมูลน้ำภายนอก
                <div class="ch-actions">
                    <button class="btn btn-sm btn-primary" data-form-modal="#linkModal" data-action="{{ route('admin.links.store') }}" data-method="POST" data-title="เพิ่มลิงก์"><i class="bi bi-plus-lg"></i> เพิ่ม</button>
                </div>
            </div>
            @if($links->isEmpty())
                <x-empty icon="link-45deg" title="ยังไม่มีลิงก์" />
            @else
                <div class="list-group list-group-flush">
                    @foreach($links as $l)
                        @php $canEdit = $l->province_id !== null || $me->isSuperAdmin(); @endphp
                        <div class="list-group-item d-flex align-items-center gap-3 py-2">
                            <span class="st-icon tint-blue d-grid" style="width:38px;height:38px;border-radius:12px;place-items:center"><i class="bi bi-box-arrow-up-right"></i></span>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-600">{{ $l->title }}
                                    @if($l->province_id === null)<span class="chip ms-1">ทุกจังหวัด</span>@endif
                                    @unless($l->is_active)<span class="chip ms-1">ซ่อน</span>@endunless
                                </div>
                                <div class="small text-muted text-truncate">{{ $l->description }} @if($l->source_name)· {{ $l->source_name }}@endif</div>
                                <a href="{{ $l->url }}" target="_blank" rel="noopener" class="small text-truncate d-block">{{ $l->url }}</a>
                            </div>
                            @if($canEdit)
                                <button class="btn btn-sm btn-light btn-icon" data-form-modal="#linkModal" data-action="{{ route('admin.links.update', $l) }}" data-method="PUT" data-title="แก้ไขลิงก์"
                                        data-fill="{{ json_encode($l->only(['title', 'description', 'url', 'source_name', 'is_active'])) }}"><i class="bi bi-pencil"></i></button>
                                <form method="POST" action="{{ route('admin.links.destroy', $l) }}" data-confirm="ลบลิงก์ {{ $l->title }}?">@csrf @method('DELETE')
                                    <button class="btn btn-sm btn-light btn-icon text-danger" type="submit"><i class="bi bi-trash"></i></button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <x-form-modal id="linkModal" title="เพิ่มลิงก์" :action="route('admin.links.store')">
            <label class="form-label req">ชื่อลิงก์</label>
            <input name="title" class="form-control mb-3" value="{{ old('title') }}">
            <label class="form-label">คำอธิบาย</label>
            <input name="description" class="form-control mb-3" value="{{ old('description') }}">
            <label class="form-label req">URL</label>
            <input name="url" type="url" class="form-control mb-3" placeholder="https://" value="{{ old('url') }}">
            <label class="form-label">แหล่งข้อมูล</label>
            <input name="source_name" class="form-control mb-3" placeholder="เช่น กรมชลประทาน" value="{{ old('source_name') }}">
            <div class="form-check form-switch">
                <input type="hidden" name="is_active" value="0">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="lActive" checked data-default="1">
                <label class="form-check-label" for="lActive">แสดงบนหน้าเว็บ</label>
            </div>
            @if($me->isSuperAdmin())
                <div class="form-check mt-2" data-only="create">
                    <input class="form-check-input" type="checkbox" name="global" value="1" id="lGlobal">
                    <label class="form-check-label" for="lGlobal">แสดงทุกจังหวัด</label>
                </div>
            @endif
        </x-form-modal>
    @endif
    {{-- LINE OA --}}
    @if($tab === 'line')
        <form method="POST" action="{{ route('admin.settings.line') }}" class="row g-3">
            @csrf @method('PUT')
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header"><i class="bi bi-chat-dots text-success"></i> LINE Official Account ของศูนย์
                        <span class="ch-actions">{!! $s['line_ready'] ? '<span class="chip chip-success">เชื่อมต่อแล้ว</span>' : '<span class="chip">ยังไม่ตั้งค่า</span>' !!}</span>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">LINE ID ของ OA</label>
                            <input name="line_oa_id" class="form-control" maxlength="40" value="{{ old('line_oa_id', $s['line_oa_id']) }}" placeholder="@floodcenter">
                            <div class="form-text">แสดงปุ่มเพิ่มเพื่อนบนเว็บประชาชน</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Channel access token (long-lived)</label>
                            <input name="line_oa_token" type="password" class="form-control mono" autocomplete="off" placeholder="{{ $s['line_ready'] ? 'ตั้งค่าไว้แล้ว เว้นว่างเพื่อใช้ค่าเดิม' : '' }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Channel secret</label>
                            <input name="line_oa_secret" type="password" class="form-control mono" autocomplete="off" placeholder="{{ $s['line_secret_set'] ? 'ตั้งค่าไว้แล้ว เว้นว่างเพื่อใช้ค่าเดิม' : '' }}">
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="clear" value="1" id="lineClear">
                            <label class="form-check-label text-danger" for="lineClear">ลบ token และ secret ที่บันทึกไว้</label>
                        </div>
                        <button class="btn btn-primary" type="submit"><i class="bi bi-check2 me-1"></i>บันทึก</button>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header"><i class="bi bi-info-circle text-primary"></i> วิธีตั้งค่า</div>
                    <div class="card-body small">
                        <ol class="ps-3 mb-3">
                            <li>สร้าง Messaging API channel ใน LINE Developers Console</li>
                            <li>คัดลอก Channel access token และ Channel secret มาใส่</li>
                            <li>ตั้ง Webhook URL เป็น<div class="input-group input-group-sm mt-1"><input class="form-control mono" readonly value="{{ route('line.webhook', $province) }}"><button class="btn btn-light" type="button" data-copy="{{ route('line.webhook', $province) }}"><i class="bi bi-clipboard"></i></button></div></li>
                            <li>เปิด Use webhook และปิดข้อความตอบกลับอัตโนมัติของ LINE</li>
                        </ol>
                        <div class="text-muted">ประชาชนพิมพ์ "ช่วย" "น้ำ" "ศูนย์" หรือส่งตำแหน่ง ระบบตอบลิงก์ขอความช่วยเหลือ แผนที่ และศูนย์พักพิงใกล้สุดให้เอง ประกาศที่ติ๊ก "ส่ง LINE" จะส่งถึงผู้ติดตามทุกคน</div>
                    </div>
                </div>
            </div>
        </form>
    @endif

@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <script src="{{ asset('js/map.js') }}?v={{ filemtime(public_path('js/map.js')) }}"></script>
    <script>
        (function () {
            // สีหลัก
            const text = document.getElementById('colorText'), picker = document.getElementById('colorPicker');
            if (text && picker) {
                const apply = hex => {
                    text.value = hex.toUpperCase(); picker.value = hex;
                    document.querySelectorAll('.color-swatch').forEach(s => s.classList.toggle('active', s.dataset.color.toUpperCase() === hex.toUpperCase()));
                    document.documentElement.style.setProperty('--sb-primary', hex);
                };
                document.querySelectorAll('.color-swatch').forEach(s => s.addEventListener('click', () => apply(s.dataset.color)));
                picker.addEventListener('input', () => apply(picker.value));
                text.addEventListener('change', () => /^#[0-9a-f]{6}$/i.test(text.value) && apply(text.value));
            }

            // จุดกลางแผนที่
            const el = document.getElementById('centerMap');
            if (el) {
                FloodMap.picker(el, document.getElementById('cLat'), document.getElementById('cLng'), {
                    center: [13.7563, 100.5018], zoom: 6, areasUrl: @json(route('dashboard.areas')) + '?level=district',
                });
            }

            // ลากเรียงเบอร์ฉุกเฉิน
            const list = document.getElementById('contactList');
            if (list && window.Sortable) {
                Sortable.create(list, {
                    handle: '.bi-grip-vertical', animation: 150,
                    onEnd: () => fetch(@json(route('admin.contacts.sort')), {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, Accept: 'application/json' },
                        body: JSON.stringify({ ids: [...list.children].map(r => r.dataset.id) }),
                    }),
                });
            }

            // ปิดรับแจ้งทางเว็บอัตโนมัติเมื่อศูนย์ปิด
            const cmd = document.getElementById('swCommand'), help = document.getElementById('swHelp');
            if (cmd && help) {
                const sync = () => { if (!cmd.checked) help.checked = false; help.disabled = !cmd.checked; };
                cmd.addEventListener('change', sync); sync();
            }
        })();
    </script>
@endpush
