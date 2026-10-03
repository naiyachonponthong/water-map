<?php

namespace App\Jobs;

use App\Models\Province;
use App\Support\RiskEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * ตรวจจุดเสี่ยงของจังหวัดแบบรวบ: รายงานน้ำเข้ามาพร้อมกันหลายร้อยรายการ ตรวจจริงครั้งเดียวต่อรอบ
 * (ระหว่างรอคิว job ซ้ำของจังหวัดเดียวกันจะถูกทิ้ง พอเริ่มทำงานแล้วรายงานใหม่จะต่อคิวรอบถัดไปได้)
 */
class EvaluateProvinceRisks implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 2;

    public int $uniqueFor = 120;

    public function __construct(public int $provinceId) {}

    public function uniqueId(): string
    {
        return (string) $this->provinceId;
    }

    public function handle(RiskEngine $engine): void
    {
        if ($province = Province::find($this->provinceId)) {
            $engine->evaluate($province);
        }
    }

    /** เรียกจากที่ไหนก็ได้ที่ข้อมูลระดับน้ำเปลี่ยน */
    public static function soon(int $provinceId, int $seconds = 15): void
    {
        static::dispatch($provinceId)->delay(now()->addSeconds($seconds));
    }
}
