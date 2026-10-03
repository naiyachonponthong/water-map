@extends('layouts.app')
@section('title', 'ลงทะเบียนเข้าศูนย์')

@section('content')
    @use('App\Support\ReliefOptions')

    <x-page-head title="ลงทะเบียนเข้าศูนย์" :sub="$shelter->name.($shelter->capacity ? ' · ว่าง '.$shelter->available().' ที่' : '')">
        <a href="{{ route('shelters.show', $shelter) }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>กลับ</a>
    </x-page-head>

    @if($errors->any())
        <div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i><div>{{ $errors->first() }}</div></div>
    @endif
    @if($prefill)
        <div class="alert alert-info small"><i class="bi bi-life-preserver"></i>
            <div>มาจากเคส <a href="{{ route('cases.show', $prefill['case']) }}" class="fw-600">{{ $prefill['case']->code }}</a> ทีมช่วยออกมา {{ $prefill['people_count'] }} คน ใส่ชื่อให้ครบทุกคน</div>
        </div>
    @endif

    <form method="POST" action="{{ route('shelters.register.store', $shelter) }}" id="regForm">
        @csrf
        <input type="hidden" name="help_request_id" value="{{ old('help_request_id', $prefill['help_request_id'] ?? '') }}">
        <input type="hidden" name="vulnerable_household_id" value="{{ old('vulnerable_household_id', $prefill['vulnerable_household_id'] ?? '') }}">

        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-people text-primary"></i> สมาชิกที่มาด้วยกัน <span class="ch-actions small text-muted">ลงทะเบียนพร้อมกัน = ครอบครัวเดียวกัน</span></div>
            <div class="card-body" id="people">
                @php
                    $rows = old('people', $prefill['people'] ?? [[]]);
                    $count = max(count($rows), min(12, (int) ($prefill['people_count'] ?? 1)));
                @endphp
                @for($i = 0; $i < $count; $i++)
                    @php $p = $rows[$i] ?? []; @endphp
                    <div class="person-row border rounded-3 p-2 mb-2">
                        <div class="row g-2">
                            <div class="col-md-4"><input name="people[{{ $i }}][name]" class="form-control" maxlength="120" placeholder="ชื่อ-สกุล" value="{{ $p['name'] ?? '' }}"></div>
                            <div class="col-md-3"><input name="people[{{ $i }}][phone]" class="form-control mono" inputmode="tel" maxlength="15" placeholder="เบอร์โทร (ถ้ามี)" value="{{ $p['phone'] ?? '' }}"></div>
                            <div class="col-6 col-md-3">
                                <select name="people[{{ $i }}][age_group]" class="form-select">
                                    <option value="">ช่วงอายุ</option>
                                    @foreach(ReliefOptions::AGE_GROUPS as $k => $l)<option value="{{ $k }}" @selected(($p['age_group'] ?? '') === $k)>{{ $l }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-6 col-md-2">
                                <select name="people[{{ $i }}][gender]" class="form-select">
                                    <option value="">เพศ</option>
                                    @foreach(ReliefOptions::GENDERS as $k => $l)<option value="{{ $k }}" @selected(($p['gender'] ?? '') === $k)>{{ $l }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-12 d-flex flex-wrap gap-1">
                                @foreach(ReliefOptions::EVACUEE_NEEDS as $k => [$l, $ic])
                                    <input type="checkbox" class="btn-check" name="people[{{ $i }}][needs][]" value="{{ $k }}" id="n{{ $i }}{{ $k }}" @checked(in_array($k, $p['needs'] ?? []))>
                                    <label class="btn btn-outline-danger btn-sm" for="n{{ $i }}{{ $k }}"><i class="bi bi-{{ $ic }} me-1"></i>{{ $l }}</label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endfor
            </div>
            <div class="card-footer bg-transparent">
                <button type="button" class="btn btn-soft btn-sm" id="addPerson"><i class="bi bi-plus-lg me-1"></i>เพิ่มคน</button>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <label class="form-label">ที่อยู่เดิม</label>
                <input name="address" class="form-control mb-3" maxlength="255" value="{{ old('address', $prefill['address'] ?? '') }}">
                <div class="form-check">
                    <input type="hidden" name="allow_lookup" value="0">
                    <input class="form-check-input" type="checkbox" name="allow_lookup" value="1" id="allowLookup" @checked(old('allow_lookup', '1'))>
                    <label class="form-check-label" for="allowLookup">ยินยอมให้ญาติค้นหาว่าอยู่ศูนย์นี้ ด้วยเบอร์โทร (ไม่แสดงเบอร์และชื่อเต็ม)</label>
                </div>
            </div>
        </div>
        <button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-check2 me-1"></i>ลงทะเบียน</button>
    </form>
@endsection

@push('scripts')
    <script>
        document.getElementById('addPerson').addEventListener('click', () => {
            const box = document.getElementById('people');
            const rows = box.querySelectorAll('.person-row');
            if (rows.length >= 30) return;
            const n = rows.length;
            const clone = rows[rows.length - 1].cloneNode(true);
            clone.querySelectorAll('[name]').forEach(el => {
                el.name = el.name.replace(/people\[\d+\]/, `people[${n}]`);
                if (el.type === 'checkbox') { el.checked = false; el.id = el.id.replace(/^n\d+/, 'n' + n); }
                else if (el.tagName === 'SELECT') el.selectedIndex = 0;
                else el.value = '';
            });
            clone.querySelectorAll('label[for]').forEach(l => (l.htmlFor = l.htmlFor.replace(/^n\d+/, 'n' + n)));
            box.appendChild(clone);
            clone.querySelector('input').focus();
        });
    </script>
@endpush
