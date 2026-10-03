<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

class HostingStatus
{
    public function beat(string $name): void
    {
        $dir = storage_path('app/hosting');
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        file_put_contents($dir.'/'.$name.'.php', "<?php http_response_code(404); exit; ?>\n".json_encode(['at' => now()->timestamp]), LOCK_EX);
    }

    public function lastBeat(string $name): ?int
    {
        $file = storage_path('app/hosting/'.$name.'.php');
        if (! is_file($file)) {
            return null;
        }

        return json_decode(explode("\n", file_get_contents($file), 2)[1] ?? '', true)['at'] ?? null;
    }

    public function checks(): array
    {
        $checks = [];
        $add = function ($label, $ok, $help) use (&$checks) {
            $checks[] = compact('label', 'ok', 'help');
        };
        $add('PHP พร้อม', version_compare(PHP_VERSION, '8.3', '>='), 'PHP เว็บไซต์และ PHP CLI ต้องเป็น 8.3 ขึ้นไป');
        $add('ปิดรายละเอียดข้อผิดพลาด', config('app.debug') === false, 'ตั้ง APP_DEBUG=false ก่อนเปิดให้ประชาชนใช้');
        $add('URL ใช้ HTTPS', str_starts_with(config('app.url'), 'https://'), 'ตั้ง APP_URL เป็น HTTPS และเปิด SSL');
        $add('คุกกี้เข้าสู่ระบบปลอดภัย', (bool) config('session.secure'), 'ตั้ง SESSION_SECURE_COOKIE=true บนเว็บไซต์จริง');
        $add('มีคีย์เข้ารหัส', ! empty(config('app.key')), 'เก็บ APP_KEY สำรองคู่กับฐานข้อมูล ห้ามสร้างใหม่ทับระบบเดิม');
        $add('โฟลเดอร์เขียนได้', is_writable(storage_path()) && is_writable(base_path('bootstrap/cache')), 'ให้ PHP เขียน storage และ bootstrap/cache ได้');
        $add('พื้นที่รูปพร้อม', config('floodthai.public_uploads_through_app') || is_dir(public_path('storage')), 'ชุดติดตั้งใหม่ส่งรูปผ่านแอปได้ หากใช้ symbolic link ต้องสร้าง storage:link');
        $failed = null;
        try {
            DB::connection()->getPdo();
            $failed = DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count();
            $add('เชื่อมต่อฐานข้อมูลและตารางคิวได้', true, 'สำรองข้อมูลสม่ำเสมอ');
        } catch (Throwable $e) {
            $add('เชื่อมต่อฐานข้อมูลและตารางคิวได้', false, 'ตรวจฐานข้อมูลและรัน migration ให้ครบ');
        }
        foreach (['scheduler' => 'งานตามเวลา', 'queue' => 'ตัวประมวลผลงานคิว'] as $name => $label) {
            $last = $this->lastBeat($name);
            $add($label.'ทำงานล่าสุด', $last !== null && $last <= now()->timestamp && now()->timestamp - $last <= 180,
                $last ? 'ทำงานล่าสุด '.date('Y-m-d H:i:s', $last).' · หากเกิน 3 นาที ตรวจ Cron/worker' : 'ยังไม่มีหลักฐานการทำงาน ตั้ง Cron/worker แล้วรอไม่เกิน 2 นาที');
        }
        $add('ไม่มีงานคิวล้มเหลวใน 24 ชั่วโมง', $failed === 0, $failed === null ? 'ยังตรวจจำนวนงานไม่ได้' : "มี {$failed} งาน ตรวจ failed jobs และแก้สาเหตุก่อนลองใหม่");

        return $checks;
    }
}
