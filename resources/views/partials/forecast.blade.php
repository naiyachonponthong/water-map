{{-- พยากรณ์ฝน 7 วัน: $week จาก ForecastService::week() --}}
@if($week->isEmpty())
    <div class="small text-muted">ยังไม่มีข้อมูลพยากรณ์</div>
@else
    <div class="fc-strip">
        @foreach($week as $d)
            @php $cat = $d['category']; @endphp
            <div class="fc-day {{ $d['date']->isToday() ? 'today' : '' }}" title="{{ $cat['label'] ?? 'ไม่มีฝน' }}">
                <div class="fc-dow">{{ $d['date']->isToday() ? 'วันนี้' : ['อา.', 'จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.'][$d['date']->dayOfWeek] }}</div>
                <div class="fc-ico" style="color:{{ $cat['color'] ?? '#cbd5e1' }}"><i class="bi bi-{{ ! $cat ? 'sun' : ($d['rain_mm'] >= 35 ? 'cloud-rain-heavy-fill' : 'cloud-drizzle-fill') }}"></i></div>
                <div class="fc-mm mono" style="{{ $d['rain_mm'] >= 35 ? 'color:'.$cat['color'].';font-weight:700' : '' }}">{{ $d['rain_mm'] >= 1 ? number_format($d['rain_mm'], 0) : '0' }}<small> มม.</small></div>
                @if($d['rain_prob'] !== null)<div class="fc-prob">{{ $d['rain_prob'] }}%</div>@endif
            </div>
        @endforeach
    </div>
@endif
