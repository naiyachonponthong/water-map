<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Admin\StationController;
use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\Camera;
use App\Models\Province;
use App\Models\RiskPoint;
use App\Models\Shelter;
use App\Models\WaterReport;
use App\Support\ReportOptions;
use App\Support\Settings;
use App\Support\ThaiDate;
use App\Support\WaterReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * ประชาชนรายงานระดับน้ำ + แผนที่สถานการณ์สาธารณะ
 */
class WaterReportController extends Controller
{
    public function form(Request $request, Province $province)
    {
        abort_unless($province->is_active, 404);

        return $this->withDevice($request, response()->view('public.reports.form', [
            'province' => $province,
            'open' => (bool) Settings::get('reports_open', $province->id),
            'premoderate' => (bool) Settings::get('report_premoderate', $province->id),
            'levels' => config('floodthai.water_levels'),
            'hotline' => Settings::get('hotline', $province->id),
        ]));
    }

    public function store(Request $request, Province $province, WaterReportService $service)
    {
        abort_unless($province->is_active, 404);
        if (! Settings::get('reports_open', $province->id)) {
            return redirect()->route('public.reports.create', $province)->with('error', 'ขณะนี้ยังไม่เปิดรับรายงานระดับน้ำ');
        }
        if (filled($request->input('website'))) {
            return redirect()->route('public.map', $province);
        }

        // เบราว์เซอร์บางรุ่นคืนค่า GPS accuracy ที่สูงมากเมื่อระบุตำแหน่งได้เพียงคร่าว ๆ
        // ยังใช้พิกัดรายงานได้ แต่ไม่ควรเก็บค่าความแม่นยำที่เกินขอบเขตเป็นข้อมูลจริง
        $accuracy = $request->input('accuracy_m');
        if (is_numeric($accuracy) && (float) $accuracy > 100000) {
            $request->merge(['accuracy_m' => null]);
        }

        $data = $request->validate(WaterReportService::rules(), [
            'lat.required' => 'กรุณาระบุตำแหน่ง',
            'level.required' => 'เลือกระดับน้ำ',
            'reporter_phone.regex' => 'เบอร์โทรไม่ถูกต้อง',
        ], WaterReportService::attributes());

        [$report, $updated] = $service->submit($province, $data, [
            'source' => 'web',
            'photos' => $request->file('photos', []),
            'device_hash' => $this->deviceHash($request),
            'ip_hash' => hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')),
        ]);

        $msg = match (true) {
            $report->status === 'pending' => 'ส่งรายงานแล้ว รอเจ้าหน้าที่ตรวจก่อนแสดงบนแผนที่',
            $updated => 'อัปเดตรายงานจุดเดิมของคุณแล้ว',
            default => 'ขอบคุณ รายงานของคุณขึ้นแผนที่แล้ว',
        };

        return redirect()->route('public.map', ['province' => $province, 'r' => $report->id])->with('success', $msg);
    }

    /** แผนที่สถานการณ์: รายงานระดับน้ำ + จุดเสี่ยง */
    public function map(Request $request, Province $province)
    {
        abort_unless($province->is_active, 404);
        $current = WaterReport::inProvince($province->id)->current()->where('outside_province', false);

        return $this->withDevice($request, response()->view('public.reports.map', [
            'province' => $province,
            'provinceOptions' => Province::where('is_active', true)->orderBy('name_th')->get(['slug', 'name_th']),
            'open' => (bool) Settings::get('reports_open', $province->id),
            'levels' => config('floodthai.water_levels'),
            'hotline' => Settings::get('hotline', $province->id),
            'summary' => [
                'reports' => (clone $current)->count(),
                'deep' => (clone $current)->where('level', '>=', 4)->count(),
                'risks' => RiskPoint::inProvince($province->id)->live()->where('is_public', true)->where('status', 'threatened')->count(),
            ],
            'focus' => $request->integer('r') ?: null,
            'alerts' => Alert::inProvince($province->id)->active()->where('is_public', true)->severeFirst()->limit(3)->get(),
        ]));
    }

    public function geojson(Province $province)
    {
        abort_unless($province->is_active, 404);

        $data = Cache::remember("public-map:{$province->id}", 30, function () use ($province) {
            $reports = WaterReport::inProvince($province->id)->current()
                ->where('outside_province', false)
                ->with('subdistrict:id,name_th,code', 'district:id,name_th,code')
                ->latest('updated_at')->limit(1500)->get()
                ->map(fn (WaterReport $r) => [
                    'type' => 'Feature',
                    'geometry' => ['type' => 'Point', 'coordinates' => [$r->lng, $r->lat]],
                    'properties' => [
                        'kind' => 'report',
                        'id' => $r->id,
                        'level' => $r->level,
                        'label' => $r->levelLabel(),
                        'color' => $r->color(),
                        'opacity' => $r->freshness(),
                        'trend' => $r->trendLabel(),
                        'place' => $r->placeLabel(),
                        'note' => $r->note,
                        'photos' => $r->photoUrls(),
                        'area' => $r->areaLabel(),
                        'ago' => ThaiDate::ago($r->updated_at),
                        'source' => $r->sourceLabel(),
                        'verified' => $r->verified,
                        'confirm' => $r->confirm_count,
                        'receded' => $r->recede_count,
                    ],
                ])->values();

            $risks = RiskPoint::inProvince($province->id)->live()->where('is_public', true)->get()
                ->map(fn (RiskPoint $p) => [
                    'type' => 'Feature',
                    'geometry' => $p->zone ? $p->zoneArray() : ['type' => 'Point', 'coordinates' => [$p->lng, $p->lat]],
                    'properties' => [
                        'id' => $p->id, 'name' => $p->name, 'type' => $p->typeLabel(), 'icon' => $p->icon(), 'color' => $p->color(),
                        'status' => $p->status, 'radius' => null, 'lat' => $p->lat, 'lng' => $p->lng, 'description' => $p->description,
                    ],
                ])->values();

            return [
                'reports' => ['type' => 'FeatureCollection', 'features' => $reports],
                'risks' => ['type' => 'FeatureCollection', 'features' => $risks],
                'stations' => ['type' => 'FeatureCollection', 'features' => StationController::features($province->id, true)],
                'cameras' => Camera::inProvince($province->id)->where('is_active', true)->where('is_public', true)->orderBy('sort')->get()->map->toViewer()->values(),
                'shelters' => Shelter::inProvince($province->id)->where('is_public', true)->whereIn('status', ['open', 'full'])->get()
                    ->map(fn ($s) => ['name' => $s->name, 'lat' => $s->lat, 'lng' => $s->lng, 'status' => $s->statusLabel(), 'color' => $s->color(), 'available' => $s->available(), 'phone' => $s->contact_phone])->values(),
                'time' => now()->format('H:i'),
            ];
        });

        return response()->json($data)->header('Cache-Control', 'public, max-age=30');
    }

    public function vote(Request $request, Province $province, WaterReport $report, WaterReportService $service)
    {
        abort_unless($report->province_id === $province->id, 404);
        $kind = $request->validate(['kind' => ['required', Rule::in(array_keys(ReportOptions::VOTES))]])['kind'];
        $result = $service->vote($report, $this->deviceHash($request) ?? hash('sha256', $request->ip().'|'.$request->userAgent()), $kind);
        Cache::forget("public-map:{$province->id}");

        return response()->json($result + [
            'confirm' => $report->confirm_count,
            'receded' => $report->recede_count,
        ], $result['ok'] ? 200 : 422);
    }

    protected function deviceHash(Request $request): ?string
    {
        return $request->cookie('fdev') ? hash('sha256', $request->cookie('fdev')) : null;
    }

    protected function withDevice(Request $request, $response)
    {
        if (! $request->cookie('fdev')) {
            $response->cookie('fdev', (string) Str::uuid(), 60 * 24 * 365);
        }

        return $response;
    }
}
