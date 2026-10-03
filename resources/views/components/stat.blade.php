@props(['label', 'value', 'icon' => 'bar-chart', 'tint' => 'primary', 'url' => null])
@php $tag = $url ? 'a' : 'div'; @endphp
<{{ $tag }} @if($url) href="{{ $url }}" @endif {{ $attributes->merge(['class' => 'card card-lift text-reset']) }}>
    <div class="card-body stat">
        <span class="st-icon tint-{{ $tint }}"><i class="bi bi-{{ $icon }}"></i></span>
        <div>
            <div class="st-num mono">{{ is_numeric($value) ? number_format($value) : $value }}</div>
            <div class="st-label">{{ $label }}</div>
        </div>
    </div>
</{{ $tag }}>
