<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0e2233">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') | ศูนย์ช่วยเหลือน้ำท่วม</title>
    <meta name="description" content="@yield('description', 'ศูนย์ช่วยเหลือน้ำท่วม ระดับน้ำ พยากรณ์ฝน ศูนย์พักพิง และเบอร์ฉุกเฉิน')">
    <meta property="og:title" content="@yield('title') | ศูนย์ช่วยเหลือน้ำท่วม">
    <meta property="og:description" content="@yield('description', 'ศูนย์ช่วยเหลือน้ำท่วม')">
    <meta property="og:locale" content="th_TH">
    @include('partials.head')
    @stack('head')
    <style>
        body { background: #f4f5f7; }
        .pub-wrap { max-width: 720px; margin: 0 auto; padding: 0 1rem 6rem; }
        .pub-head { background: linear-gradient(150deg, #0e2233, #173a5e); color: #fff; padding: 1.2rem 1rem 4.5rem; border-radius: 0 0 26px 26px; }
        .pub-head .inner { max-width: 720px; margin: 0 auto; }
        .pub-float { margin-top: -3.4rem; }
        .call-fab { position: fixed; right: 16px; bottom: calc(16px + env(safe-area-inset-bottom)); z-index: 1050; border-radius: 999px; padding: .75rem 1.2rem; font-weight: 700; box-shadow: 0 10px 24px rgba(220, 38, 38, .35); }
        .contact-row { display: flex; align-items: center; gap: 12px; padding: .8rem 1rem; border-bottom: 1px solid var(--sb-border-soft); color: var(--sb-text); }
        .contact-row:last-child { border-bottom: 0; }
        .contact-row .num { margin-left: auto; font-weight: 700; color: #dc2626; font-size: 1.1rem; }
    </style>
</head>
<body class="@yield('body-class')">
@yield('content')
@include('partials.creator-credit')
@include('partials.flash')
@include('partials.scripts')
@stack('scripts')
</body>
</html>
