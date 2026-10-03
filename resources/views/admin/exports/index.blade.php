@extends('layouts.app')
@section('title', 'รายงานและส่งออก')

@section('content')
    @use('App\Support\CaseOptions')

    <x-page-head title="รายงานและส่งออก" :sub="'ช่วง '.thai_date($from).' ถึง '.thai_date($to)">
        <a href="{{ route('exports.sitrep', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}" target="_blank" class="btn btn-primary"><i class="bi bi-printer me-1"></i>รายงานสถานการณ์ (พิมพ์/PDF)</a>
    </x-page-head>

    <form class="card card-body mb-3 d-flex flex-row flex-wrap gap-2 align-items-end" method="GET">
        <div><label class="form-label small mb-1">ตั้งแต่</label><input type="date" name="from" class="form-control form-control-sm" value="{{ $from->toDateString() }}"></div>
        <div><label class="form-label small mb-1">ถึง</label><input type="date" name="to" class="form-control form-control-sm" value="{{ $to->copy()->timezone(config('app.timezone'))->toDateString() }}"></div>
        <div><label class="form-label small mb-1">อำเภอ</label>
            <select name="district" class="form-select form-select-sm"><option value="">ทั้งจังหวัด</option>@foreach($districts as $d)<option value="{{ $d->id }}" @selected(request('district') == $d->id)>{{ $d->name_th }}</option>@endforeach</select>
        </div>
        <button class="btn btn-sm btn-primary" type="submit">แสดง</button>
        <div class="ms-auto d-flex gap-1">
            @foreach(['วันนี้' => [today(), today()], '7 วัน' => [today()->subDays(6), today()], '30 วัน' => [today()->subDays(29), today()]] as $l => [$a, $b])
                <a class="btn btn-sm btn-light" href="{{ route('exports.index', ['from' => $a->toDateString(), 'to' => $b->toDateString(), 'district' => request('district')]) }}">{{ $l }}</a>
            @endforeach
        </div>
    </form>

    @php $c = $r['cases']; $rs = $r['response']; @endphp
    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><x-stat label="เคสทั้งหมด" :value="$c['total']" icon="life-preserver" tint="primary" /></div>
        <div class="col-6 col-lg-3"><x-stat label="ช่วยแล้ว (เคส)" :value="$c['rescued']" icon="check2-circle" tint="success" /></div>
        <div class="col-6 col-lg-3"><x-stat label="คนที่ช่วยออกมา" :value="$c['people_rescued']" icon="people" tint="teal" /></div>
        <div class="col-6 col-lg-3"><x-stat label="ยังเปิดอยู่" :value="$c['open']" icon="hourglass-split" tint="warning" /></div>
    </div>

    <div class="row g-3 mb-3">
        @foreach(['accept' => ['ทีมตอบรับงาน', 'hand-thumbs-up'], 'arrive' => ['ทีมถึงที่เกิดเหตุ', 'geo-alt'], 'finish' => ['ช่วยเสร็จ', 'flag']] as $k => [$l, $ic])
            <div class="col-md-4">
                <div class="card h-100"><div class="card-body">
                    <div class="small text-muted"><i class="bi bi-{{ $ic }}"></i> เวลานับจากแจ้งจนถึง{{ $l }}</div>
                    @if($rs[$k]['n'])
                        <div class="d-flex align-items-end gap-2 mt-1">
                            <div class="fs-3 fw-bold mono">{{ $rs[$k]['median'] }}</div><div class="text-muted mb-1">นาที (ค่ากลาง)</div>
                        </div>
                        <div class="small text-muted">เฉลี่ย {{ $rs[$k]['avg'] }} · 90% ภายใน {{ $rs[$k]['p90'] }} นาที · {{ $rs[$k]['n'] }} เคส</div>
                    @else
                        <div class="text-muted mt-2">ยังไม่มีข้อมูล</div>
                    @endif
                </div></div>
            </div>
        @endforeach
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-8">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-graph-up text-primary"></i> เคสเข้าใหม่และปิดรายวัน</div>
                <div class="card-body"><div style="height:280px"><canvas id="dailyChart"></canvas></div></div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-pie-chart text-primary"></i> ผลการช่วยเหลือ</div>
                <div class="card-body small">
                    @forelse($c['by_outcome'] as $k => $n)
                        <div class="d-flex justify-content-between py-1 border-bottom"><span>{{ CaseOptions::OUTCOMES[$k] ?? $k }}</span><b class="mono">{{ number_format($n) }}</b></div>
                    @empty
                        <div class="text-muted">ยังไม่มีเคสที่ปิด</div>
                    @endforelse
                    <div class="d-flex justify-content-between py-1 mt-2"><span>มีกลุ่มเปราะบาง</span><b class="mono">{{ number_format($c['vulnerable']) }}</b></div>
                    <div class="d-flex justify-content-between py-1"><span>ช่องทางที่แจ้ง</span><span>@foreach($c['by_source'] as $k => $n){{ CaseOptions::SOURCES[$k][0] ?? $k }} {{ $n }}@if(! $loop->last) · @endif @endforeach</span></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-6">
            <div class="card table-card h-100">
                <div class="card-header"><i class="bi bi-map text-primary"></i> รายอำเภอ</div>
                <div class="table-responsive"><table class="table table-sm mb-0">
                    <thead><tr><th>อำเภอ</th><th class="text-end">เคส</th><th class="text-end">ยังเปิด</th><th class="text-end">วิกฤต</th><th class="text-end">ช่วยออกมา (คน)</th></tr></thead>
                    <tbody>
                        @forelse($r['districts'] as $d)
                            <tr><td>{{ $d['name'] }}</td><td class="text-end mono">{{ $d['total'] }}</td><td class="text-end mono">{{ $d['open'] }}</td><td class="text-end mono {{ $d['critical'] ? 'text-danger' : '' }}">{{ $d['critical'] }}</td><td class="text-end mono">{{ number_format($d['rescued']) }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="text-muted text-center py-3">ไม่มีข้อมูล</td></tr>
                        @endforelse
                    </tbody>
                </table></div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card table-card h-100">
                <div class="card-header"><i class="bi bi-people text-primary"></i> ผลงานทีม</div>
                <div class="table-responsive"><table class="table table-sm mb-0">
                    <thead><tr><th>ทีม</th><th class="text-end">ได้รับงาน</th><th class="text-end">ปฏิเสธ/หมดเวลา</th><th class="text-end">เสร็จ</th><th class="text-end">คน</th><th class="text-end">เดินทาง (นาที)</th></tr></thead>
                    <tbody>
                        @forelse($r['teams'] as $t)
                            <tr><td>{{ $t['name'] }}</td><td class="text-end mono">{{ $t['offered'] }}</td><td class="text-end mono">{{ $t['declined'] }}</td><td class="text-end mono fw-600">{{ $t['done'] }}</td><td class="text-end mono">{{ $t['people'] }}</td><td class="text-end mono">{{ $t['travel'] ?? '-' }}</td></tr>
                        @empty
                            <tr><td colspan="6" class="text-muted text-center py-3">ไม่มีข้อมูล</td></tr>
                        @endforelse
                    </tbody>
                </table></div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-4">
            <div class="card h-100"><div class="card-header"><i class="bi bi-house-heart text-teal"></i> ศูนย์พักพิง</div>
                <div class="card-body small">
                    <div class="d-flex gap-4 mb-2">
                        <div><div class="fs-4 fw-bold mono">{{ $r['shelters']['people_now'] }}</div><div class="text-muted">คนในศูนย์ตอนนี้</div></div>
                        <div><div class="fs-4 fw-bold mono">{{ $r['shelters']['registered'] }}</div><div class="text-muted">ลงทะเบียนในช่วงนี้</div></div>
                    </div>
                    @foreach($r['shelters']['list'] as $s)
                        <div class="d-flex justify-content-between py-1 border-bottom"><span>{{ $s->name }}</span><span class="mono">{{ $s->occupancy }}{{ $s->capacity ? '/'.$s->capacity : '' }}</span></div>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card h-100"><div class="card-header"><i class="bi bi-box-seam text-primary"></i> ของบริจาค รับ / จ่าย</div>
                <div class="card-body small">
                    @forelse($r['supplies'] as $s)
                        <div class="d-flex justify-content-between py-1 border-bottom"><span>{{ $s['name'] }}</span><span class="mono"><span class="text-success">+{{ number_format($s['in']) }}</span> / <span class="text-warning">-{{ number_format($s['out']) }}</span> {{ $s['unit'] }}</span></div>
                    @empty
                        <div class="text-muted">ไม่มีรายการ</div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card h-100"><div class="card-header"><i class="bi bi-download text-primary"></i> ส่งออกข้อมูล (CSV เปิดใน Excel ได้)</div>
                <div class="card-body small">
                    <form method="GET" id="dlForm">
                        <input type="hidden" name="from" value="{{ $from->toDateString() }}">
                        <input type="hidden" name="to" value="{{ $to->copy()->timezone(config('app.timezone'))->toDateString() }}">
                        <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="phones" value="1" id="dlPhones"><label class="form-check-label" for="dlPhones">รวมเบอร์โทรเต็ม (ต้องมีสิทธิ์ และบันทึกลงประวัติการใช้งาน)</label></div>
                        <div class="d-grid gap-1">
                            @foreach($datasets as $k => [$l])
                                <button class="btn btn-light text-start" type="submit" formaction="{{ route('exports.download', $k) }}"><i class="bi bi-filetype-csv me-1"></i>{{ $l }}</button>
                            @endforeach
                        </div>
                    </form>
                    <div class="text-muted mt-2">ข้อมูลเปิดสำหรับหน่วยงานอื่น (ไม่มีข้อมูลส่วนบุคคล): <a href="{{ route('public.open-data', $province) }}" target="_blank" class="mono">open-data.json</a></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <script>
        (function () {
            const d = @json($r['daily']);
            const labels = Object.keys(d).map(k => new Date(k + 'T00:00:00').toLocaleDateString('th-TH', { day: 'numeric', month: 'short' }));
            new Chart(document.getElementById('dailyChart'), {
                type: 'bar',
                data: { labels, datasets: [
                    { label: 'เคสใหม่', data: Object.values(d).map(v => v.new), backgroundColor: '#2563eb', borderRadius: 4 },
                    { label: 'ปิดเคส', data: Object.values(d).map(v => v.closed), backgroundColor: '#16a34a', borderRadius: 4 },
                ] },
                options: { maintainAspectRatio: false, plugins: { legend: { labels: { font: { family: 'Sarabun' } } } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { ticks: { font: { family: 'Sarabun' } } } } },
            });
        })();
    </script>
@endpush
