<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AreaWaterLevel;
use App\Models\RiskPoint;
use App\Models\Subdistrict;
use App\Support\RiskEngine;
use App\Support\RiskImporter;
use App\Support\RiskOptions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RiskController extends Controller
{
    public function index(Request $request)
    {
        $province = $this->province();
        $tab = in_array($request->query('tab'), ['live', 'threatened', 'pending', 'inactive'], true) ? $request->query('tab') : 'live';

        $base = RiskPoint::inProvince($province->id)
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->query('type')))
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->query('q').'%'));

        $counts = [
            'live' => (clone $base)->live()->count(),
            'threatened' => (clone $base)->where('review', 'approved')->where('status', 'threatened')->count(),
            'pending' => (clone $base)->where('review', 'pending')->count(),
            'inactive' => (clone $base)->where(fn ($q) => $q->where('status', 'inactive')->orWhere('review', 'rejected'))->count(),
        ];

        $points = (clone $base)
            ->with('subdistrict:id,name_th,code', 'district:id,name_th')
            ->when($tab === 'live', fn ($q) => $q->live())
            ->when($tab === 'threatened', fn ($q) => $q->where('review', 'approved')->where('status', 'threatened'))
            ->when($tab === 'pending', fn ($q) => $q->where('review', 'pending'))
            ->when($tab === 'inactive', fn ($q) => $q->where(fn ($w) => $w->where('status', 'inactive')->orWhere('review', 'rejected')))
            ->orderByRaw("case status when 'threatened' then 0 else 1 end")
            ->orderByRaw("case severity when 'high' then 0 when 'medium' then 1 else 2 end")
            ->orderBy('name')
            ->paginate(30)->withQueryString();

        return view('admin.risks.index', [
            'province' => $province,
            'tab' => $tab,
            'counts' => $counts,
            'points' => $points,
            'areaLevels' => AreaWaterLevel::where('province_id', $province->id)->current()->with('subdistrict.district', 'setter:id,name')->latest()->get(),
            'subdistricts' => Subdistrict::where('province_id', $province->id)->with('district:id,name_th')->orderBy('name_th')->get(['id', 'district_id', 'name_th', 'code']),
        ]);
    }

    public function geojson()
    {
        $province = $this->province();
        $features = RiskPoint::inProvince($province->id)->where('review', '!=', 'rejected')->where('status', '!=', 'inactive')->get()
            ->map(fn (RiskPoint $p) => [
                'type' => 'Feature',
                'geometry' => $p->zone ? $p->zoneArray() : ['type' => 'Point', 'coordinates' => [$p->lng, $p->lat]],
                'properties' => [
                    'id' => $p->id, 'name' => $p->name, 'type' => $p->typeLabel(), 'icon' => $p->icon(), 'color' => $p->color(),
                    'status' => $p->status, 'pending' => $p->review === 'pending', 'radius' => $p->zone ? null : $p->radius(),
                    'lat' => $p->lat, 'lng' => $p->lng, 'reason' => $p->threat_reason, 'severity' => $p->severity,
                ],
            ]);

        return response()->json(['type' => 'FeatureCollection', 'features' => $features]);
    }

    public function store(Request $request)
    {
        $province = $this->province();
        $risk = new RiskPoint(['province_id' => $province->id, 'source' => 'staff', 'review' => 'approved', 'created_by' => $request->user()->id]);
        $this->fillRisk($request, $risk);

        return $this->ok("เพิ่มจุดเสี่ยง {$risk->name} แล้ว");
    }

    public function update(Request $request, RiskPoint $risk)
    {
        $this->authorizeProvince($risk->province_id);
        $this->fillRisk($request, $risk);

        return $this->ok("บันทึก {$risk->name} แล้ว");
    }

    public function destroy(RiskPoint $risk)
    {
        $this->authorizeProvince($risk->province_id);
        $risk->delete();

        return $this->ok("ลบ {$risk->name} แล้ว");
    }

    /** อนุมัติ / ปฏิเสธจุดที่ประชาชนเสนอ */
    public function review(Request $request, RiskPoint $risk)
    {
        $this->authorizeProvince($risk->province_id);
        $decision = $request->validate(['decision' => ['required', Rule::in(['approved', 'rejected'])]])['decision'];
        $risk->update(['review' => $decision, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);

        return $this->ok($decision === 'approved' ? "อนุมัติ {$risk->name} แล้ว" : "ปฏิเสธ {$risk->name} แล้ว");
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:20480'],
            'type' => ['required', Rule::in(array_keys(RiskOptions::TYPES))],
        ], [], ['file' => 'ไฟล์']);
        $report = (new RiskImporter($this->province(), $request->user()))->risks($request->file('file'), $request->input('type'));

        return back()->with('success', "นำเข้าจุดเสี่ยง {$report['created']} จุด".($report['skipped'] ? " ข้าม {$report['skipped']}" : ''))
            ->with('import_errors', array_slice($report['errors'], 0, 20));
    }

    /** ศูนย์ประกาศระดับน้ำรายตำบล */
    public function declareLevel(Request $request, RiskEngine $engine)
    {
        $province = $this->province();
        $data = $request->validate([
            'subdistrict_ids' => ['required', 'array', 'min:1'],
            'subdistrict_ids.*' => [Rule::exists('subdistricts', 'id')->where('province_id', $province->id)],
            'level' => ['required', 'integer', 'between:1,6'],
            'hours' => ['required', 'integer', 'between:1,72'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], ['subdistrict_ids' => 'ตำบล', 'level' => 'ระดับน้ำ', 'hours' => 'ระยะเวลา']);

        foreach ($data['subdistrict_ids'] as $sid) {
            AreaWaterLevel::where('subdistrict_id', $sid)->current()->update(['expires_at' => now()]);
            AreaWaterLevel::create([
                'province_id' => $province->id, 'subdistrict_id' => $sid, 'level' => $data['level'],
                'note' => $data['note'] ?? null, 'set_by' => $request->user()->id, 'expires_at' => now()->addHours($data['hours']),
            ]);
        }
        $r = $engine->evaluate($province);

        return back()->with('success', 'ประกาศระดับน้ำ '.count($data['subdistrict_ids'])." ตำบลแล้ว · จุดเสี่ยงขึ้นเตือน {$r['threatened']} · เปิดเคสตรวจเยี่ยม {$r['cases']}");
    }

    public function endLevel(AreaWaterLevel $level)
    {
        $this->authorizeProvince($level->province_id);
        $level->update(['expires_at' => now()]);

        return $this->ok('ยกเลิกประกาศระดับน้ำแล้ว');
    }

    public function evaluate(RiskEngine $engine)
    {
        $r = $engine->evaluate($this->province());

        return back()->with('success', "ตรวจแล้ว: ขึ้นเตือน {$r['threatened']} · กลับปกติ {$r['cleared']} · เปิดเคสตรวจเยี่ยม {$r['cases']}");
    }

    protected function fillRisk(Request $request, RiskPoint $risk): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(array_keys(RiskOptions::TYPES))],
            'description' => ['nullable', 'string', 'max:2000'],
            'lat' => ['required', 'numeric', 'between:5,21'],
            'lng' => ['required', 'numeric', 'between:97,106'],
            'zone' => ['nullable', 'json'],
            'radius_m' => ['nullable', 'integer', 'between:50,20000'],
            'trigger_level' => ['required', 'integer', 'between:1,6'],
            'severity' => ['required', Rule::in(array_keys(RiskOptions::SEVERITY))],
            'status' => ['nullable', Rule::in(['normal', 'inactive'])],
        ], [], ['name' => 'ชื่อ', 'type' => 'ประเภท', 'lat' => 'ตำแหน่ง']);

        $risk->fill(collect($data)->except(['zone', 'status'])->all());
        $risk->is_public = $request->boolean('is_public');
        if (isset($data['status']) && $risk->status !== 'threatened') {
            $risk->status = $data['status'];
        } elseif (($data['status'] ?? null) === 'inactive') {
            $risk->status = 'inactive';
        }
        $risk->setZone($data['zone'] ?? null);
        $sub = Subdistrict::locate($risk->province_id, $risk->lat, $risk->lng);
        $risk->subdistrict_id = $sub?->id;
        $risk->district_id = $sub?->district_id;
        $risk->save();
    }
}
