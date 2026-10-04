<?php

namespace App\Support;

use App\Models\Province;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/** Diagnostic metadata only. Never turns a provider outage into a flood alert. */
class DataSourceHealth
{
    public const SOURCES = [
        'water' => ['name' => 'ThaiWater', 'description' => 'ระดับน้ำสถานีในจังหวัด', 'fetch_seconds' => 600, 'measure_seconds' => 21600, 'url' => 'https://www.thaiwater.net/'],
        'forecast' => ['name' => 'Open-Meteo', 'description' => 'พยากรณ์บริเวณตัวเมือง', 'fetch_seconds' => 1800, 'measure_seconds' => null, 'url' => 'https://open-meteo.com/'],
        'radar' => ['name' => 'RainViewer', 'description' => 'ภาพเรดาร์ย้อนหลัง', 'fetch_seconds' => 300, 'measure_seconds' => 1200, 'url' => 'https://www.rainviewer.com/'],
    ];

    private function key(string $source, ?int $provinceId): string
    {
        return 'data-health:v1:'.$source.':'.($source === 'radar' ? 'national' : $provinceId);
    }

    public function success(string $source, ?int $provinceId, string $fetchedAt, ?string $measuredAt = null, array $coverage = []): void
    {
        $key = $this->key($source, $provinceId);
        $old = Cache::get($key, []);
        Cache::put($key, [
            'checked_at' => now()->toIso8601String(), 'fetched_at' => $fetchedAt, 'measured_at' => $measuredAt,
            'coverage' => $coverage, 'failed_at' => isset($old['failed_at']) && Carbon::parse($old['failed_at'])->gt(Carbon::parse($fetchedAt)) ? $old['failed_at'] : null,
        ], now()->addDays(7));
    }

    public function failure(string $source, ?int $provinceId): void
    {
        $key = $this->key($source, $provinceId);
        Cache::put($key, array_replace(Cache::get($key, []), [
            'checked_at' => now()->toIso8601String(), 'failed_at' => now()->toIso8601String(),
        ]), now()->addDays(7));
    }

    public function snapshot(Province $province): array
    {
        $rows = [];
        foreach (self::SOURCES as $source => $definition) {
            $record = Cache::get($this->key($source, $province->id), []);
            $fetched = isset($record['fetched_at']) ? Carbon::parse($record['fetched_at']) : null;
            $measured = isset($record['measured_at']) ? Carbon::parse($record['measured_at']) : null;
            $failed = isset($record['failed_at']) ? Carbon::parse($record['failed_at']) : null;
            $state = 'unchecked';
            if ($fetched) {
                $state = $fetched->gt(now()) || $fetched->lt(now()->subSeconds($definition['fetch_seconds'])) ? 'delayed' : 'fresh';
                if ($definition['measure_seconds'] !== null && (! $measured || $measured->gt(now()->addMinutes(10)) || $measured->lt(now()->subSeconds($definition['measure_seconds'])))) {
                    $state = 'delayed';
                }
                if ($source === 'water') {
                    $coverage = $record['coverage'] ?? [];
                    if (($coverage['total'] ?? 0) === 0) {
                        $state = 'empty';
                    } elseif (($coverage['current'] ?? 0) < $coverage['total'] && $state === 'fresh') {
                        $state = 'partial';
                    }
                }
            }
            if ($failed && (! $fetched || $failed->gte($fetched))) {
                $state = 'unavailable';
            }
            $labels = ['unchecked' => 'ยังไม่ได้ตรวจ', 'fresh' => 'ข้อมูลล่าสุด', 'delayed' => 'ข้อมูลเก่า', 'partial' => 'บางสถานีข้อมูลเก่า', 'empty' => 'ยังไม่มีสถานีในจังหวัด', 'unavailable' => 'เชื่อมต่อต้นทางไม่สำเร็จ'];
            $rows[] = array_merge($definition, $record, ['source' => $source, 'state' => $state, 'label' => $labels[$state],
                'has_cached_data' => $fetched !== null]);
        }

        return ['province' => ['slug' => $province->slug, 'name' => $province->name_th], 'sources' => $rows,
            'issues' => count(array_filter($rows, fn ($r) => in_array($r['state'], ['delayed', 'partial', 'unavailable'], true))),
            'checked_at' => now()->toIso8601String()];
    }
}
