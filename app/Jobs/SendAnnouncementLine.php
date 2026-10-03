<?php

namespace App\Jobs;

use App\Models\Announcement;
use App\Support\AnnouncementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** ส่งประกาศทาง LINE เบื้องหลัง หน้าจอเจ้าหน้าที่ไม่ต้องรอ LINE ตอบ */
class SendAnnouncementLine implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public int $announcementId) {}

    public function handle(AnnouncementService $service): void
    {
        $a = Announcement::find($this->announcementId);
        if ($a && $a->send_line && $a->line_status !== 'sent') {
            $service->sendLine($a);
        }
    }
}
