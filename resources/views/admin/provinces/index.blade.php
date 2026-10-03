@extends('layouts.app')
@section('title', 'จังหวัดทั้งหมด')

@section('content')
    <x-page-head title="จังหวัดทั้งหมด" sub="เปิดศูนย์สั่งการรายจังหวัด และตั้งพิกัดกลางของแผนที่" />

    <div class="row g-3 mb-3">
        <div class="col-6 col-md-4"><x-stat label="จังหวัดทั้งหมด" :value="$totals['all']" icon="globe-asia-australia" tint="blue" :url="route('admin.provinces.index')" /></div>
        <div class="col-6 col-md-4"><x-stat label="เปิดศูนย์สั่งการ" :value="$totals['open']" icon="broadcast-pin" tint="success" :url="route('admin.provinces.index', ['filter' => 'open'])" /></div>
        <div class="col-12 col-md-4"><x-stat label="รับแจ้งทางเว็บ" :value="$totals['help']" icon="life-preserver" tint="danger" :url="route('admin.provinces.index', ['filter' => 'help'])" /></div>
    </div>

    <div class="card table-card">
        <div class="card-header">
            <form method="GET" class="d-flex gap-2 w-100">
                <input type="hidden" name="filter" value="{{ $filter }}">
                <div class="input-group input-group-sm" style="max-width:280px">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="ค้นหาจังหวัด">
                </div>
                @if($filter !== 'all' || request('q'))
                    <a href="{{ route('admin.provinces.index') }}" class="btn btn-sm btn-light">ล้างตัวกรอง</a>
                @endif
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-stack">
                <thead><tr><th>จังหวัด</th><th>URL</th><th>อำเภอ</th><th>เจ้าหน้าที่</th><th>พิกัดกลาง</th><th>ศูนย์สั่งการ</th><th>รับแจ้งทางเว็บ</th><th></th></tr></thead>
                <tbody>
                @foreach($provinces as $p)
                    <tr class="{{ $p->is_active ? '' : 'opacity-50' }}">
                        <td class="td-main"><span class="mono text-muted small me-1">{{ $p->code }}</span><span class="fw-600">{{ $p->name_th }}</span></td>
                        <td data-label="URL"><a href="{{ route('public.province', $p) }}" target="_blank" class="small mono">/{{ $p->slug }}</a></td>
                        <td data-label="อำเภอ" class="mono">{{ $p->districts_count }}</td>
                        <td data-label="เจ้าหน้าที่" class="mono">{{ $p->active_users_count }}</td>
                        <td data-label="พิกัดกลาง" class="small">{!! $p->hasCenter() ? '<span class="chip chip-success">ตั้งแล้ว</span>' : '<span class="chip">ยังไม่ตั้ง</span>' !!}</td>
                        <td data-label="ศูนย์สั่งการ">@if($p->command_open)<span class="chip chip-dot chip-success">เปิด</span>@else<span class="chip">ปิด</span>@endif</td>
                        <td data-label="รับแจ้งทางเว็บ">@if($p->web_help_open)<span class="chip chip-dot chip-danger">เปิด</span>@else<span class="chip">ปิด</span>@endif</td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-light" data-form-modal="#provinceModal" data-action="{{ route('admin.provinces.update', $p) }}" data-method="PUT" data-title="{{ $p->fullName() }}"
                                    data-fill="{{ json_encode($p->only(['name_th', 'name_en', 'center_lat', 'center_lng', 'default_zoom', 'is_active', 'command_open', 'web_help_open'])) }}">
                                <i class="bi bi-pencil"></i> จัดการ
                            </button>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <x-form-modal id="provinceModal" title="จังหวัด" :action="route('admin.provinces.index')">
        <div class="row g-3">
            <div class="col-6">
                <label class="form-label req">ชื่อจังหวัด</label>
                <input name="name_th" class="form-control" required>
            </div>
            <div class="col-6">
                <label class="form-label">ชื่ออังกฤษ</label>
                <input name="name_en" class="form-control">
            </div>
            <div class="col-5">
                <label class="form-label">ละติจูดกลาง</label>
                <input name="center_lat" class="form-control mono" placeholder="13.6904">
            </div>
            <div class="col-5">
                <label class="form-label">ลองจิจูดกลาง</label>
                <input name="center_lng" class="form-control mono" placeholder="101.0780">
            </div>
            <div class="col-2">
                <label class="form-label">ซูม</label>
                <input name="default_zoom" type="number" min="7" max="15" class="form-control" data-default="10">
            </div>
            <div class="col-12">
                <div class="switch-card mb-2">
                    <span class="sc-ico tint-blue"><i class="bi bi-eye"></i></span>
                    <div class="sc-text"><div class="sc-title">แสดงในรายชื่อจังหวัด</div><div class="sc-sub">ปิดเพื่อซ่อนหน้าเว็บประชาชนของจังหวัดนี้</div></div>
                    <div class="form-check form-switch m-0"><input type="hidden" name="is_active" value="0"><input class="form-check-input" type="checkbox" name="is_active" value="1"></div>
                </div>
                <div class="switch-card mb-2">
                    <span class="sc-ico tint-success"><i class="bi bi-broadcast-pin"></i></span>
                    <div class="sc-text"><div class="sc-title">เปิดศูนย์สั่งการ</div><div class="sc-sub">เปิดเมื่อมีหน่วยงานรับเป็นศูนย์อำนวยการในจังหวัด</div></div>
                    <div class="form-check form-switch m-0"><input type="hidden" name="command_open" value="0"><input class="form-check-input" type="checkbox" name="command_open" value="1"></div>
                </div>
                <div class="switch-card">
                    <span class="sc-ico tint-danger"><i class="bi bi-life-preserver"></i></span>
                    <div class="sc-text"><div class="sc-title">รับแจ้งขอความช่วยเหลือทางเว็บ</div><div class="sc-sub">ต้องเปิดศูนย์สั่งการก่อน</div></div>
                    <div class="form-check form-switch m-0"><input type="hidden" name="web_help_open" value="0"><input class="form-check-input" type="checkbox" name="web_help_open" value="1"></div>
                </div>
            </div>
        </div>
    </x-form-modal>
@endsection
