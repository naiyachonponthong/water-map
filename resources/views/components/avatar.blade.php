@props(['user', 'size' => null])
@php $cls = 'avatar'.($size ? ' avatar-'.$size : ''); @endphp
@if($user?->avatar_url)
    <img src="{{ $user->avatar_url }}" alt="" class="{{ $cls }}" {{ $attributes }} referrerpolicy="no-referrer">
@else
    <span class="{{ $cls }}" {{ $attributes }}>{{ $user ? $user->initials() : '?' }}</span>
@endif
