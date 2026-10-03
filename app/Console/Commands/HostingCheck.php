<?php

namespace App\Console\Commands;

use App\Support\HostingStatus;
use Illuminate\Console\Command;

class HostingCheck extends Command
{
    protected $signature = 'flood:hosting-check';

    protected $description = 'ตรวจความพร้อมใช้งานจริง รวมหลักฐานงานตามเวลาและคิว';

    public function handle(HostingStatus $status): int
    {
        $checks = $status->checks();
        $this->table(['รายการ', 'สถานะ', 'คำแนะนำ'], array_map(fn ($c) => [$c['label'], $c['ok'] ? 'ผ่าน' : 'ยังไม่ผ่าน', $c['help']], $checks));
        $this->comment('Heartbeat ยืนยันว่าตัวประมวลผลทำงาน ไม่รับรองว่าข้อมูล API ภายนอกทุกแหล่งเป็นปัจจุบัน');

        return array_filter($checks, fn ($c) => ! $c['ok']) ? self::FAILURE : self::SUCCESS;
    }
}
