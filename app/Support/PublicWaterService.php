<?php

namespace App\Support;

use App\Models\Province;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/** Public display only; never changes operational stations or alert thresholds. */
class PublicWaterService
{
    public const ENDPOINT = 'https://api-v3.thaiwater.net/api/v1/thaiwater30/public/waterlevel_load';

    public const LEVELS = [
        1 => ['label' => 'น้ำน้อยวิกฤติ', 'color' => '#a76317'],
        2 => ['label' => 'น้ำน้อย', 'color' => '#b38a12'],
        3 => ['label' => 'น้ำปกติ', 'color' => '#16875c'],
        4 => ['label' => 'น้ำมาก', 'color' => '#2676d6'],
        5 => ['label' => 'น้ำล้นตลิ่ง', 'color' => '#df4058'],
    ];

    public function stations(Province $province): array
    {
        $key = 'public-thaiwater:v1';
        $entry = Cache::get($key);
        $stale = false;
        if (! $entry || $entry['fetched'] < now()->timestamp - 300) {
            if (Cache::has($key.':backoff')) {
                $stale = true;
            } else {
                $lock = Cache::lock($key.':lock', 25);
                if ($lock->get()) {
                    try {
                        $raw = Http::withOptions(['verify' => config('weather.ca_bundle')])->connectTimeout(4)->timeout(15)
                            ->withoutRedirecting()->acceptJson()->get(self::ENDPOINT)->throw()->json();
                        if (! is_array($raw) || ($raw['waterlevel_data']['result'] ?? null) !== 'OK' || ! is_array($raw['waterlevel_data']['data'] ?? null)) {
                            throw new RuntimeException('Invalid ThaiWater response');
                        }
                        $stations = [];
                        foreach (array_merge($raw['waterlevel_data']['data'], $raw['waterlevel_manual_data']['data'] ?? []) as $row) {
                            $station = $this->normalize($row);
                            if ($station && (! isset($stations[$station['id']]) || $station['measured_at'] > $stations[$station['id']]['measured_at'])) {
                                $stations[$station['id']] = $station;
                            }
                        }
                        if (! $stations) {
                            throw new RuntimeException('No valid stations');
                        }
                        $entry = ['fetched' => now()->timestamp, 'stations' => array_values($stations)];
                        Cache::put($key, $entry, 3600);
                    } catch (Throwable $e) {
                        app(DataSourceHealth::class)->failure('water', $province->id);
                        Cache::put($key.':backoff', true, 60);
                        $stale = true;
                    } finally {
                        $lock->release();
                    }
                } else {
                    $stale = true;
                }
            }
        }
        if (! $entry || $entry['fetched'] < now()->timestamp - 3600) {
            app(DataSourceHealth::class)->failure('water', $province->id);
            throw new RuntimeException('ThaiWater temporarily unavailable');
        }
        $stations = array_values(array_filter($entry['stations'], fn ($s) => $s['province_code'] === (string) $province->code));
        foreach ($stations as &$station) {
            $station['outdated'] = Carbon::parse($station['measured_at'])->lt(now()->subHours(6));
            if ($station['outdated'] || $stale) {
                $station['color'] = '#899ba8';
            }
        }
        unset($station);

        $fetchedAt = Carbon::createFromTimestamp($entry['fetched'])->toIso8601String();
        app(DataSourceHealth::class)->success('water', $province->id, $fetchedAt, collect($stations)->max('measured_at'), [
            'total' => count($stations), 'current' => count(array_filter($stations, fn ($s) => ! $s['outdated'] && $s['value'] !== null)),
        ]);
        if ($stale) {
            app(DataSourceHealth::class)->failure('water', $province->id);
        } else {
            app(PublicWaterHistory::class)->record($province, $stations);
        }

        return ['stations' => $stations, 'stale' => $stale, 'fetched_at' => Carbon::createFromTimestamp($entry['fetched'])->toIso8601String(),
            'source' => 'คลังข้อมูลน้ำแห่งชาติ (ThaiWater) สสน.', 'source_url' => 'https://www.thaiwater.net/',
            'notice' => 'ระดับน้ำที่สถานี ไม่ใช่ความลึกน้ำท่วมบนถนนหรือที่บ้าน'];
    }

    public function normalize(mixed $row): ?array
    {
        if (! is_array($row)) {
            return null;
        }
        $s = $row['station'] ?? [];
        $g = $row['geocode'] ?? [];
        $lat = $this->number($s['tele_station_lat'] ?? null, 5, 22);
        $lng = $this->number($s['tele_station_long'] ?? null, 97, 107);
        $code = (string) ($g['province_code'] ?? '');
        $id = $s['id'] ?? null;
        $time = $row['waterlevel_datetime'] ?? '';
        if ($lat === null || $lng === null || ! preg_match('/^\d{2}$/D', $code) || ! is_numeric($id)
            || ! is_string($time) || ! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/D', $time)) {
            return null;
        }
        try {
            $at = Carbon::parse($time, 'Asia/Bangkok');
            if ($at->format(strlen($time) === 16 ? 'Y-m-d H:i' : 'Y-m-d H:i:s') !== $time || $at->gt(now()->addMinutes(10))) {
                return null;
            }
        } catch (Throwable $e) {
            return null;
        }
        $msl = $this->number($row['waterlevel_msl'] ?? null);
        $local = $this->number($row['waterlevel_m'] ?? null);
        $previous = $this->number($row['waterlevel_msl_previous'] ?? null);
        $level = filter_var($row['situation_level'] ?? null, FILTER_VALIDATE_INT);
        $category = self::LEVELS[$level] ?? ['label' => 'ยังไม่มีเกณฑ์สถานี', 'color' => '#899ba8'];
        if ($msl === null && $local === null) {
            $level = null;
            $category = ['label' => 'ยังไม่มีค่าระดับน้ำ', 'color' => '#899ba8'];
        }
        // Compare only elevations sharing the same datum, never station depth with MSL.
        $bank = $this->number($s['min_bank'] ?? null);
        $relative = $msl !== null && $bank !== null ? round($msl - $bank, 2) : null;

        return ['id' => (string) $id, 'code' => $this->text($s['tele_station_oldcode'] ?? ''), 'name' => $this->text($s['tele_station_name'] ?? ''),
            'province_code' => $code, 'province' => $this->text($g['province_name'] ?? ''), 'district' => $this->text($g['amphoe_name'] ?? ''),
            'subdistrict' => $this->text($g['tumbon_name'] ?? ''), 'lat' => $lat, 'lng' => $lng,
            'river' => $this->text($row['river_name'] ?? ''), 'agency' => $this->text($row['agency']['agency_name'] ?? ''),
            'value' => $msl ?? $local, 'unit' => $msl !== null ? 'ม. รทก.' : ($local !== null ? 'ม. (ระดับอ้างอิงสถานี)' : null),
            'bank' => $bank, 'relative_bank' => $relative, 'storage_percent' => $this->number($row['storage_percent'] ?? null),
            'situation' => $level ?: null, 'label' => $category['label'], 'color' => $category['color'],
            'trend' => $msl !== null && $previous !== null ? round($msl - $previous, 2) : null,
            'measured_at' => $at->toIso8601String()];
    }

    private function number(mixed $value, float $min = -1000, float $max = 10000): ?float
    {
        return is_numeric($value) && is_finite((float) $value) && ! in_array((float) $value, [-999.0, -999.99], true)
            && (float) $value >= $min && (float) $value <= $max ? (float) $value : null;
    }

    private function text(mixed $value): string
    {
        $value = is_array($value) ? ($value['th'] ?? $value['en'] ?? '') : $value;

        return is_string($value) ? mb_substr(trim($value), 0, 200) : '';
    }
}
