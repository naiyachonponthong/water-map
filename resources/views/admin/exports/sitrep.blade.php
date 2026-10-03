<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>รายงานสถานการณ์อุทกภัย {{ $province->fullName() }} {{ thai_date($to) }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        @page { size: A4; margin: 14mm 14mm 16mm; }
        * { box-sizing: border-box; }
        body { font-family: Sarabun, sans-serif; font-size: 14px; color: #111; margin: 0; background: #e5e7eb; }
        .page { background: #fff; width: 210mm; min-height: 297mm; margin: 16px auto; padding: 14mm; }
        h1 { font-size: 20px; margin: 0; }
        h2 { font-size: 15px; margin: 18px 0 6px; padding-bottom: 3px; border-bottom: 2px solid #1e3a5f; color: #1e3a5f; }
        .sub { color: #555; }
        .kpi { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-top: 12px; }
        .kpi div { border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px; text-align: center; }
        .kpi b { display: block; font-size: 22px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { border: 1px solid #cbd5e1; padding: 4px 6px; text-align: left; vertical-align: top; }
        th { background: #f1f5f9; }
        td.n, th.n { text-align: right; font-variant-numeric: tabular-nums; }
        .two { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .lv-critical { color: #b91c1c; font-weight: 700; } .lv-warning { color: #c2410c; font-weight: 600; }
        .sign { margin-top: 28px; display: flex; justify-content: flex-end; text-align: center; }
        .toolbar { position: sticky; top: 0; background: #0e2233; color: #fff; padding: 8px 16px; display: flex; gap: 8px; align-items: center; }
        .toolbar button, .toolbar a { background: #fff; color: #0e2233; border: 0; border-radius: 6px; padding: 6px 12px; font: inherit; cursor: pointer; text-decoration: none; }
        @media print { body { background: #fff; } .page { margin: 0; width: auto; min-height: 0; padding: 0; } .toolbar { display: none; } h2 { break-after: avoid; } tr { break-inside: avoid; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <span>รายงานสถานการณ์ พร้อมพิมพ์ หรือบันทึกเป็น PDF จากหน้าต่างพิมพ์</span>
        <button onclick="window.print()" style="margin-left:auto">พิมพ์ / บันทึก PDF</button>
        <a href="{{ route('exports.index', ['from' => $from->toDateString(), 'to' => $to->copy()->timezone(config('app.timezone'))->toDateString()]) }}">กลับ</a>
    </div>

    @php $c = $r['cases']; @endphp
    <div class="page">
        <h1>รายงานสถานการณ์อุทกภัย {{ $province->fullName() }}</h1>
        <div class="sub">{{ $title }} · ข้อมูลช่วง {{ thai_date($from, 'datetime') }} ถึง {{ thai_date($to, 'datetime') }} · จัดทำ {{ thai_date(now(), 'datetime') }}</div>

        <div class="kpi">
            <div><b>{{ number_format($c['total']) }}</b>เคสขอความช่วยเหลือ</div>
            <div><b>{{ number_format($c['people_rescued']) }}</b>คนที่ช่วยออกมา</div>
            <div><b>{{ number_format($c['open']) }}</b>เคสที่ยังเปิด</div>
            <div><b>{{ number_format($r['shelters']['people_now']) }}</b>คนในศูนย์พักพิง</div>
        </div>

        <h2>1. ประกาศเตือนภัยในช่วงนี้</h2>
        @forelse($r['alerts'] as $a)
            <div class="lv-{{ $a->level }}">• [{{ $a->levelLabel() }}] {{ $a->title }}</div>
            @if($a->body)<div style="margin:0 0 4px 14px;color:#444">{{ $a->body }}</div>@endif
        @empty
            <div>ไม่มีประกาศเตือนภัย</div>
        @endforelse

        <h2>2. การรับแจ้งและช่วยเหลือ</h2>
        <div class="two">
            <table>
                <tr><th>รายการ</th><th class="n">จำนวน</th></tr>
                <tr><td>เคสทั้งหมด</td><td class="n">{{ number_format($c['total']) }}</td></tr>
                <tr><td>เคสวิกฤต</td><td class="n">{{ number_format($c['critical']) }}</td></tr>
                <tr><td>มีกลุ่มเปราะบาง</td><td class="n">{{ number_format($c['vulnerable']) }}</td></tr>
                <tr><td>ช่วยเหลือแล้ว (เคส)</td><td class="n">{{ number_format($c['rescued']) }}</td></tr>
                <tr><td>จำนวนคนที่แจ้งว่าติด</td><td class="n">{{ number_format($c['people_reported']) }}</td></tr>
                <tr><td>ข้อมูลไม่จริง</td><td class="n">{{ number_format($c['fake']) }}</td></tr>
            </table>
            <table>
                <tr><th>เวลานับจากแจ้ง</th><th class="n">ค่ากลาง</th><th class="n">90%</th></tr>
                @foreach(['accept' => 'ทีมตอบรับ', 'arrive' => 'ถึงที่เกิดเหตุ', 'finish' => 'ช่วยเสร็จ'] as $k => $l)
                    <tr><td>{{ $l }}</td><td class="n">{{ $r['response'][$k]['median'] !== null ? $r['response'][$k]['median'].' นาที' : '-' }}</td><td class="n">{{ $r['response'][$k]['p90'] !== null ? $r['response'][$k]['p90'].' นาที' : '-' }}</td></tr>
                @endforeach
            </table>
        </div>

        <h2>3. รายอำเภอ</h2>
        <table>
            <tr><th>อำเภอ</th><th class="n">เคส</th><th class="n">ยังเปิด</th><th class="n">วิกฤต</th><th class="n">ช่วยออกมา (คน)</th></tr>
            @forelse($r['districts'] as $d)
                <tr><td>{{ $d['name'] }}</td><td class="n">{{ $d['total'] }}</td><td class="n">{{ $d['open'] }}</td><td class="n">{{ $d['critical'] }}</td><td class="n">{{ number_format($d['rescued']) }}</td></tr>
            @empty
                <tr><td colspan="5">ไม่มีข้อมูล</td></tr>
            @endforelse
        </table>

        <h2>4. ทีมกู้ภัย</h2>
        <table>
            <tr><th>ทีม</th><th class="n">ได้รับงาน</th><th class="n">เสร็จ</th><th class="n">ช่วยออกมา (คน)</th><th class="n">เวลาเดินทาง (นาที)</th></tr>
            @forelse($r['teams'] as $t)
                <tr><td>{{ $t['name'] }}</td><td class="n">{{ $t['offered'] }}</td><td class="n">{{ $t['done'] }}</td><td class="n">{{ $t['people'] }}</td><td class="n">{{ $t['travel'] ?? '-' }}</td></tr>
            @empty
                <tr><td colspan="5">ไม่มีข้อมูล</td></tr>
            @endforelse
        </table>

        <h2>5. ศูนย์พักพิง</h2>
        <table>
            <tr><th>ศูนย์</th><th>สถานะ</th><th class="n">คนในศูนย์</th><th class="n">รับได้</th></tr>
            @forelse($r['shelters']['list'] as $s)
                <tr><td>{{ $s->name }}</td><td>{{ $s->statusLabel() }}</td><td class="n">{{ number_format($s->occupancy) }}</td><td class="n">{{ $s->capacity ? number_format($s->capacity) : '-' }}</td></tr>
            @empty
                <tr><td colspan="4">ยังไม่เปิดศูนย์พักพิง</td></tr>
            @endforelse
        </table>

        @if($r['supplies']->isNotEmpty())
            <h2>6. ของบริจาค</h2>
            <table>
                <tr><th>รายการ</th><th class="n">รับเข้า</th><th class="n">จ่ายออก</th><th>หน่วย</th></tr>
                @foreach($r['supplies'] as $s)
                    <tr><td>{{ $s['name'] }}</td><td class="n">{{ number_format($s['in']) }}</td><td class="n">{{ number_format($s['out']) }}</td><td>{{ $s['unit'] }}</td></tr>
                @endforeach
            </table>
        @endif

        <h2>{{ $r['supplies']->isNotEmpty() ? '7' : '6' }}. ข้อมูลน้ำจากประชาชน</h2>
        <div>รายงานระดับน้ำ {{ number_format($r['water']['reports']) }} รายงาน · น้ำลึกเกินเอว {{ number_format($r['water']['deep']) }} จุด</div>

        <div class="sign">
            <div>ลงชื่อ ...........................................<br>(...........................................)<br>ผู้รายงาน ศูนย์สั่งการ · สายด่วน {{ $hotline }}</div>
        </div>
    </div>
</body>
</html>
