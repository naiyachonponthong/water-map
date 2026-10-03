<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class HostingCron extends Command
{
    protected $signature = 'flood:cron';

    protected $description = 'รันงานตามเวลาและคิวเป็นรอบสำหรับโฮสต์ทั่วไป ตั้ง Cron ทุกนาที';

    public function handle(): int
    {
        if (PHP_SAPI !== 'cli' || ! function_exists('proc_open')) {
            $this->error('ต้องใช้ PHP CLI ที่เปิด proc_open สำหรับ scheduler');

            return self::FAILURE;
        }
        $dir = storage_path('app/hosting');
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $lock = fopen($dir.'/cron-lock.php', 'c+');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            $this->comment('รอบก่อนยังทำงานอยู่ ข้ามเพื่อไม่ให้งานซ้อนกัน');

            return self::SUCCESS;
        }
        try {
            $scheduler = $this->call('schedule:run');
            $queue = $this->call('queue:work', ['--stop-when-empty' => true, '--max-time' => 45,
                '--timeout' => 40, '--tries' => 3, '--sleep' => 1]);

            return $scheduler === 0 && $queue === 0 ? self::SUCCESS : self::FAILURE;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
