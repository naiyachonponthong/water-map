<?php

namespace App\Support;

use App\Models\Alert;
use App\Models\Assignment;
use App\Models\District;
use App\Models\Evacuee;
use App\Models\HelpRequest;
use App\Models\Province;
use App\Models\Shelter;
use App\Models\SupplyMovement;
use App\Models\Team;
use App\Models\WaterReport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * ตัวเลขสรุปสำหรับผู้บริหาร / รายงานสถานการณ์ (SitRep)
 * คำนวณใน PHP เพื่อให้ได้ค่ากลาง (median) และใช้ได้ทั้ง MySQL และ SQLite
 */
class ReportService
{
    public function build(Province $province, Carbon $from, Carbon $to, ?int $districtId = null): array
    {
        $pid = $province->id;
        $cases = HelpRequest::inProvince($pid)
            ->whereBetween('created_at', [$from, $to])
            ->whereNull('duplicate_of_id')
            ->where('status', '!=', 'merged')
            ->when($districtId, fn ($q) => $q->where('district_id', $districtId))
            ->get(['id', 'district_id', 'status', 'priority', 'source', 'outcome', 'people_count', 'people_rescued', 'vulnerable', 'created_at', 'closed_at', 'team_id']);

        $ids = $cases->pluck('id');
        $assignments = Assignment::whereIn('help_request_id', $ids)->get(['help_request_id', 'team_id', 'status', 'offered_at', 'responded_at', 'arrived_at', 'done_at', 'people_rescued']);
        $byCase = $assignments->groupBy('help_request_id');

        // เวลาตอบสนองต่อเคส (นาที): รับงาน / ถึงที่เกิดเหตุ / ช่วยเสร็จ
        $accept = $arrive = $finish = [];
        foreach ($cases as $c) {
            $as = $byCase->get($c->id, collect());
            $acc = $as->whereNotIn('status', ['declined', 'expired', 'offered'])->pluck('responded_at')->filter()->min();
            $arr = $as->pluck('arrived_at')->filter()->min();
            $done = $as->where('status', 'done')->pluck('done_at')->filter()->min();
            if ($acc) {
                $accept[] = $c->created_at->diffInMinutes($acc, true);
            }
            if ($arr) {
                $arrive[] = $c->created_at->diffInMinutes($arr, true);
            }
            if ($done) {
                $finish[] = $c->created_at->diffInMinutes($done, true);
            }
        }

        $districts = District::where('province_id', $pid)->orderBy('sort')->get(['id', 'name_th'])->keyBy('id');
        $real = $cases->whereNotIn('outcome', ['fake', 'duplicate']);

        return [
            'from' => $from,
            'to' => $to,
            'cases' => [
                'total' => $cases->count(),
                'open' => $cases->whereIn('status', CaseOptions::OPEN)->count(),
                'rescued' => $cases->where('status', 'rescued')->count(),
                'critical' => $cases->where('priority', 'critical')->count(),
                'people_reported' => (int) $real->sum('people_count'),
                'people_rescued' => (int) $cases->sum('people_rescued'),
                'vulnerable' => $real->filter(fn ($c) => ! empty(array_diff($c->vulnerable ?? [], ['pets'])))->count(),
                'fake' => $cases->where('outcome', 'fake')->count(),
                'by_status' => $cases->countBy('status')->sortDesc(),
                'by_source' => $cases->countBy('source')->sortDesc(),
                'by_outcome' => $cases->whereNotNull('outcome')->countBy('outcome')->sortDesc(),
                'by_priority' => $cases->countBy('priority'),
            ],
            'response' => [
                'accept' => $this->stats($accept),
                'arrive' => $this->stats($arrive),
                'finish' => $this->stats($finish),
            ],
            'districts' => $cases->groupBy('district_id')->map(fn ($g, $did) => [
                'name' => $did ? ('อ.'.($districts[$did]->name_th ?? '-')) : 'ไม่ทราบอำเภอ',
                'total' => $g->count(),
                'open' => $g->whereIn('status', CaseOptions::OPEN)->count(),
                'critical' => $g->where('priority', 'critical')->count(),
                'rescued' => (int) $g->sum('people_rescued'),
            ])->sortByDesc('total')->values(),
            'teams' => $this->teams($pid, $assignments),
            'daily' => $this->daily($cases, $from, $to),
            'shelters' => [
                'open' => Shelter::inProvince($pid)->whereIn('status', ['open', 'full'])->count(),
                'people_now' => Evacuee::inProvince($pid)->where('status', 'in')->count(),
                'registered' => Evacuee::inProvince($pid)->whereBetween('checked_in_at', [$from, $to])->count(),
                'list' => Shelter::inProvince($pid)->whereIn('status', ['open', 'full'])->orderByDesc('occupancy')->limit(10)->get(['name', 'capacity', 'occupancy', 'status']),
            ],
            'supplies' => SupplyMovement::where('province_id', $pid)->whereBetween('created_at', [$from, $to])
                ->whereIn('kind', ['in', 'out'])->with('item:id,name,unit')->get()
                ->groupBy('supply_item_id')->map(fn ($g) => [
                    'name' => $g->first()->item?->name, 'unit' => $g->first()->item?->unit,
                    'in' => (int) $g->where('kind', 'in')->sum('qty'), 'out' => (int) -$g->where('kind', 'out')->sum('qty'),
                ])->sortByDesc('in')->values()->take(15),
            'water' => [
                'reports' => WaterReport::inProvince($pid)->whereBetween('created_at', [$from, $to])->count(),
                'deep' => WaterReport::inProvince($pid)->whereBetween('created_at', [$from, $to])->where('level', '>=', 4)->count(),
            ],
            'alerts' => Alert::inProvince($pid)->where('created_at', '<=', $to)
                ->where(fn ($q) => $q->whereNull('resolved_at')->orWhere('resolved_at', '>=', $from))
                ->orderByRaw("case level when 'critical' then 0 when 'warning' then 1 else 2 end")->latest()->limit(15)->get(),
        ];
    }

    /** ค่าเฉลี่ย ค่ากลาง และ 90 เปอร์เซ็นไทล์ (นาที) */
    public function stats(array $values): array
    {
        sort($values);
        $n = count($values);
        if (! $n) {
            return ['n' => 0, 'avg' => null, 'median' => null, 'p90' => null];
        }
        $pick = fn (float $q) => $values[(int) min($n - 1, max(0, ceil($q * $n) - 1))];

        return ['n' => $n, 'avg' => (int) round(array_sum($values) / $n), 'median' => $pick(.5), 'p90' => $pick(.9)];
    }

    protected function teams(int $pid, Collection $assignments): Collection
    {
        $names = Team::where('province_id', $pid)->pluck('name', 'id');

        return $assignments->groupBy('team_id')->map(function ($g, $tid) use ($names) {
            $arrive = $g->filter(fn ($a) => $a->arrived_at && $a->responded_at)->map(fn ($a) => $a->responded_at->diffInMinutes($a->arrived_at, true))->values()->all();

            return [
                'name' => $names[$tid] ?? '-',
                'offered' => $g->count(),
                'declined' => $g->whereIn('status', ['declined', 'expired'])->count(),
                'done' => $g->where('status', 'done')->count(),
                'people' => (int) $g->sum('people_rescued'),
                'travel' => $this->stats($arrive)['median'],
            ];
        })->sortByDesc('done')->values();
    }

    protected function daily(Collection $cases, Carbon $from, Carbon $to): array
    {
        $days = [];
        $start = $from->copy()->timezone(config('app.timezone'))->startOfDay();
        $end = $to->copy()->timezone(config('app.timezone'))->startOfDay();
        for ($d = $start->copy(); $d->lte($end) && count($days) < 120; $d->addDay()) {
            $days[$d->toDateString()] = ['new' => 0, 'closed' => 0];
        }
        foreach ($cases as $c) {
            $k = $c->created_at->timezone(config('app.timezone'))->toDateString();
            if (isset($days[$k])) {
                $days[$k]['new']++;
            }
            if ($c->closed_at) {
                $k2 = $c->closed_at->timezone(config('app.timezone'))->toDateString();
                if (isset($days[$k2])) {
                    $days[$k2]['closed']++;
                }
            }
        }

        return $days;
    }

    /** ช่วงเวลาเริ่มต้น: ตั้งแต่เปิดศูนย์ หรือ 7 วันล่าสุด */
    public static function defaultRange(Province $province): array
    {
        $from = $province->command_opened_at && $province->command_opened_at->gt(now()->subDays(90))
            ? $province->command_opened_at->copy()->startOfDay()
            : now()->subDays(6)->startOfDay();

        return [$from, now()];
    }
}
