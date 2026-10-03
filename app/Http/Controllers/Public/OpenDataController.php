<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\Evacuee;
use App\Models\HelpRequest;
use App\Models\Province;
use App\Models\Shelter;
use App\Models\WaterReport;
use App\Models\WaterStation;
use App\Support\CaseOptions;
use Illuminate\Support\Facades\Cache;

/**
 * ข้อมูลสรุปแบบเปิด ให้หน่วยงาน สื่อ หรือนักพัฒนานำไปใช้ต่อ
 * มีแต่ตัวเลขรวมและข้อมูลสถานที่สาธารณะ ไม่มีชื่อ เบอร์ หรือพิกัดของผู้ขอความช่วยเหลือ
 */
class OpenDataController extends Controller
{
    public function __invoke(Province $province)
    {
        abort_unless($province->is_active, 404);

        $data = Cache::remember("open-data:{$province->id}", 60, function () use ($province) {
            $pid = $province->id;
            $cases = HelpRequest::inProvince($pid)->whereNull('duplicate_of_id')->where('status', '!=', 'merged');

            return [
                'province' => ['code' => $province->code, 'name' => $province->name_th, 'command_open' => (bool) $province->command_open],
                'generated_at' => now()->toIso8601String(),
                'license' => 'ข้อมูลสรุปเพื่อประโยชน์สาธารณะ อ้างอิงแหล่งที่มาเมื่อนำไปใช้',
                'help_requests' => [
                    'open' => (clone $cases)->whereIn('status', CaseOptions::OPEN)->count(),
                    'rescued_total' => (clone $cases)->where('status', 'rescued')->count(),
                    'people_rescued_total' => (int) (clone $cases)->sum('people_rescued'),
                    'last_24h' => (clone $cases)->where('created_at', '>=', now()->subDay())->count(),
                    'open_by_district' => (clone $cases)->whereIn('status', CaseOptions::OPEN)->with('district:id,name_th')->get(['id', 'district_id'])
                        ->groupBy(fn ($c) => $c->district?->name_th ?? 'ไม่ทราบ')->map->count(),
                ],
                'shelters' => Shelter::inProvince($pid)->where('is_public', true)->whereIn('status', ['open', 'full'])->get()
                    ->map(fn ($s) => ['name' => $s->name, 'status' => $s->status, 'lat' => $s->lat, 'lng' => $s->lng, 'capacity' => $s->capacity, 'occupancy' => $s->occupancy, 'phone' => $s->contact_phone])->values(),
                'evacuees_now' => Evacuee::inProvince($pid)->where('status', 'in')->count(),
                'stations' => WaterStation::inProvince($pid)->where('is_active', true)->where('is_public', true)->get()
                    ->map(fn ($s) => ['name' => $s->name, 'river' => $s->river, 'lat' => $s->lat, 'lng' => $s->lng, 'value' => $s->last_value, 'unit' => $s->unit, 'status' => $s->status, 'measured_at' => $s->last_at?->toIso8601String()])->values(),
                'water_reports_current' => WaterReport::inProvince($pid)->current()->get(['level'])->countBy('level'),
                'alerts' => Alert::inProvince($pid)->active()->where('is_public', true)->get()
                    ->map(fn ($a) => ['level' => $a->level, 'kind' => $a->kind, 'title' => $a->title, 'body' => $a->body, 'since' => $a->created_at->toIso8601String()])->values(),
            ];
        });

        return response()->json($data, 200, ['Access-Control-Allow-Origin' => '*', 'Cache-Control' => 'public, max-age=60'], JSON_UNESCAPED_UNICODE);
    }
}
