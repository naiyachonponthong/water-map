<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\RainForecast;
use App\Models\Subdistrict;
use App\Models\WaterStation;
use App\Support\ForecastService;
use App\Support\SafeUrl;
use App\Support\StationOptions;
use App\Support\StationService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * สถานีวัดระดับน้ำ + พยากรณ์ฝน
 */
class StationController extends Controller
{
    public function index(Request $request)
    {
        $province = $this->province();
        $stations = WaterStation::inProvince($province->id)
            ->with('district:id,name_th')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->orderByRaw("case status when 'critical' then 0 when 'warning' then 1 when 'watch' then 2 when 'normal' then 3 else 4 end")
            ->orderBy('name')->get();

        $counts = WaterStation::inProvince($province->id)->where('is_active', true)
            ->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        $districts = District::where('province_id', $province->id)->orderBy('sort')->get(['id', 'name_th']);
        $byDistrict = RainForecast::where('province_id', $province->id)->whereNotNull('district_id')
            ->whereBetween('date', [today(), today()->addDays(2)])->get()->groupBy('district_id');

        return view('admin.stations.index', [
            'province' => $province,
            'stations' => $stations,
            'counts' => $counts,
            'week' => ForecastService::week($province),
            'districts' => $districts,
            'byDistrict' => $byDistrict,
            'forecastAt' => RainForecast::where('province_id', $province->id)->max('fetched_at'),
        ]);
    }

    public function show(Request $request, WaterStation $station)
    {
        $this->authorizeProvince($station->province_id);
        $days = in_array((int) $request->query('days'), [1, 3, 7, 30], true) ? (int) $request->query('days') : 3;
        $readings = $station->readings()->where('measured_at', '>=', now()->subDays($days))->orderBy('measured_at')->get(['value', 'measured_at']);

        return view('admin.stations.show', [
            'station' => $station->load('district', 'subdistrict', 'cameras'),
            'days' => $days,
            'chart' => [
                'labels' => $readings->map(fn ($r) => $r->measured_at->timezone(config('app.timezone'))->format('Y-m-d H:i'))->values(),
                'values' => $readings->pluck('value')->values(),
                'lines' => array_filter([
                    'ตลิ่ง' => $station->bank_level, 'เฝ้าระวัง' => $station->watch_level,
                    'เตือนภัย' => $station->warning_level, 'วิกฤต' => $station->critical_level,
                ], fn ($v) => $v !== null),
            ],
            'recent' => $station->readings()->with('user:id,name')->latest('measured_at')->limit(30)->get(),
        ]);
    }

    public function geojson()
    {
        $province = $this->province();

        return response()->json(['type' => 'FeatureCollection', 'features' => static::features($province->id, false)]);
    }

    /** สถานีเป็น GeoJSON ใช้ร่วมกับหน้าสาธารณะและแดชบอร์ด */
    public static function features(int $pid, bool $publicOnly): array
    {
        return WaterStation::inProvince($pid)->where('is_active', true)
            ->when($publicOnly, fn ($q) => $q->where('is_public', true))
            ->get()->map(fn (WaterStation $s) => [
                'type' => 'Feature',
                'geometry' => ['type' => 'Point', 'coordinates' => [$s->lng, $s->lat]],
                'properties' => [
                    'id' => $s->id, 'name' => $s->name, 'river' => $s->river, 'status' => $s->status, 'status_label' => $s->statusLabel(),
                    'color' => $s->color(), 'value' => $s->last_value, 'unit' => $s->unit, 'to_bank' => $s->toBank(),
                    'fill' => $s->fillPercent(), 'trend' => $s->trendLabel(),
                    'at' => $s->last_at?->timezone(config('app.timezone'))->format('d/m H:i'), 'link' => $s->link_url,
                ],
            ])->values()->all();
    }

    public function store(Request $request)
    {
        $station = new WaterStation(['province_id' => $this->province()->id]);
        $this->fill($request, $station);

        return $this->ok("เพิ่มสถานี {$station->name} แล้ว");
    }

    public function update(Request $request, WaterStation $station, StationService $service)
    {
        $this->authorizeProvince($station->province_id);
        $this->fill($request, $station);
        // เกณฑ์เปลี่ยน สถานะต้องคำนวณใหม่
        if ($station->last_value !== null && ! in_array($station->status, ['offline'], true)) {
            $station->update(['status' => $station->statusFor($station->last_value)]);
            $service->syncAlert($station);
        }

        return $this->ok("บันทึก {$station->name} แล้ว");
    }

    public function destroy(WaterStation $station, \App\Support\AlertService $alerts)
    {
        $this->authorizeProvince($station->province_id);
        $alerts->resolveKey($station->province_id, 'station:'.$station->id);
        $station->delete();

        return $this->ok("ลบ {$station->name} แล้ว", 'stations.index');
    }

    public function reading(Request $request, WaterStation $station, StationService $service)
    {
        $this->authorizeProvince($station->province_id);
        $data = $request->validate([
            'value' => ['required', 'numeric', 'between:-100,2000'],
            'measured_at' => ['nullable', 'date', 'before_or_equal:now'],
        ], [], ['value' => 'ระดับน้ำ', 'measured_at' => 'เวลา']);
        $service->record($station, (float) $data['value'], ! empty($data['measured_at']) ? Carbon::parse($data['measured_at'], config('app.timezone')) : now(), 'manual', $request->user());

        return back()->with('success', 'บันทึกระดับน้ำ '.$data['value'].' '.$station->unit.' · สถานะ'.$station->fresh()->statusLabel());
    }

    public function import(Request $request, WaterStation $station, StationService $service)
    {
        $this->authorizeProvince($station->province_id);
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']], [], ['file' => 'ไฟล์']);
        $r = $service->importCsv($station, (string) file_get_contents($request->file('file')->getRealPath()), $request->user());

        return back()->with('success', "นำเข้า {$r['created']} ค่า")->with('import_errors', array_slice($r['errors'], 0, 20));
    }

    public function fetch(WaterStation $station, StationService $service)
    {
        $this->authorizeProvince($station->province_id);
        $ok = $service->fetch($station);
        $station->refresh();

        return back()->with($ok ? 'success' : 'error', $ok ? 'ดึงค่าได้ '.$station->last_value.' '.$station->unit : 'ดึงไม่สำเร็จ: '.$station->fetch_error);
    }

    public function forecast(ForecastService $service)
    {
        $province = $this->province();
        try {
            $n = $service->fetch($province);
            $a = $service->evaluate($province);

            return back()->with('success', "อัปเดตพยากรณ์ {$n} จุด".($a ? " · ประกาศเตือนฝน {$a} วัน" : ''));
        } catch (Throwable $e) {
            return back()->with('error', 'ดึงพยากรณ์ไม่สำเร็จ: '.$e->getMessage());
        }
    }

    protected function fill(Request $request, WaterStation $station): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:40'],
            'river' => ['nullable', 'string', 'max:120'],
            'lat' => ['required', 'numeric', 'between:5,21'],
            'lng' => ['required', 'numeric', 'between:97,106'],
            'unit' => ['required', 'string', 'max:20'],
            'bank_level' => ['nullable', 'numeric'],
            'watch_level' => ['nullable', 'numeric'],
            'warning_level' => ['nullable', 'numeric'],
            'critical_level' => ['nullable', 'numeric'],
            'influence_radius_m' => ['required', 'integer', 'between:200,30000'],
            'fetch_mode' => ['required', Rule::in(array_keys(StationOptions::FETCH_MODES))],
            'fetch_url' => ['nullable', 'required_if:fetch_mode,json', 'url', 'max:500'],
            'value_path' => ['nullable', 'required_if:fetch_mode,json', 'string', 'max:120', 'regex:/^[\w.\-*]+$/u'],
            'time_path' => ['nullable', 'string', 'max:120', 'regex:/^[\w.\-*]+$/u'],
            'value_offset' => ['nullable', 'numeric'],
            'link_url' => ['nullable', 'url', 'max:500'],
        ], [
            'fetch_url.required_if' => 'ใส่ URL ของ API',
            'value_path.required_if' => 'ระบุตำแหน่งค่าใน JSON',
        ], ['name' => 'ชื่อสถานี', 'lat' => 'ตำแหน่ง', 'fetch_url' => 'URL']);

        // เกณฑ์ต้องเรียง เฝ้าระวัง <= เตือนภัย <= วิกฤต (ข้ามขั้นที่ไม่ได้ใส่)
        $levels = array_filter(['watch_level' => $data['watch_level'] ?? null, 'warning_level' => $data['warning_level'] ?? null, 'critical_level' => $data['critical_level'] ?? null], fn ($v) => $v !== null && $v !== '');
        $prev = null;
        foreach ($levels as $k => $v) {
            if ($prev !== null && (float) $v < $prev) {
                throw \Illuminate\Validation\ValidationException::withMessages([$k => 'เกณฑ์ต้องเรียงจากเฝ้าระวัง เตือนภัย ไปวิกฤต (ค่าสูงขึ้นตามลำดับ)']);
            }
            $prev = (float) $v;
        }

        if (($data['fetch_mode'] ?? '') === 'json' && ! SafeUrl::isAllowed($data['fetch_url'] ?? null)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['fetch_url' => 'URL นี้ไม่อนุญาต ต้องเป็นเซิร์ฟเวอร์สาธารณะ']);
        }

        $station->fill($data + ['value_offset' => 0]);
        $station->value_offset = (float) ($data['value_offset'] ?? 0);
        $station->is_public = $request->boolean('is_public');
        $station->is_active = $request->boolean('is_active', true);
        $sub = Subdistrict::locate($station->province_id, (float) $data['lat'], (float) $data['lng']);
        $station->subdistrict_id = $sub?->id;
        $station->district_id = $sub?->district_id;
        $station->save();
    }
}
