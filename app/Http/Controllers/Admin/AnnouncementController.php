<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\Announcement;
use App\Models\District;
use App\Support\AnnouncementService;
use App\Support\LineMessenger;
use App\Support\ReliefOptions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * ประกาศถึงประชาชน/เจ้าหน้าที่ บนเว็บ + LINE OA
 */
class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $province = $this->province();
        $draft = null;
        if ($request->filled('alert')) {
            $alert = Alert::inProvince($province->id)->find($request->integer('alert'));
            $draft = $alert ? AnnouncementService::draftFromAlert($alert) : null;
        }

        return view('admin.announcements.index', [
            'province' => $province,
            'items' => Announcement::inProvince($province->id)->with('creator:id,name')->latest()->paginate(20),
            'districts' => District::where('province_id', $province->id)->orderBy('sort')->get(['id', 'name_th']),
            'lineReady' => LineMessenger::configured($province->id),
            'draft' => $draft,
        ]);
    }

    public function store(Request $request, AnnouncementService $service)
    {
        $province = $this->province();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:4000'],
            'level' => ['required', Rule::in(array_keys(ReliefOptions::ANNOUNCE_LEVELS))],
            'audience' => ['required', Rule::in(['public', 'staff'])],
            'district_ids' => ['nullable', 'array'],
            'district_ids.*' => [Rule::exists('districts', 'id')->where('province_id', $province->id)],
            'hours' => ['nullable', 'integer', 'between:1,720'],
            'alert_id' => ['nullable', Rule::exists('alerts', 'id')->where('province_id', $province->id)],
        ], [], ['title' => 'หัวข้อ', 'body' => 'ข้อความ']);

        $a = Announcement::create([
            'province_id' => $province->id,
            'alert_id' => $data['alert_id'] ?? null,
            'title' => $data['title'],
            'body' => $data['body'],
            'level' => $data['level'],
            'audience' => $data['audience'],
            'district_ids' => $data['district_ids'] ?? null,
            'pinned' => $request->boolean('pinned'),
            'send_line' => $request->boolean('send_line'),
            'line_status' => $request->boolean('send_line') ? 'pending' : null,
            'expires_at' => ! empty($data['hours']) ? now()->addHours((int) $data['hours']) : null,
            'created_by' => $request->user()->id,
        ]);

        if ($request->input('action') === 'draft') {
            return $this->ok('บันทึกร่างแล้ว');
        }
        $service->publish($a);

        return $this->ok($this->resultMessage($a->fresh()));
    }

    public function publish(Announcement $announcement, AnnouncementService $service)
    {
        $this->authorizeProvince($announcement->province_id);
        $service->publish($announcement);

        return $this->ok($this->resultMessage($announcement->fresh()));
    }

    public function resend(Announcement $announcement, AnnouncementService $service)
    {
        $this->authorizeProvince($announcement->province_id);
        $announcement->update(['send_line' => true, 'line_status' => 'pending', 'line_error' => null]);
        \App\Jobs\SendAnnouncementLine::dispatch($announcement->id);

        return $this->ok($this->resultMessage($announcement->fresh()));
    }

    public function unpublish(Announcement $announcement)
    {
        $this->authorizeProvince($announcement->province_id);
        $announcement->update(['expires_at' => now()]);

        return $this->ok('ยกเลิกประกาศแล้ว');
    }

    public function destroy(Announcement $announcement)
    {
        $this->authorizeProvince($announcement->province_id);
        $announcement->delete();

        return $this->ok('ลบประกาศแล้ว');
    }

    protected function resultMessage(Announcement $a): string
    {
        return 'เผยแพร่แล้ว'.match ($a->line_status) {
            'sent' => ' · ส่ง LINE แล้ว'.($a->line_recipients ? " {$a->line_recipients} คน" : ''),
            'failed' => ' · ส่ง LINE ไม่สำเร็จ: '.$a->line_error,
            'skipped' => ' · ไม่ได้ส่ง LINE ('.$a->line_error.')',
            'pending' => ' · กำลังส่ง LINE เบื้องหลัง',
            default => '',
        };
    }
}
