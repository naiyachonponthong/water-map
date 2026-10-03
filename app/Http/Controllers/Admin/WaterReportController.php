<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\WaterReport;
use App\Support\ThaiDate;
use App\Support\WaterReportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * คัดกรองรายงานระดับน้ำ
 */
class WaterReportController extends Controller
{
    public const TABS = [
        'review' => 'รอตรวจ',
        'live' => 'แสดงอยู่',
        'unconfirmed' => 'ยังไม่มีคนยืนยัน',
        'expired' => 'หมดอายุ',
        'hidden' => 'ซ่อน/ปฏิเสธ',
    ];

    public function index(Request $request)
    {
        $province = $this->province();
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'review';

        $base = WaterReport::inProvince($province->id)
            ->when($request->filled('district'), fn ($q) => $q->where('district_id', $request->integer('district')))
            ->when($request->filled('level'), fn ($q) => $q->where('level', '>=', $request->integer('level')))
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->query('source')));

        $scope = fn ($q, $t) => match ($t) {
            'review' => $q->where('status', 'pending'),
            'live' => $q->current(),
            'unconfirmed' => $q->current()->where('verified', false)->where('confirm_count', 0),
            'expired' => $q->where('status', 'published')->where('expires_at', '<=', now())->where('expires_at', '>=', now()->subDays(3)),
            'hidden' => $q->whereIn('status', ['hidden', 'rejected']),
        };

        $counts = collect(self::TABS)->map(fn ($l, $t) => $scope(clone $base, $t)->count());
        $reports = $scope(clone $base, $tab)
            ->with('subdistrict:id,name_th,code', 'district:id,name_th,code', 'team:id,name', 'reviewer:id,name')
            ->orderByDesc('level')->latest('updated_at')
            ->paginate(30)->withQueryString();

        $today = WaterReport::inProvince($province->id)->where('created_at', '>=', today());

        return view('admin.reports.index', [
            'province' => $province,
            'tab' => $tab,
            'counts' => $counts,
            'reports' => $reports,
            'districts' => District::where('province_id', $province->id)->orderBy('sort')->get(['id', 'name_th']),
            'stats' => [
                'today' => (clone $today)->count(),
                'deep' => WaterReport::inProvince($province->id)->current()->where('level', '>=', 4)->count(),
                'trusted' => WaterReport::inProvince($province->id)->current()->trusted()->count(),
                'rising' => WaterReport::inProvince($province->id)->current()->where('trend', 'rising')->count(),
            ],
        ]);
    }

    /** ชั้นแผนที่: รายงานที่แสดงอยู่ + รอตรวจ */
    public function geojson()
    {
        $province = $this->province();
        $features = WaterReport::inProvince($province->id)
            ->where(fn ($q) => $q->where(fn ($w) => $w->where('status', 'published')->where('expires_at', '>', now()))->orWhere('status', 'pending'))
            ->latest('updated_at')->limit(2000)->get()
            ->map(fn (WaterReport $r) => [
                'type' => 'Feature',
                'geometry' => ['type' => 'Point', 'coordinates' => [$r->lng, $r->lat]],
                'properties' => [
                    'id' => $r->id, 'level' => $r->level, 'label' => $r->levelLabel(), 'color' => $r->color(),
                    'opacity' => $r->freshness(), 'pending' => $r->status === 'pending', 'trusted' => $r->isTrusted(),
                    'trend' => $r->trendLabel(), 'note' => $r->note, 'ago' => ThaiDate::ago($r->updated_at), 'source' => $r->sourceLabel(),
                ],
            ])->values();

        return response()->json(['type' => 'FeatureCollection', 'features' => $features]);
    }

    /** เจ้าหน้าที่บันทึกระดับน้ำเอง (เช่น รับแจ้งทางวิทยุ) ถือว่ายืนยันแล้ว */
    public function store(Request $request, WaterReportService $service)
    {
        $data = $request->validate(WaterReportService::rules(true), [], WaterReportService::attributes());
        $service->submit($this->province(), $data, ['source' => 'staff', 'user' => $request->user(), 'photos' => $request->file('photos', [])]);

        return $this->ok('บันทึกระดับน้ำแล้ว');
    }

    public function moderate(Request $request, WaterReport $report, WaterReportService $service)
    {
        $this->authorizeProvince($report->province_id);
        $data = $request->validate([
            'action' => ['required', Rule::in(['publish', 'verify', 'hide', 'reject'])],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->ok($service->moderate($report, $data['action'], $request->user(), $data['reason'] ?? null));
    }

    public function bulk(Request $request, WaterReportService $service)
    {
        $province = $this->province();
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer'],
            'action' => ['required', Rule::in(['publish', 'verify', 'hide', 'reject'])],
        ], ['ids.required' => 'เลือกรายงานก่อน']);

        $reports = WaterReport::inProvince($province->id)->whereIn('id', $data['ids'])->get();
        foreach ($reports as $r) {
            $service->moderate($r, $data['action'], $request->user(), null, false);
        }
        if (in_array($data['action'], ['publish', 'verify'], true)) {
            \App\Jobs\EvaluateProvinceRisks::soon($province->id, 0);
        }

        return back()->with('success', 'ดำเนินการ '.$reports->count().' รายงานแล้ว');
    }
}
