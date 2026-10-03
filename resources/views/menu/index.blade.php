@extends('layouts.app')
@section('title', 'เมนูทั้งหมด')

@section('content')
    <x-page-head title="เมนูทั้งหมด" sub="ทุกเครื่องมือที่บัญชีของคุณใช้ได้" />

    @forelse($groups as $group)
        <div class="card mb-3">
            <div class="card-header">{{ $group['label'] }}</div>
            <div class="card-body">
                <div class="app-grid">
                    @foreach($group['items'] as $item)
                        @php $badge = \App\Support\Menu::badge($item); @endphp
                        <a href="{{ route($item['route']) }}" class="app-tile">
                            <span class="app-ico {{ $item['tone'] }}"><i class="bi bi-{{ $item['icon'] }}"></i></span>
                            <span class="at-label">{{ $item['label'] }}</span>
                            @if($badge)<span class="at-badge">{{ $badge }}</span>@endif
                        </a>
                    @endforeach
                    @if($loop->last)
                        @can('settings.manage')
                            <a href="{{ route('admin.settings.index') }}" class="app-tile">
                                <span class="app-ico slate"><i class="bi bi-gear"></i></span>
                                <span class="at-label">ตั้งค่า</span>
                            </a>
                        @endcan
                        <a href="{{ route('profile.edit') }}" class="app-tile">
                            <span class="app-ico slate"><i class="bi bi-person-circle"></i></span>
                            <span class="at-label">ข้อมูลส่วนตัว</span>
                        </a>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="card">
            <x-empty icon="grid" title="ยังไม่มีเมนูสำหรับบทบาทของคุณ" text="เครื่องมือภาคสนามจะเปิดให้ใช้งานเร็วๆ นี้">
                <a href="{{ route('profile.edit') }}" class="btn btn-soft">ข้อมูลส่วนตัว</a>
            </x-empty>
        </div>
    @endforelse
@endsection
