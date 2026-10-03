<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HelpRequest;
use App\Models\Team;
use App\Support\CaseOptions;
use App\Support\DispatchService;
use App\Support\Geo;
use App\Support\Settings;
use App\Support\TeamOptions;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * หน้างานของทีม (หัวหน้าทีม/สมาชิก): รับ/ปฏิเสธงาน อัปเดตความคืบหน้า หยิบเคสใกล้ตัว ส่งพิกัด
 * แอปภาคสนามเต็มรูปแบบ (ออฟไลน์, หลายเคสต่อรอบ) จะต่อยอดจากหน้านี้
 */
class MyTeamController extends Controller
{
    public function __construct(protected DispatchService $dispatch) {}

    public function index(Request $request)
    {
        $team = $this->myTeam($request);
        if (! $team) {
            return view('admin.my-team.none');
        }

        $team->load(['vehicles', 'members']);
        $assignments = $team->assignments()->whereIn('status', TeamOptions::ACTIVE)
            ->with('helpRequest.subdistrict', 'helpRequest.district')->orderBy('offered_at')->get();

        $selfAssign = (bool) Settings::get('team_self_assign', $team->province_id);
        $nearby = collect();
        if ($selfAssign && ($pos = $team->position())) {
            $nearby = HelpRequest::inProvince($team->province_id)->where('status', 'queued')
                ->with('subdistrict', 'district')->get()
                ->map(fn ($c) => tap($c, fn ($c) => $c->distance_m = (int) Geo::distance($pos[0], $pos[1], $c->lat, $c->lng)))
                ->sortBy(fn ($c) => [-$c->priority_score, $c->distance_m])
                ->take(10);
        }

        return view('admin.my-team.index', [
            'team' => $team,
            'offers' => $assignments->where('status', 'offered'),
            'active' => $assignments->where('status', '!=', 'offered'),
            'nearby' => $nearby,
            'selfAssign' => $selfAssign,
            'doneToday' => $team->assignments()->where('status', 'done')->where('done_at', '>=', today())->with('helpRequest:id,code,requester_name')->latest('done_at')->get(),
        ]);
    }

    /** ทีมหยิบเคสในคิวเอง (เมื่อศูนย์เปิดให้) */
    public function pick(Request $request, HelpRequest $case)
    {
        $team = $this->myTeam($request);
        abort_unless($team && $team->province_id === $case->province_id, 403);
        abort_unless(Settings::get('team_self_assign', $team->province_id), 403, 'ศูนย์ยังไม่เปิดให้ทีมหยิบเคสเอง');

        if ($case->status !== 'queued') {
            return back()->with('error', "{$case->code} มีทีมอื่นรับไปแล้ว");
        }
        try {
            $this->dispatch->offer($case, $team, $request->user(), 'self', true);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "รับ {$case->code} แล้ว กดเริ่มเดินทางเมื่อออกจากฐาน");
    }

    /** ส่งพิกัดของทีม (เบราว์เซอร์ส่งทุก 60 วินาทีขณะเปิดหน้านี้และมีงาน) */
    public function location(Request $request)
    {
        $team = $this->myTeam($request);
        abort_unless($team, 403);
        $data = $request->validate(['lat' => ['required', 'numeric', 'between:5,21'], 'lng' => ['required', 'numeric', 'between:97,106']]);
        $team->forceFill(['last_lat' => $data['lat'], 'last_lng' => $data['lng'], 'last_seen_at' => now()])->saveQuietly();
        \App\Support\Live::teamChanged($team);

        return response()->json(['ok' => true]);
    }

    protected function myTeam(Request $request): ?Team
    {
        return $request->user()->team()->first();
    }
}
