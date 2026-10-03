<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\TeamMember;
use App\Support\CaseOptions;
use App\Support\DispatchService;
use App\Support\TeamOptions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * การกระทำกับงานของทีม ใช้ร่วมทั้งหน้าสั่งการ (ศูนย์บันทึกแทนทีม) และหน้างานของทีม (ทีมกดเอง)
 */
class AssignmentController extends Controller
{
    public function __construct(protected DispatchService $dispatch) {}

    public function respond(Request $request, Assignment $assignment)
    {
        $this->authorizeAssignment($request, $assignment);
        $data = $request->validate([
            'accept' => ['required', 'boolean'],
            'reason' => ['nullable', Rule::in(array_keys(TeamOptions::DECLINE_REASONS))],
            'note' => ['nullable', 'string', 'max:300'],
        ]);

        return $this->run(fn () => $this->dispatch->respond($assignment, (bool) $data['accept'], $request->user(), $data['reason'] ?? null, $data['note'] ?? null),
            $data['accept'] ? 'รับงานแล้ว' : 'ปฏิเสธงานแล้ว เคสกลับเข้าคิว');
    }

    public function progress(Request $request, Assignment $assignment)
    {
        $this->authorizeAssignment($request, $assignment);
        $step = $request->validate(['step' => ['required', Rule::in(['en_route', 'on_site'])]])['step'];

        return $this->run(fn () => $this->dispatch->progress($assignment, $step, $request->user()), TeamOptions::ASSIGNMENT[$step][0]);
    }

    public function complete(Request $request, Assignment $assignment)
    {
        $this->authorizeAssignment($request, $assignment);
        $data = $request->validate([
            'outcome' => ['required', Rule::in(array_diff(array_keys(CaseOptions::OUTCOMES), ['duplicate', 'self_safe']))],
            'people_rescued' => ['nullable', 'integer', 'between:0,500'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [], ['outcome' => 'ผลการช่วยเหลือ']);

        return $this->run(fn () => $this->dispatch->complete($assignment, $request->user(), $data['outcome'], $data['people_rescued'] ?? null, $data['note'] ?? null),
            'ปิดงาน '.$assignment->helpRequest->code.' แล้ว');
    }

    /** ยกเลิกงาน ทำได้เฉพาะศูนย์ */
    public function cancel(Request $request, Assignment $assignment)
    {
        abort_unless($request->user()->can('dispatch.manage'), 403);
        $this->authorizeProvince($assignment->team->province_id);
        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:300']])['reason'] ?? null;

        return $this->run(fn () => $this->dispatch->cancel($assignment, $request->user(), $reason), 'ยกเลิกงานแล้ว เคสกลับเข้าคิว');
    }

    protected function run(callable $fn, string $ok)
    {
        try {
            $fn();
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $ok);
    }

    /** ศูนย์สั่งการของจังหวัด หรือสมาชิกทีมเจ้าของงาน */
    protected function authorizeAssignment(Request $request, Assignment $a): void
    {
        $user = $request->user();
        if ($user->can('dispatch.manage') && $user->canManageProvince($a->team->province_id)) {
            return;
        }
        abort_unless(
            $user->can('field.use') && TeamMember::where('team_id', $a->team_id)->where('user_id', $user->id)->exists(),
            403,
            'งานนี้ไม่ใช่ของทีมคุณ'
        );
    }
}
