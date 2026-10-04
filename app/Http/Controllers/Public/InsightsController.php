<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Province;
use App\Models\WaterReport;
use App\Models\WaterStation;
use App\Support\DataSourceHealth;
use App\Support\PublicAreaReference;
use App\Support\PublicWaterHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InsightsController extends Controller
{
    public function index(?Province $province = null)
    {
        if ($province) {
            abort_unless($province->is_active, 404);
        }
        $locations = Province::where('is_active', true)->orderBy('name_th')->get(['slug', 'name_th'])->map(fn ($p) => [
            'slug' => $p->slug, 'name' => $p->name_th,
            'context' => route('public.water.context', $p->slug, false),
            'summary' => route('public.insights.summary', $p->slug, false),
            'history' => route('public.insights.history', $p->slug, false),
            'forecast' => route('public.weather.forecast', $p->slug, false),
            'water' => route('public.water.data', $p->slug, false),
            'health' => route('public.data-status.json', $p->slug, false),
            'home' => route('public.province', $p->slug, false),
            'map' => route('public.map', $p->slug, false),
            'weather' => route('public.weather', $p->slug, false),
            'waterMap' => route('public.water-map', $p->slug, false),
            'status' => route('public.data-status', $p->slug, false),
        ])->values();

        return view('public.insights', compact('province', 'locations'));
    }

    public function summary(Request $request, Province $province, PublicAreaReference $reference)
    {
        abort_unless($province->is_active, 404);
        $input = $request->validate(['district' => 'nullable|string|max:12', 'subdistrict' => 'nullable|string|max:12']);
        $district = $input['district'] ?? '';
        $subdistrict = $input['subdistrict'] ?? '';
        $areas = collect($reference->areas($province));
        $selected = $district ? $areas->firstWhere('code', $district) : null;
        abort_if($district && ! $selected, 422, 'อำเภอไม่อยู่ในจังหวัดที่เลือก');
        abort_if($subdistrict && (! $selected || ! collect($selected['subdistricts'])->contains('code', $subdistrict)), 422, 'ตำบลไม่อยู่ในอำเภอที่เลือก');

        $base = WaterReport::inProvince($province->id)->current()->where('outside_province', false)
            ->where('level', '>=', 2)->whereBetween('updated_at', [now()->subHours(24), now()]);
        $unassigned = (clone $base)->where(function ($q) use ($subdistrict) {
            $q->whereNull('district_id');
            if ($subdistrict) {
                $q->orWhereNull('subdistrict_id');
            }
        })->count();
        if ($district) {
            $base->whereHas('district', fn ($q) => $q->where('province_id', $province->id)->where('code', $district));
        }
        if ($subdistrict) {
            $base->whereHas('subdistrict', fn ($q) => $q->where('province_id', $province->id)->where('code', $subdistrict));
        }
        // Aggregate only: never serialize a report, location, name or phone.
        $rows = $base->get(['updated_at', 'verified', 'level']);
        $hours = [];
        for ($i = 24; $i >= 0; $i--) {
            $hour = now()->startOfHour()->subHours($i);
            $hours[] = ['time' => $hour->toIso8601String(), 'count' => $rows->filter(fn ($r) => $r->updated_at->copy()->startOfHour()->eq($hour))->count()];
        }
        $local = WaterStation::inProvince($province->id)->where('is_public', true)->where('is_active', true)
            ->orderBy('name')->get(['id', 'name', 'unit']);

        return response()->json(['reports' => ['count' => $rows->count(), 'verified' => $rows->where('verified', true)->count(),
            'hours' => $hours, 'unassigned_in_province' => $unassigned,
            'notice' => 'เฉพาะรายงานที่ยังเผยแพร่และอัปเดตใน 24 ชั่วโมง ไม่พบรายงานไม่ได้แปลว่าไม่มีน้ำท่วม'],
            'local_stations' => $local->map(fn ($s) => ['id' => (string) $s->id, 'name' => $s->name, 'source' => 'local', 'unit' => $s->unit]),
            'as_of' => now()->toIso8601String()])->header('Cache-Control', 'no-store');
    }

    public function history(Request $request, Province $province, PublicWaterHistory $history)
    {
        abort_unless($province->is_active, 404);
        $input = $request->validate(['source' => 'required|in:local,thaiwater', 'station' => 'required|regex:/^[0-9]{1,20}$/', 'datum' => 'nullable|in:msl,local']);
        $from = now()->startOfHour()->subHours(71);
        if ($input['source'] === 'local') {
            $station = WaterStation::inProvince($province->id)->where('is_public', true)->where('is_active', true)->findOrFail($input['station']);
            $rows = $station->readings()->whereBetween('measured_at', [$from, now()])->orderBy('measured_at')->get(['value', 'measured_at']);
            $name = $station->name;
            $unit = $station->unit;
            $bank = $station->bank_level;
        } else {
            if (! $history->available()) {
                return response()->json(['message' => 'ยังไม่ได้อัปเดตตารางประวัติน้ำ'], 503)->header('Cache-Control', 'no-store');
            }
            $datum = $input['datum'] ?? 'msl';
            $query = DB::table('public_water_readings')->where('province_id', $province->id)->where('station_id', $input['station'])->where('datum', $datum);
            $station = (clone $query)->where('measured_at', '<=', now())->orderByDesc('measured_at')->first();
            abort_unless($station, 404);
            $rows = $query->whereBetween('measured_at', [$from, now()])->orderBy('measured_at')->get(['value', 'measured_at']);
            $name = $station->name;
            $unit = $datum === 'msl' ? 'ม. รทก.' : 'ม. (ระดับอ้างอิงสถานี)';
            $bank = $datum === 'msl' && $station->bank !== null ? (float) $station->bank : null;
        }
        $buckets = $rows->groupBy(fn ($r) => Carbon::parse($r->measured_at)->format('Y-m-d H'));
        $points = [];
        for ($i = 0; $i < 72; $i++) {
            $hour = $from->copy()->addHours($i);
            $last = $buckets->get($hour->format('Y-m-d H'))?->last();
            $points[] = ['time' => $hour->toIso8601String(), 'value' => $last && $last->value !== null ? (float) $last->value : null,
                'measured_at' => $last ? Carbon::parse($last->measured_at)->toIso8601String() : null];
        }

        return response()->json(['name' => $name, 'unit' => $unit, 'bank' => $bank, 'source' => $input['source'], 'points' => $points,
            'notice' => 'ค่าสุดท้ายที่ตรวจวัดในแต่ละชั่วโมง ช่องว่างคือไม่มีข้อมูล · ThaiWater เริ่มสะสมประวัติหลังอัปเดตรุ่นนี้'])->header('Cache-Control', 'no-store');
    }

    public function status(Province $province)
    {
        abort_unless($province->is_active, 404);
        return view('public.data-status', ['province' => $province]);
    }

    public function statusJson(Province $province, DataSourceHealth $health)
    {
        abort_unless($province->is_active, 404);
        return response()->json($health->snapshot($province))->header('Cache-Control', 'no-store');
    }
}
