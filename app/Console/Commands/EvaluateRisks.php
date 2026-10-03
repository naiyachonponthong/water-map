<?php

namespace App\Console\Commands;

use App\Models\Province;
use App\Support\RiskEngine;
use Illuminate\Console\Command;

/**
 * ทุก 5 นาที: ตรวจจุดเสี่ยงที่น้ำถึง และเปิดเคสตรวจเยี่ยมเชิงรุกให้ครัวเรือนเปราะบาง (เฉพาะจังหวัดที่เปิดศูนย์)
 */
class EvaluateRisks extends Command
{
    protected $signature = 'flood:evaluate-risks {--province= : slug จังหวัด}';

    protected $description = 'ตรวจจุดเสี่ยงและครัวเรือนเปราะบางจากระดับน้ำล่าสุด';

    public function handle(RiskEngine $engine): int
    {
        $provinces = $this->option('province')
            ? Province::where('slug', $this->option('province'))->get()
            : Province::where('command_open', true)->get();

        foreach ($provinces as $p) {
            $r = $engine->evaluate($p);
            $this->info("{$p->name_th}: ถูกคุกคามใหม่ {$r['threatened']} กลับปกติ {$r['cleared']} เปิดเคสเชิงรุก {$r['cases']}");
        }

        return self::SUCCESS;
    }
}
