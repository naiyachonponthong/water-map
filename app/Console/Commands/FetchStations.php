<?php

namespace App\Console\Commands;

use App\Models\Province;
use App\Models\WaterStation;
use App\Support\StationService;
use Illuminate\Console\Command;

/**
 * ทุก 10 นาที: ดึงค่าสถานีที่ตั้งเป็น JSON API และตรวจสถานีที่ขาดการติดต่อ
 */
class FetchStations extends Command
{
    protected $signature = 'flood:fetch-stations {--province= : slug จังหวัด}';

    protected $description = 'ดึงระดับน้ำจากสถานีที่ตั้งค่า API ไว้';

    public function handle(StationService $service): int
    {
        $provinces = $this->option('province')
            ? Province::where('slug', $this->option('province'))->get()
            : Province::whereIn('id', WaterStation::where('is_active', true)->select('province_id'))->get();

        foreach ($provinces as $p) {
            $ok = $fail = 0;
            WaterStation::inProvince($p->id)->where('is_active', true)->where('fetch_mode', 'json')->get()
                ->each(function ($s) use ($service, &$ok, &$fail) {
                    $service->fetch($s) ? $ok++ : $fail++;
                });
            $off = $service->markOffline($p);
            $this->info("{$p->name_th}: ดึงได้ {$ok} ไม่สำเร็จ {$fail} ขาดการติดต่อ {$off}");
        }

        return self::SUCCESS;
    }
}
