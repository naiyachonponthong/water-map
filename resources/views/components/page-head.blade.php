@props(['title', 'sub' => null])
<div class="page-head">
    <div>
        <h1>{{ $title }}</h1>
        @if($sub)<div class="ph-sub">{{ $sub }}</div>@endif
    </div>
    @if(trim($slot))
        <div class="ph-actions">{{ $slot }}</div>
    @endif
</div>
