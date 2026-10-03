<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') | ศูนย์ช่วยเหลือน้ำท่วม</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f4f5f7; font-family: 'Sarabun', system-ui, sans-serif; color: #1f2937; padding: 1rem; }
        .box { text-align: center; max-width: 420px; }
        .code { font-size: 4rem; font-weight: 700; letter-spacing: -.04em; color: #1565C0; line-height: 1; }
        h1 { font-size: 1.3rem; margin: .6rem 0 .3rem; }
        p { color: #6b7280; margin: 0 0 1.4rem; }
        a { display: inline-block; padding: .6rem 1.2rem; border-radius: 12px; background: #1565C0; color: #fff; text-decoration: none; font-weight: 600; margin: 0 4px; }
        a.light { background: #e9eaee; color: #1f2937; }
    </style>
</head>
<body>
<div class="box">
    <div class="code">@yield('code')</div>
    <h1>@yield('title')</h1>
    <p>@yield('message')</p>
    <a href="{{ url()->previous() !== url()->current() ? url()->previous() : url('/') }}" class="light">ย้อนกลับ</a>
    <a href="tel:1784">โทร 1784</a>
</div>
</body>
</html>
