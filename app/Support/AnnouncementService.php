<?php

namespace App\Support;

use App\Models\Alert;
use App\Models\Announcement;
use App\Models\Province;
use App\Models\User;
use Throwable;

/**
 * ประกาศถึงประชาชน/เจ้าหน้าที่ บนเว็บ และส่งทาง LINE OA
 */
class AnnouncementService
{
    public function __construct(protected LineMessenger $line) {}

    public function publish(Announcement $a): void
    {
        if (! $a->published_at) {
            $a->published_at = now();
        }
        $a->save();
        if ($a->send_line && $a->line_status !== 'sent') {
            $a->update(['line_status' => 'pending', 'line_error' => null]);
            \App\Jobs\SendAnnouncementLine::dispatch($a->id);
        }
        Live::signal($a->province_id, 'announcement.published', ['id' => $a->id, 'title' => $a->title, 'level' => $a->level]);
    }

    public function sendLine(Announcement $a): void
    {
        if (! LineMessenger::configured($a->province_id)) {
            $a->update(['line_status' => 'skipped', 'line_error' => 'ยังไม่ได้ตั้งค่า LINE OA']);

            return;
        }
        $province = Province::find($a->province_id);
        $msg = LineMessenger::text($this->lineText($a, $province));
        try {
            if ($a->audience === 'staff') {
                $ids = User::where('province_id', $a->province_id)->where('status', 'active')->whereNotNull('line_user_id')->pluck('line_user_id')->all();
                $n = $ids ? $this->line->multicast($a->province_id, $ids, [$msg]) : 0;
            } else {
                $this->line->broadcast($a->province_id, [$msg]);
                $n = null;
            }
            $a->update(['line_status' => 'sent', 'line_error' => null, 'line_sent_at' => now(), 'line_recipients' => $n]);
        } catch (Throwable $e) {
            $a->update(['line_status' => 'failed', 'line_error' => mb_substr($e->getMessage(), 0, 250)]);
        }
    }

    public function lineText(Announcement $a, ?Province $province): string
    {
        $head = match ($a->level) {
            'urgent' => '🚨 ด่วน: ',
            'warning' => '⚠️ ',
            default => '📢 ',
        };
        $lines = [$head.$a->title, '', $a->body];
        if ($a->district_ids) {
            $lines[] = '';
            $lines[] = 'พื้นที่: '.$a->districtNames();
        }
        if ($province && $a->audience === 'public') {
            $lines[] = '';
            $lines[] = 'ขอความช่วยเหลือ / ดูแผนที่น้ำ: '.route('public.province', $province);
            $lines[] = 'สายด่วน '.Settings::get('hotline', $province->id);
        }

        return implode("\n", $lines);
    }

    /** ร่างประกาศจากประกาศเตือนภัย */
    public static function draftFromAlert(Alert $alert): array
    {
        return [
            'title' => $alert->title,
            'body' => $alert->body ?? '',
            'level' => $alert->level === 'critical' ? 'urgent' : 'warning',
            'district_ids' => $alert->district_ids,
            'alert_id' => $alert->id,
        ];
    }
}
