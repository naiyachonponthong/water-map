@php
    $user = auth()->user();
    $bottom = collect(\App\Support\Menu::rail())->take(4)->values();
    // 5 ช่อง: 2 ซ้าย + ปุ่มกลาง เมนู + 2 ขวา
    $left = $bottom->slice(0, 2);
    $right = $bottom->slice(2, 2);
@endphp
<nav class="sb-bottom-nav" aria-label="เมนูล่าง">
    @foreach($left as $item)
        @php $badge = \App\Support\Menu::badge($item); @endphp
        <a href="{{ route($item['route']) }}" class="bn-item {{ \App\Support\Menu::isActive($item) ? 'active' : '' }}">
            <i class="bi bi-{{ $item['icon'] }}"></i><span>{{ $item['short'] }}</span>
            @if($badge)<span class="bn-badge">{{ $badge }}</span>@endif
        </a>
    @endforeach
    @for($i = $left->count(); $i < 2; $i++)<span></span>@endfor

    <a href="{{ route('menu.index') }}" class="bn-item center {{ request()->routeIs('menu.index') ? 'active' : '' }}">
        <span class="bn-fab"><i class="bi bi-grid-3x3-gap-fill"></i></span><span>เมนู</span>
    </a>

    @foreach($right as $item)
        @php $badge = \App\Support\Menu::badge($item); @endphp
        <a href="{{ route($item['route']) }}" class="bn-item {{ \App\Support\Menu::isActive($item) ? 'active' : '' }}">
            <i class="bi bi-{{ $item['icon'] }}"></i><span>{{ $item['short'] }}</span>
            @if($badge)<span class="bn-badge">{{ $badge }}</span>@endif
        </a>
    @endforeach
    @if($right->count() < 2)
        <a href="{{ route('profile.edit') }}" class="bn-item {{ request()->routeIs('profile.*') ? 'active' : '' }}">
            <i class="bi bi-person-circle"></i><span>ฉัน</span>
        </a>
    @endif
    @for($i = $right->count() + 1; $i < 2; $i++)<span></span>@endfor
</nav>
