<?php

namespace App\Console\Commands;

use App\Support\DispatchService;
use Illuminate\Console\Command;

/**
 * งานที่เสนอให้ทีมแล้วไม่มีใครตอบในเวลาที่ตั้งไว้ (ค่าตั้งต้น 10 นาที) คืนเคสเข้าคิวให้ศูนย์หาทีมใหม่
 */
class ExpireOffers extends Command
{
    protected $signature = 'flood:expire-offers';

    protected $description = 'คืนเคสเข้าคิวเมื่อทีมไม่ตอบรับงานในเวลาที่กำหนด';

    public function handle(DispatchService $dispatch): int
    {
        $this->info('หมดเวลา '.$dispatch->expireOffers().' งาน');

        return self::SUCCESS;
    }
}
