<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HelpRequest;
use App\Models\Team;
use App\Support\CaseOptions;
use App\Support\DispatchService;
use Illuminate\Http\Request;
use RuntimeException;

class DispatchController extends Controller
{
    /** คอลัมน์ของกระดาน => [ป้าย, สถานะเคส, สี] */
    public const COLUMNS = [
        'queued' => ['รอทีม', ['queued'], 'warning'],
        'offered' => ['เสนองาน รอตอบ', ['offered'], 'warning'],
        'accepted' => ['รับงานแล้ว', ['accepted'], 'primary'],
        'en_route' => ['กำลังเดินทาง', ['en_route'], 'primary'],
        'on_site' => ['ถึงที่เกิดเหตุ', ['on_site'], 'primary'],
        'rescued' => ['ช่วยแล้ววันนี้', ['rescued'], 'success'],
    ];

    public function __construct(protected DispatchService $dispatch) {}

    public function index(Request $request)
    {
        $province = $this->province();
        $data = $this->boardData($province->id);

        if ($request->boolean('partial')) {
            return view('admin.dispatch._board', $data);
        }

        return view('admin.dispatch.index', $data + [
            'province' => $province,
            'teams' => Team::inProvince($province->id)->where('is_active', true)
                ->with(['vehicles', 'activeAssignments.helpRequest:id,code'])
                ->orderByRaw("case status when 'available' then 0 when 'busy' then 1 when 'resting' then 2 else 3 end")
                ->orderBy('name')->get(),
        ]);
    }

    /** ทีมที่แนะนำสำหรับเคสนี้ (HTML ใส่ใน modal) */
    public function suggest(HelpRequest $case)
    {
        $this->authorizeProvince($case->province_id);

        return view('admin.dispatch._suggest', [
            'case' => $case,
            'suggestions' => $this->dispatch->suggest($case, 8),
            'others' => Team::inProvince($case->province_id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'status']),
        ]);
    }

    public function assign(Request $request, HelpRequest $case)
    {
        $this->authorizeProvince($case->province_id);
        $data = $request->validate([
            'team_id' => ['required', 'exists:teams,id'],
            'mode' => ['required', 'in:offer,accepted'],
        ], [], ['team_id' => 'ทีม']);

        $team = Team::findOrFail($data['team_id']);
        try {
            $this->dispatch->offer($case, $team, $request->user(), $data['mode'] === 'accepted' ? 'phone' : 'dispatch', $data['mode'] === 'accepted');
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $data['mode'] === 'accepted'
            ? "{$case->code}: ทีม {$team->name} รับงานแล้ว"
            : "เสนอ {$case->code} ให้ทีม {$team->name} แล้ว รอทีมตอบรับ");
    }

    protected function boardData(int $provinceId): array
    {
        $cases = HelpRequest::inProvince($provinceId)
            ->where(fn ($q) => $q->whereIn('status', ['queued', 'offered', 'accepted', 'en_route', 'on_site'])
                ->orWhere(fn ($w) => $w->where('status', 'rescued')->where('closed_at', '>=', today())))
            ->with(['team:id,name', 'assignment', 'subdistrict:id,name_th,code', 'district:id,name_th'])
            ->urgentFirst()
            ->get();

        $columns = [];
        foreach (self::COLUMNS as $key => [$label, $statuses, $tone]) {
            $columns[$key] = [
                'label' => $label,
                'tone' => $tone,
                'cases' => $cases->whereIn('status', $statuses)->values(),
            ];
        }
        // ช่วยแล้ว: ล่าสุดอยู่บน
        $columns['rescued']['cases'] = $columns['rescued']['cases']->sortByDesc('closed_at')->values();

        return [
            'columns' => $columns,
            'triage' => HelpRequest::inProvince($provinceId)->whereIn('status', ['new', 'screening'])->count(),
        ];
    }
}
