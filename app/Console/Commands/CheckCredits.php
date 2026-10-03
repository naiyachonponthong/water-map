<?php

namespace App\Console\Commands;

use App\Support\Attribution;
use Illuminate\Console\Command;
use RuntimeException;

class CheckCredits extends Command
{
    protected $signature = 'flood:credits-check';

    protected $description = 'ตรวจเครดิตผู้จัดทำในแม่แบบกลางและไฟล์แจกจ่าย โดยไม่แก้ข้อมูล';

    public function handle(): int
    {
        try {
            Attribution::verifyRelease(base_path());
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info('Creator credit verified: '.Attribution::AUTHOR);

        return self::SUCCESS;
    }
}
