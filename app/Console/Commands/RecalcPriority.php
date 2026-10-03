<?php

namespace App\Console\Commands;

use App\Models\HelpRequest;
use App\Support\CaseOptions;
use App\Support\HelpRequestService;
use Illuminate\Console\Command;

/**
 * คำนวณคะแนนความเร่งด่วนใหม่ทุก 5 นาที เพื่อให้เคสที่รอนานไม่จมอยู่ท้ายคิว
 */
class RecalcPriority extends Command
{
    protected $signature = 'flood:recalc-priority';

    protected $description = 'คำนวณคะแนนความเร่งด่วนของเคสที่ยังรอทีมใหม่';

    public function handle(HelpRequestService $service): int
    {
        $changed = 0;
        HelpRequest::whereIn('status', CaseOptions::WAITING)
            ->chunkById(200, function ($cases) use ($service, &$changed) {
                foreach ($cases as $case) {
                    $changed += (int) $service->recalc($case);
                }
            });

        $this->info("ปรับคะแนน $changed เคส");

        return self::SUCCESS;
    }
}
