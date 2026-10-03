<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Province;
use App\Models\WaterReport;
use App\Support\PublicAreaReference;
use App\Support\PublicWaterService;
use App\Support\PublicWeatherService;
use Throwable;

class WaterMapController extends Controller
{
    public function index(Province $province)
    {
        abort_unless($province->is_active, 404);
        $locations = Province::where('is_active', true)->orderBy('name_th')->get(['slug', 'name_th'])
            ->map(fn ($p) => ['slug' => $p->slug, 'name' => $p->name_th, 'url' => route('public.water-map', $p->slug, false),
                'contextUrl' => route('public.water.context', $p->slug, false), 'dataUrl' => route('public.water.data', $p->slug, false),
                'reportsUrl' => route('public.water.reports', $p->slug, false), 'homeUrl' => route('public.province', $p->slug, false)]);

        return view('public.water-map', compact('province', 'locations'));
    }

    public function context(Province $province, PublicAreaReference $areas, PublicWeatherService $weather)
    {
        abort_unless($province->is_active, 404);

        return response()->json(['name' => $province->name_th, 'code' => $province->code, 'center' => $weather->center($province),
            'boundary' => $areas->boundary($province), 'areas' => $areas->areas($province),
            'boundary_source' => 'geoBoundaries / OpenStreetMap (ODbL), 2017 · ขอบเขตอ้างอิง ไม่ใช้ตัดสินสิทธิ์หรือสั่งการ',
        ])->header('Cache-Control', 'public, max-age=3600');
    }

    public function data(Province $province, PublicWaterService $water)
    {
        abort_unless($province->is_active, 404);
        try {
            return response()->json($water->stations($province))->header('Cache-Control', 'no-store');
        } catch (Throwable $e) {
            return response()->json(['message' => 'ยังเชื่อมข้อมูล ThaiWater ไม่ได้ กรุณาลองใหม่ หรือเปิดเว็บไซต์ต้นทาง'], 503)
                ->header('Retry-After', '60')->header('Cache-Control', 'no-store');
        }
    }

    /** Public observations only: no reporter identity, phone, note, device or case details. */
    public function reports(Province $province)
    {
        abort_unless($province->is_active, 404);
        $rows = WaterReport::inProvince($province->id)->current()->where('updated_at', '>=', now()->subHours(12))
            ->where('outside_province', false)->where('level', '>=', 2)
            ->with('district:id,code', 'subdistrict:id,code')->latest('updated_at')->limit(3001)->get();

        return response()->json(['reports' => $rows->take(3000)->map(fn ($r) => ['id' => $r->id, 'lat' => $r->lat, 'lng' => $r->lng,
            'level' => $r->level, 'label' => $r->levelLabel(), 'color' => $r->color(), 'verified' => $r->verified,
            'trusted' => $r->isTrusted(), 'district_code' => $r->district?->code, 'subdistrict_code' => $r->subdistrict?->code,
            'updated_at' => $r->updated_at->toIso8601String(), 'expires_at' => $r->expires_at->toIso8601String(),
        ])->values(), 'truncated' => $rows->count() > 3000, 'window_hours' => 12, 'fetched_at' => now()->toIso8601String(),
            'notice' => 'ไม่พบรายงาน ไม่ได้หมายความว่าไม่มีน้ำท่วม ข้อมูลอาจยังไม่ครอบคลุมพื้นที่',
        ])->header('Cache-Control', 'no-store');
    }
}
