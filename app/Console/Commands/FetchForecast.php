<?php

namespace App\Console\Commands;

use App\Models\Province;
use App\Support\ForecastService;
use Illuminate\Console\Command;
use Throwable;

/**
 * ทุกชั่วโมง: ดึงพยากรณ์ฝน 7 วัน (Open-Meteo) และเตือนฝนหนักล่วงหน้า (เฉพาะจังหวัดที่เปิดศูนย์)
 */
class FetchForecast extends Command
{
    protected $signature = 'flood:fetch-forecast {--province= : slug จังหวัด}';

    protected $description = 'ดึงพยากรณ์ฝนและเตือนฝนหนักล่วงหน้า';

    public function handle(ForecastService $service): int
    {
        $provinces = $this->option('province')
            ? Province::where('slug', $this->option('province'))->get()
            : Province::where('command_open', true)->get();

        foreach ($provinces as $p) {
            try {
                $n = $service->fetch($p);
                $a = $service->evaluate($p);
                $this->info("{$p->name_th}: พยากรณ์ {$n} จุด ประกาศเตือนฝน {$a} วัน");
            } catch (Throwable $e) {
                $this->error("{$p->name_th}: ".$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
