<?php

namespace App\Console\Commands;

use App\Models\Evacuee;
use App\Models\FieldAction;
use App\Models\HelpRequest;
use App\Models\WaterReport;
use App\Models\WaterReportVote;
use Illuminate\Console\Command;

/**
 * ทุกวัน: ลบข้อมูลส่วนบุคคลที่หมดความจำเป็น (PDPA) เก็บไว้เฉพาะตัวเลขสถิติ
 *  - เคสที่ปิดเกินกำหนด: ลบเบอร์ผู้แจ้ง/ผู้ติดต่อ ลิงก์ตำแหน่งดิบ รหัสอุปกรณ์
 *  - ผู้อพยพที่ออกจากศูนย์เกินกำหนด: ลบเบอร์
 *  - รายงานระดับน้ำเก่า: ลบเบอร์และรหัสอุปกรณ์
 *  - ข้อมูลระบบชั่วคราว (โหวต, คิวการกระทำภาคสนาม) เกิน 30 วัน
 */
class PruneData extends Command
{
    protected $signature = 'flood:prune-data {--days= : จำนวนวันที่เก็บ (ค่าเริ่มต้นจาก PII_RETENTION_DAYS)} {--dry : แสดงจำนวนอย่างเดียว ไม่ลบ}';

    protected $description = 'ลบข้อมูลส่วนบุคคลที่เกินระยะเวลาเก็บ';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('floodthai.pii_retention_days'));
        if ($days < 30) {
            $this->error('ต้องเก็บอย่างน้อย 30 วัน');

            return self::FAILURE;
        }
        $cut = now()->subDays($days);
        $dry = (bool) $this->option('dry');

        $cases = HelpRequest::withTrashed()->whereNotNull('closed_at')->where('closed_at', '<', $cut)
            ->where(fn ($q) => $q->whereNotNull('device_hash')->orWhereNotNull('contact_phone')->orWhere('phone_hash', '!=', str_repeat('0', 64)));
        $evac = Evacuee::where('status', '!=', 'in')->where('checked_out_at', '<', $cut)->whereNotNull('phone_hash');
        $reports = WaterReport::where('created_at', '<', $cut)->where(fn ($q) => $q->whereNotNull('reporter_phone')->orWhereNotNull('device_hash'));

        $this->info("เคส {$cases->count()} · ผู้อพยพ {$evac->count()} · รายงานน้ำ {$reports->count()} (เก่ากว่า {$days} วัน)");
        if ($dry) {
            return self::SUCCESS;
        }

        $cases->chunkById(200, function ($rows) {
            foreach ($rows as $c) {
                $c->forceFill([
                    'requester_phone' => '',
                    'phone_hash' => str_repeat('0', 64),
                    'contact_phone' => null,
                    'location_raw' => null,
                    'device_hash' => null,
                    'ip_hash' => null,
                ])->saveQuietly();
            }
        });
        $evac->chunkById(200, fn ($rows) => $rows->each(fn ($e) => $e->forceFill(['phone' => null, 'phone_hash' => null])->saveQuietly()));
        // คำร้องเยียวยาที่จบแล้ว (จ่าย/ไม่ผ่าน) เกินกำหนด: ลบรหัสอุปกรณ์ เบอร์เก็บไว้เป็นหลักฐานการจ่ายตามระเบียบ
        \App\Models\DamageClaim::whereIn('status', ['paid', 'rejected'])->where('updated_at', '<', $cut)->whereNotNull('device_hash')->update(['device_hash' => null]);
        $reports->chunkById(200, fn ($rows) => $rows->each(fn ($r) => $r->forceFill(['reporter_phone' => null, 'device_hash' => null, 'ip_hash' => null])->saveQuietly()));

        WaterReportVote::where('created_at', '<', now()->subDays(30))->delete();
        FieldAction::where('created_at', '<', now()->subDays(30))->delete();

        $this->info('เรียบร้อย');

        return self::SUCCESS;
    }
}
