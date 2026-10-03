@props(['icon' => 'inbox', 'title' => 'ยังไม่มีข้อมูล', 'text' => null])
<div {{ $attributes->merge(['class' => 'empty']) }}>
    <div class="em-ico"><i class="bi bi-{{ $icon }}"></i></div>
    <div class="em-title">{{ $title }}</div>
    @if($text)<div class="small">{{ $text }}</div>@endif
    @if(trim($slot))<div class="mt-3">{{ $slot }}</div>@endif
</div>
