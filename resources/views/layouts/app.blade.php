<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0e2233">
    <meta name="robots" content="noindex">
    <title>{{ trim($__env->yieldContent('title')) ? trim($__env->yieldContent('title')).' | ' : '' }}{{ $appTitle }}</title>
    @include('partials.head')
    @stack('head')
</head>
<body @if($errors->any() && old('_modal')) data-reopen-modal="{{ old('_modal') }}" @endif>
@php
    $me = auth()->user();
    $rail = \App\Support\Menu::rail();
@endphp

{{-- Navigation rail (เดสก์ท็อป) --}}
<aside class="sb-rail" aria-label="เมนูหลัก">
    <a href="{{ route('dashboard') }}" class="rail-logo" title="{{ $appTitle }}"><i class="bi bi-droplet-fill"></i></a>
    <nav class="rail-items">
        @foreach($rail as $item)
            @php $badge = \App\Support\Menu::badge($item); @endphp
            <a href="{{ route($item['route']) }}" class="rail-item {{ \App\Support\Menu::isActive($item) ? 'active' : '' }}" title="{{ $item['label'] }}">
                <span class="ri-icon"><i class="bi bi-{{ $item['icon'] }}"></i></span>
                <span class="ri-label">{{ $item['short'] }}</span>
                @if($badge)<span class="ri-badge">{{ $badge > 99 ? '99+' : $badge }}</span>@endif
            </a>
        @endforeach
        <a href="{{ route('menu.index') }}" class="rail-item {{ request()->routeIs('menu.index') ? 'active' : '' }}" title="เมนูทั้งหมด">
            <span class="ri-icon"><i class="bi bi-grid-3x3-gap"></i></span>
            <span class="ri-label">ทั้งหมด</span>
        </a>
    </nav>
    <div class="rail-bottom">
        @can('settings.manage')
            <a href="{{ route('admin.settings.index') }}" class="rail-item {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}" title="ตั้งค่า">
                <span class="ri-icon"><i class="bi bi-gear"></i></span>
                <span class="ri-label">ตั้งค่า</span>
            </a>
        @endcan
        <a href="{{ route('profile.edit') }}" class="rail-avatar" title="{{ $me->name }}">
            <x-avatar :user="$me" size="sm" />
        </a>
    </div>
</aside>

<div class="sb-main">
    {{-- Topbar --}}
    <header class="sb-topbar">
        <div class="tb-org">
            <div class="tb-name">{{ $currentProvince ? 'ศูนย์ช่วยเหลือน้ำท่วม'.$currentProvince->fullName() : $appTitle }}</div>
            <div class="tb-sub">
                @if($currentProvince?->command_open)
                    <span class="text-success"><i class="bi bi-circle-fill" style="font-size:.5rem;vertical-align:middle"></i> ศูนย์สั่งการเปิดอยู่</span>
                @else
                    ศูนย์สั่งการยังไม่เปิด
                @endif
                · {{ $me->roleLabel() }}
            </div>
        </div>

        @if($me->isSuperAdmin())
            <div class="dropdown d-desktop">
                <button class="tb-province" data-bs-toggle="dropdown" type="button">
                    <i class="bi bi-geo-alt"></i> {{ $currentProvince?->shortName() ?? 'เลือกจังหวัด' }} <i class="bi bi-chevron-down small"></i>
                </button>
                <div class="dropdown-menu p-2" style="width:280px">
                    <form method="POST" action="{{ route('province.switch') }}">
                        @csrf
                        <select name="province_id" class="form-select form-select-sm" data-search onchange="this.form.submit()">
                            @foreach(\App\Models\Province::orderByDesc('command_open')->orderBy('name_th')->get(['id','name_th','command_open']) as $p)
                                <option value="{{ $p->id }}" @selected($currentProvince?->id === $p->id)>{{ $p->name_th }}{{ $p->command_open ? ' (เปิดศูนย์)' : '' }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>
            </div>
        @endif

        <button class="tb-search" type="button" data-open-search>
            <i class="bi bi-search"></i><span>ค้นหาเมนู</span><kbd>Ctrl K</kbd>
        </button>
        @if($currentProvince)
            <a class="tb-btn d-desktop" href="{{ route('public.province', $currentProvince) }}" target="_blank" title="ดูหน้าเว็บประชาชน"><i class="bi bi-box-arrow-up-right"></i></a>
        @endif
        <button class="tb-btn" type="button" title="แจ้งเตือน"><i class="bi bi-bell"></i></button>
        <div class="dropdown">
            <button class="tb-profile" data-bs-toggle="dropdown" type="button">
                <x-avatar :user="$me" size="sm" />
                <span class="d-desktop small fw-600 text-truncate" style="max-width:140px">{{ $me->name }}</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li class="px-3 py-2">
                    <div class="fw-600">{{ $me->name }}</div>
                    <div class="small text-muted">{{ phone_format($me->phone) }} · {{ $me->roleLabel() }}</div>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person me-2"></i>ข้อมูลส่วนตัว</a></li>
                <li><button class="dropdown-item d-none" type="button" data-install-app><i class="bi bi-phone me-2"></i>ติดตั้งแอปลงเครื่อง</button></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">@csrf
                        <button class="dropdown-item text-danger" type="submit"><i class="bi bi-box-arrow-right me-2"></i>ออกจากระบบ</button>
                    </form>
                </li>
            </ul>
        </div>
    </header>

    <main class="sb-content">
        @yield('content')
    </main>
    @include('partials.creator-credit')
</div>

@include('partials.bottom-nav')
@include('partials.flash')

{{-- ค้นหาเมนู Ctrl K --}}
<div class="modal fade search-modal" id="searchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <input type="text" class="form-control sm-input" placeholder="พิมพ์ชื่อเมนู เช่น ผู้ใช้ ตั้งค่า">
            <div class="sm-list"></div>
        </div>
    </div>
</div>

<script>window.__MENU = @json(\App\Support\Menu::flat());</script>
@include('partials.scripts')
@stack('scripts')
</body>
</html>
