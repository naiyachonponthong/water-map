<?php

namespace App\Support;

use App\Models\Province;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/** Public forecasts are cached separately from the alert engine's daily records. */
class PublicWeatherService
{
    public function center(Province $province): ?array
    {
        $point = $province->hasCenter() ? [$province->center_lat, $province->center_lng]
            : config('weather.city_centers.'.$province->code);

        return $point ? array_slice($point, 0, 2) : null;
    }

    public function forecast(Province $province, ?array $point = null): array
    {
        $center = $point['center'] ?? $this->center($province);
        if (! $center) {
            throw new RuntimeException('ยังไม่ได้กำหนดพิกัดจังหวัด');
        }
        $key = 'public-weather:v3:'.$province->id.':'.sha1(implode(',', $center));
        $result = $this->cached($key, 900, 7200, function () use ($center) {
            $raw = Http::withOptions(['verify' => config('weather.ca_bundle')])->connectTimeout(4)->timeout(10)->acceptJson()->get('https://api.open-meteo.com/v1/forecast', [
                'latitude' => $center[0], 'longitude' => $center[1],
                'hourly' => 'precipitation,precipitation_probability,weather_code,temperature_2m,wind_speed_10m,wind_direction_10m',
                'daily' => 'precipitation_sum,precipitation_probability_max,weather_code,temperature_2m_max,temperature_2m_min,wind_speed_10m_max',
                'current' => 'temperature_2m,apparent_temperature,relative_humidity_2m,weather_code,wind_speed_10m,wind_direction_10m,is_day',
                'timezone' => 'Asia/Bangkok', 'forecast_days' => 7, 'wind_speed_unit' => 'kmh',
                'models' => 'best_match', 'cell_selection' => 'land', 'temperature_unit' => 'celsius', 'precipitation_unit' => 'mm',
            ])->throw()->json();
            if (! is_array($raw) || empty($raw['hourly']['time']) || empty($raw['daily']['time'])) {
                throw new RuntimeException('Incomplete forecast');
            }
            $hours = [];
            foreach ($raw['hourly']['time'] as $i => $time) {
                if (! is_string($time) || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:00$/', $time)) {
                    continue;
                }
                $hours[$time] = [
                    'time' => Carbon::parse($time, 'Asia/Bangkok')->toIso8601String(),
                    'rain_mm' => $this->number($raw['hourly']['precipitation'][$i] ?? null),
                    'probability' => $this->number($raw['hourly']['precipitation_probability'][$i] ?? null, 100),
                    'code' => $this->number($raw['hourly']['weather_code'][$i] ?? null, 99),
                    'temperature' => $this->number($raw['hourly']['temperature_2m'][$i] ?? null, 70, -80),
                    'wind_kmh' => $this->number($raw['hourly']['wind_speed_10m'][$i] ?? null, 500),
                    'wind_direction' => $this->number($raw['hourly']['wind_direction_10m'][$i] ?? null, 360),
                ];
            }
            ksort($hours);
            $days = [];
            foreach ($raw['daily']['time'] as $i => $date) {
                if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    continue;
                }
                $mm = $this->number($raw['daily']['precipitation_sum'][$i] ?? null);
                $days[] = [
                    'date' => $date, 'rain_mm' => $mm,
                    'probability' => $this->number($raw['daily']['precipitation_probability_max'][$i] ?? null, 100),
                    'code' => $this->number($raw['daily']['weather_code'][$i] ?? null, 99),
                    'temp_max' => $this->number($raw['daily']['temperature_2m_max'][$i] ?? null, 70, -80),
                    'temp_min' => $this->number($raw['daily']['temperature_2m_min'][$i] ?? null, 70, -80),
                    'wind_kmh' => $this->number($raw['daily']['wind_speed_10m_max'][$i] ?? null, 500),
                    'category' => $this->rainLabel($mm),
                ];
            }
            if (count($hours) < 24 || count($days) < 3) {
                throw new RuntimeException('Incomplete forecast');
            }

            $current = $raw['current'] ?? [];

            return ['hours' => array_values($hours), 'days' => $days, 'updated_at' => now()->toIso8601String(),
                'grid' => ['latitude' => $this->number($raw['latitude'] ?? null, 90, -90),
                    'longitude' => $this->number($raw['longitude'] ?? null, 180, -180)],
                'current' => [
                    'time' => isset($current['time']) ? Carbon::parse($current['time'], 'Asia/Bangkok')->toIso8601String() : null,
                    'temperature' => $this->number($current['temperature_2m'] ?? null, 70, -80),
                    'feels_like' => $this->number($current['apparent_temperature'] ?? null, 80, -100),
                    'humidity' => $this->number($current['relative_humidity_2m'] ?? null, 100),
                    'code' => $this->number($current['weather_code'] ?? null, 99),
                    'wind_kmh' => $this->number($current['wind_speed_10m'] ?? null, 500),
                    'wind_direction' => $this->number($current['wind_direction_10m'] ?? null, 360),
                ]];
        }, 'forecast', $province->id, $point === null);
        // Recalculate the rolling window even when serving cached data (including across midnight).
        $from = now('Asia/Bangkok')->startOfHour();
        // Open-Meteo precipitation belongs to the preceding hour. Skip the hour that
        // has already ended; the first forecast bucket ends at the next full hour.
        $hours = array_values(array_filter($result['hours'], fn ($h) => Carbon::parse($h['time'])->gt($from)));
        $next = array_slice($hours, 0, 24);
        if (count($next) < 24) {
            throw new RuntimeException('Forecast expired');
        }
        $rainComplete = count(array_filter($next, fn ($h) => $h['rain_mm'] !== null)) === 24;
        $probs = array_column($next, 'probability');
        $probComplete = count(array_filter($probs, fn ($p) => $p !== null)) === 24;
        $mm = $rainComplete ? round(array_sum(array_column($next, 'rain_mm')), 1) : null;
        $wet = array_values(array_filter($next, fn ($h) => $h['rain_mm'] !== null && $h['rain_mm'] >= 0.1));
        $result['hours'] = $hours;
        $result['days'] = array_values(array_filter($result['days'], fn ($d) => $d['date'] >= $from->toDateString()));
        $windows = [];
        foreach ($wet as $hour) {
            $start = Carbon::parse($hour['time'])->subHour()->toIso8601String();
            $last = count($windows) - 1;
            if ($last >= 0 && $windows[$last]['to'] === $start) {
                $windows[$last]['to'] = $hour['time'];
                $windows[$last]['rain_mm'] += $hour['rain_mm'];
            } else {
                $windows[] = ['from' => $start, 'to' => $hour['time'], 'rain_mm' => $hour['rain_mm']];
            }
        }
        $result['summary'] = [
            'rain_mm' => $mm, 'category' => $this->rainLabel($mm),
            'probability' => $probComplete ? max($probs) : null,
            'first_rain' => $wet ? Carbon::parse($wet[0]['time'])->subHour()->toIso8601String() : null,
            'from' => Carbon::parse($next[0]['time'])->subHour()->toIso8601String(), 'to' => $next[23]['time'],
            'rain_windows' => array_map(fn ($w) => array_replace($w, ['rain_mm' => round($w['rain_mm'], 1)]), $windows), 'rain_hours' => $rainComplete ? count($wet) : null,
            'max_hour_mm' => $rainComplete ? max(array_column($next, 'rain_mm')) : null,
        ];
        $result['source'] = 'Open-Meteo';
        $result['location'] = ['name' => $point['name'] ?? 'ตัวเมือง'.$province->name_th, 'center' => $center,
            'scope' => $point ? 'subdistrict' : 'city'];
        $result['model_selection'] = 'best_match';
        $currentTime = $result['current']['time'] ?? null;
        $result['current_stale'] = ! $currentTime || Carbon::parse($currentTime)->lt(now()->subMinutes(45))
            || Carbon::parse($currentTime)->gt(now()->addMinutes(15));
        if ($result['current_stale']) {
            // Keep the timestamp, but never present an old model reading as current weather.
            $result['current'] = array_fill_keys(array_keys($result['current']), null);
            $result['current']['time'] = $currentTime;
        }

        return $result;
    }

    public function radar(): array
    {
        return $this->cached('public-weather:radar:v2', 60, 900, function () {
            $raw = Http::withOptions(['verify' => config('weather.ca_bundle')])->connectTimeout(4)->timeout(10)->acceptJson()
                ->get('https://api.rainviewer.com/public/weather-maps.json')->throw()->json();
            if (($raw['host'] ?? '') !== 'https://tilecache.rainviewer.com') {
                throw new RuntimeException('Invalid radar host');
            }
            $frames = array_values(array_filter($raw['radar']['past'] ?? [], fn ($f) => is_array($f) && is_numeric($f['time'] ?? null) && $f['time'] <= now()->timestamp
                && $f['time'] >= now()->subHours(3)->timestamp
                && preg_match('#^/v2/radar/[a-f0-9]{9,32}$#', $f['path'] ?? '')
            ));
            usort($frames, fn ($a, $b) => $a['time'] <=> $b['time']);
            if (! $frames) {
                throw new RuntimeException('No radar frames');
            }

            return ['host' => $raw['host'], 'frames' => array_slice($frames, -24),
                'generated' => $raw['generated'] ?? null, 'updated_at' => now()->toIso8601String(), 'source' => 'RainViewer'];
        }, 'radar', null);
    }

    private function cached(string $key, int $freshSeconds, int $maxAge, callable $fetch, string $source, ?int $provinceId, bool $trackHealth = true): array
    {
        $old = Cache::get($key);
        if ($old && now()->timestamp - $old['at'] < $freshSeconds) {
            if ($trackHealth) {
                $this->health($source, $provinceId, $old['data']);
            }

            return $old['data'] + ['stale' => false];
        }
        // A short failure backoff prevents every visitor from retrying a provider outage.
        if (! Cache::has($key.':failed')) {
            try {
                $data = $fetch();
                Cache::put($key, ['at' => now()->timestamp, 'data' => $data], $maxAge);
                if ($trackHealth) {
                    $this->health($source, $provinceId, $data);
                }

                return $data + ['stale' => false];
            } catch (Throwable $e) {
                if ($trackHealth) {
                    app(DataSourceHealth::class)->failure($source, $provinceId);
                }
                report($e);
                Cache::put($key.':failed', true, 60);
            }
        }
        if ($old && now()->timestamp - $old['at'] < $maxAge) {
            if ($trackHealth) {
                $this->health($source, $provinceId, $old['data']);
                app(DataSourceHealth::class)->failure($source, $provinceId);
            }

            return $old['data'] + ['stale' => true];
        }
        if ($trackHealth) {
            app(DataSourceHealth::class)->failure($source, $provinceId);
        }
        throw new RuntimeException('Weather provider unavailable');
    }

    private function health(string $source, ?int $provinceId, array $data): void
    {
        $measured = $source === 'radar' ? Carbon::createFromTimestamp(max(array_column($data['frames'], 'time')))->toIso8601String() : null;
        app(DataSourceHealth::class)->success($source, $provinceId, $data['updated_at'], $measured);
    }

    private function number(mixed $value, float $max = 10000, float $min = 0): ?float
    {
        return is_numeric($value) && is_finite((float) $value) && $value >= $min && $value <= $max ? (float) $value : null;
    }

    private function rainLabel(?float $mm): string
    {
        return $mm === null ? 'ข้อมูลฝนไม่ครบ' : (StationOptions::rain($mm)['label'] ?? 'ยังไม่คาดว่าจะมีฝน');
    }
}
