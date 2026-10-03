@extends('layouts.app')
@section('title', $station->name)

@push('head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
@endpush

@section('content')
    @use('App\Support\StationOptions')

    <x-page-head :title="$station->name" :sub="collect([$station->river ? 'ลำน้ำ'.$station->river : null, $station->district ? 'อ.'.$station->district->name_th : null, $station->code])->filter()->implode(' · ')">
        <a href="{{ route('stations.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>สถานีทั้งหมด</a>
        @if($station->fetch_mode === 'json')
            <form method="POST" action="{{ route('stations.fetch', $station) }}">@csrf<button class="btn btn-soft" type="submit"><i class="bi bi-cloud-download me-1"></i>ดึงค่าตอนนี้</button></form>
        @endif
        <button class="btn btn-soft" data-form-modal="#stationModal" data-action="{{ route('stations.update', $station) }}" data-method="PUT" data-title="แก้ไขสถานี"
            data-fill="{{ json_encode($station->only(['name', 'code', 'river', 'lat', 'lng', 'unit', 'bank_level', 'watch_level', 'warning_level', 'critical_level', 'influence_radius_m', 'fetch_mode', 'fetch_url', 'value_path', 'time_path', 'value_offset', 'link_url', 'is_public', 'is_active'])) }}"><i class="bi bi-pencil me-1"></i>แก้ไข</button>
    </x-page-head>

    @if(session('import_errors'))
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i>
            <div><div class="fw-600 mb-1">บางแถวนำเข้าไม่ได้</div><ul class="mb-0 small ps-3">@foreach(session('import_errors') as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        </div>
    @endif
    @if($station->fetch_error)
        <div class="alert alert-danger small"><i class="bi bi-cloud-slash"></i><div>ดึงข้อมูลล่าสุดไม่สำเร็จ ({{ thai_date($station->fetched_at, 'ago') }}): {{ $station->fetch_error }}</div></div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <div class="card h-100"><div class="card-body">
                <div class="small text-muted">ระดับน้ำล่าสุด</div>
                <div class="fs-3 fw-bold mono">{{ $station->last_value !== null ? number_format($station->last_value, 2) : '-' }} <small class="fs-6 text-muted fw-normal">{{ $station->unit }}</small></div>
                <div class="small text-muted">{{ $station->last_at ? thai_date($station->last_at, 'compact') : 'ยังไม่มีค่า' }}</div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card h-100"><div class="card-body">
                <div class="small text-muted">สถานะ</div>
                <div class="fs-4 fw-bold" style="color:{{ $station->color() }}">{{ $station->statusLabel() }}</div>
                <div class="small">{{ $station->trendLabel() ?? '' }}</div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card h-100"><div class="card-body">
                <div class="small text-muted">เทียบตลิ่ง</div>
                @php $tb = $station->toBank(); @endphp
                <div class="fs-4 fw-bold mono {{ $tb !== null && $tb >= 0 ? 'text-danger' : '' }}">{{ $tb === null ? '-' : ($tb >= 0 ? '+' : '').number_format($tb, 2) }} <small class="fs-6 fw-normal text-muted">ม.</small></div>
                <div class="small text-muted">ตลิ่ง {{ $station->bank_level ?? '-' }}</div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card h-100"><div class="card-body small">
                <div class="text-muted mb-1">เกณฑ์</div>
                <div><span class="lv-dot" style="background:#eab308;width:8px;height:8px"></span> เฝ้าระวัง {{ $station->watch_level ?? '-' }}</div>
                <div><span class="lv-dot" style="background:#f97316;width:8px;height:8px"></span> เตือนภัย {{ $station->warning_level ?? '-' }}</div>
                <div><span class="lv-dot" style="background:#dc2626;width:8px;height:8px"></span> วิกฤต {{ $station->critical_level ?? '-' }}</div>
            </div></div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-graph-up text-primary"></i> กราฟระดับน้ำ
                    <div class="ch-actions">
                        <div class="btn-group btn-group-sm">
                            @foreach([1 => '24 ชม.', 3 => '3 วัน', 7 => '7 วัน', 30 => '30 วัน'] as $d => $l)
                                <a href="{{ route('stations.show', [$station, 'days' => $d]) }}" class="btn {{ $days === $d ? 'btn-primary' : 'btn-light' }}">{{ $l }}</a>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    @if(count($chart['values']))
                        <div style="height:320px"><canvas id="levelChart"></canvas></div>
                    @else
                        <x-empty icon="graph-up" title="ยังไม่มีข้อมูลในช่วงนี้" text="บันทึกค่าด้านขวา นำเข้าไฟล์ หรือตั้งให้ดึงจาก API" />
                    @endif
                </div>
            </div>

            @if($station->cameras->isNotEmpty())
                <div class="row g-2 mb-3">
                    @foreach($station->cameras as $cam)
                        <div class="col-md-6"><div class="cam-tile" data-camera="{{ json_encode($cam->toViewer()) }}"><div class="cam-cap">{{ $cam->name }}</div></div></div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="col-xl-4">
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-pencil-square text-primary"></i> บันทึกระดับน้ำ</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('stations.reading', $station) }}">
                        @csrf
                        <div class="input-group mb-2">
                            <input name="value" type="number" step="0.01" class="form-control mono" placeholder="ระดับน้ำ" required>
                            <span class="input-group-text">{{ $station->unit }}</span>
                        </div>
                        <input name="measured_at" type="datetime-local" class="form-control mb-2" max="{{ now()->format('Y-m-d\TH:i') }}">
                        <div class="form-text mb-2">เว้นเวลาว่างไว้ = ตอนนี้</div>
                        <button class="btn btn-primary w-100" type="submit">บันทึก</button>
                    </form>
                    <hr>
                    <form method="POST" action="{{ route('stations.import', $station) }}" enctype="multipart/form-data">
                        @csrf
                        <label class="form-label small">นำเข้า CSV (คอลัมน์ เวลา, ระดับน้ำ)</label>
                        <div class="input-group input-group-sm">
                            <input type="file" name="file" class="form-control" accept=".csv,.txt" required>
                            <button class="btn btn-light" type="submit">นำเข้า</button>
                        </div>
                        <div class="form-text">รองรับ 02/10/2569 14:00 หรือ 2026-10-02 14:00</div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><i class="bi bi-list-ul text-primary"></i> ค่าล่าสุด</div>
                <div class="table-responsive" style="max-height:360px">
                    <table class="table table-sm small mb-0">
                        <tbody>
                            @forelse($recent as $r)
                                <tr>
                                    <td class="text-muted">{{ thai_date($r->measured_at, 'compact') }}</td>
                                    <td class="text-end mono fw-600" style="color:{{ StationOptions::STATUS[$station->statusFor($r->value)][2] }}">{{ number_format($r->value, 2) }}</td>
                                    <td class="text-muted">{{ $r->source === 'manual' ? ($r->user?->name ?? 'บันทึกเอง') : ($r->source === 'json' ? 'API' : 'นำเข้า') }}</td>
                                </tr>
                            @empty
                                <tr><td class="text-muted text-center py-3">ยังไม่มีค่า</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <form method="POST" action="{{ route('stations.destroy', $station) }}" data-confirm="ลบสถานี {{ $station->name }} และค่าที่บันทึกทั้งหมด?" class="mt-2">@csrf @method('DELETE')
                <button class="btn btn-link btn-sm text-danger" type="submit"><i class="bi bi-trash me-1"></i>ลบสถานี</button>
            </form>
        </div>
    </div>

    @include('admin.stations._form', ['province' => $station->province])
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="{{ asset('js/map.js') }}?v={{ filemtime(public_path('js/map.js')) }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.15/dist/hls.min.js"></script>
    <script src="{{ asset('js/stations.js') }}?v={{ filemtime(public_path('js/stations.js')) }}"></script>
    @if(count($chart['values']))
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
        <script>
            (function () {
                const c = @json($chart);
                const colors = { 'ตลิ่ง': '#334155', 'เฝ้าระวัง': '#eab308', 'เตือนภัย': '#f97316', 'วิกฤต': '#dc2626' };
                const labels = c.labels.map(t => { const d = new Date(t.replace(' ', 'T')); return d.toLocaleString('th-TH', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }); });
                const datasets = [{ label: 'ระดับน้ำ', data: c.values, borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,.12)', fill: true, tension: .25, pointRadius: c.values.length > 60 ? 0 : 2 }];
                Object.entries(c.lines).forEach(([name, v]) => datasets.push({ label: name, data: c.values.map(() => v), borderColor: colors[name], borderDash: [6, 4], borderWidth: 1.5, pointRadius: 0, fill: false }));
                new Chart(document.getElementById('levelChart'), {
                    type: 'line', data: { labels, datasets },
                    options: { maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, plugins: { legend: { labels: { font: { family: 'Sarabun' } } } }, scales: { x: { ticks: { maxTicksLimit: 8, font: { family: 'Sarabun' } } } } },
                });
            })();
        </script>
    @endif
    <script>
        document.querySelectorAll('[data-camera]').forEach(el => FloodStations.mountCamera(el, JSON.parse(el.dataset.camera)));
    </script>
@endpush
