<?php

namespace App\Support;

use App\Models\District;
use App\Models\Province;
use App\Models\RainForecast;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * พยากรณ์ฝน 7 วันจาก Open-Meteo (ฟรี ไม่ต้องใช้คีย์) รายอำเภอ และเตือนฝนหนักล่วงหน้า 3 วัน
 */
class ForecastService
{
    public const URL = 'https://api.open-meteo.com/v1/forecast';

    public function __construct(protected AlertService $alerts) {}

    /** @return int จำนวนจุดที่ดึงได้ */
    public function fetch(Province $province): int
    {
        // จุดพยากรณ์: กลางจังหวัด (district_id = null) + กลางแต่ละอำเภอ
        $points = collect([[null, $province->center_lat, $province->center_lng]])
            ->concat(District::where('province_id', $province->id)->whereNotNull('center_lat')->get(['id', 'center_lat', 'center_lng'])
                ->map(fn ($d) => [$d->id, $d->center_lat, $d->center_lng]))
            ->filter(fn ($p) => $p[1] !== null && $p[2] !== null)->values();
        if ($points->isEmpty()) {
            return 0;
        }

        $done = 0;
        foreach ($points->chunk(50) as $chunk) {
            $chunk = $chunk->values();
            $res = Http::timeout(15)->acceptJson()->get(self::URL, [
                'latitude' => $chunk->pluck(1)->map(fn ($v) => round($v, 4))->implode(','),
                'longitude' => $chunk->pluck(2)->map(fn ($v) => round($v, 4))->implode(','),
                'daily' => 'precipitation_sum,precipitation_probability_max,temperature_2m_max',
                'timezone' => 'Asia/Bangkok',
                'forecast_days' => 7,
            ]);
            if (! $res->successful()) {
                throw new \RuntimeException('Open-Meteo HTTP '.$res->status());
            }
            $json = $res->json();
            // จุดเดียวได้ object, หลายจุดได้ array
            $list = array_is_list($json) ? $json : [$json];
            foreach ($list as $i => $loc) {
                $districtId = $chunk[$i][0] ?? null;
                $daily = $loc['daily'] ?? [];
                foreach ($daily['time'] ?? [] as $d => $date) {
                    RainForecast::updateOrCreate(
                        ['province_id' => $province->id, 'district_id' => $districtId, 'date' => $date],
                        [
                            'rain_mm' => (float) ($daily['precipitation_sum'][$d] ?? 0),
                            'rain_prob' => isset($daily['precipitation_probability_max'][$d]) ? (int) $daily['precipitation_probability_max'][$d] : null,
                            'temp_max' => $daily['temperature_2m_max'][$d] ?? null,
                            'source' => 'open-meteo',
                            'fetched_at' => now(),
                        ],
                    );
                }
                $done++;
            }
        }

        RainForecast::where('province_id', $province->id)->where('date', '<', today()->subDays(3))->delete();

        return $done;
    }

    /** เตือนฝนหนัก 3 วันข้างหน้า: 1 ประกาศต่อวัน รวมอำเภอที่เข้าเกณฑ์ */
    public function evaluate(Province $province): int
    {
        $warn = (float) Settings::get('rain_warning_mm', $province->id);
        $crit = (float) Settings::get('rain_critical_mm', $province->id);
        $raised = 0;

        $rows = RainForecast::where('province_id', $province->id)
            ->whereBetween('date', [today(), today()->addDays(2)])
            ->with('district:id,name_th')->get()->groupBy(fn ($r) => $r->date->toDateString());

        for ($i = 0; $i <= 2; $i++) {
            $date = today()->addDays($i)->toDateString();
            $key = 'rain:'.$date;
            $day = $rows->get($date, collect());
            $hit = $day->filter(fn ($r) => $r->rain_mm >= $warn);
            if ($hit->isEmpty()) {
                $this->alerts->resolveKey($province->id, $key);

                continue;
            }
            $max = $hit->max('rain_mm');
            $level = $max >= $crit ? 'critical' : 'warning';
            $areas = $hit->filter(fn ($r) => $r->district)->sortByDesc('rain_mm')->map(fn ($r) => 'อ.'.$r->district->name_th.' '.number_format($r->rain_mm, 0).' มม.');
            $when = $i === 0 ? 'วันนี้' : ($i === 1 ? 'พรุ่งนี้' : ThaiDate::short($date));

            $this->alerts->raise($province->id, $key, 'rain', $level,
                'พยากรณ์'.($level === 'critical' ? 'ฝนหนักมาก' : 'ฝนหนัก').$when,
                ($areas->isNotEmpty() ? $areas->implode(', ') : 'ทั่วจังหวัด สูงสุด '.number_format($max, 0).' มม.').'. เตรียมพร้อมรับน้ำหลาก',
                ['district_ids' => $hit->pluck('district_id')->filter()->values()->all() ?: null, 'expires_at' => Carbon::parse($date)->endOfDay()],
            );
            $raised++;
        }

        return $raised;
    }

    /** พยากรณ์ 7 วันระดับจังหวัด (ค่าสูงสุดในทุกจุด) สำหรับแสดงผล */
    public static function week(Province $province): \Illuminate\Support\Collection
    {
        return RainForecast::where('province_id', $province->id)->whereBetween('date', [today(), today()->addDays(6)])
            ->get()->groupBy(fn ($r) => $r->date->toDateString())
            ->map(fn ($g, $date) => [
                'date' => Carbon::parse($date),
                'rain_mm' => (float) $g->max('rain_mm'),
                'rain_prob' => $g->max('rain_prob'),
                'temp_max' => $g->firstWhere('district_id', null)?->temp_max ?? $g->max('temp_max'),
                'category' => StationOptions::rain((float) $g->max('rain_mm')),
            ])->sortKeys()->values();
    }
}
