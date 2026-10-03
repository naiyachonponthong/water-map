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

    public function forecast(Province $province): array
    {
        $center = $this->center($province);
        if (! $center) {
            throw new RuntimeException('ยังไม่ได้กำหนดพิกัดจังหวัด');
        }
        $key = 'public-weather:v2:'.$province->id.':'.sha1(implode(',', $center));
        $result = $this->cached($key, 900, 7200, function () use ($center) {
            $raw = Http::withOptions(['verify' => config('weather.ca_bundle')])->connectTimeout(4)->timeout(10)->acceptJson()->get('https://api.open-meteo.com/v1/forecast', [
                'latitude' => $center[0], 'longitude' => $center[1],
                'hourly' => 'precipitation,precipitation_probability,weather_code,temperature_2m,wind_speed_10m,wind_direction_10m',
                'daily' => 'precipitation_sum,precipitation_probability_max,weather_code,temperature_2m_max,temperature_2m_min,wind_speed_10m_max',
                'current' => 'temperature_2m,apparent_temperature,relative_humidity_2m,weather_code,wind_speed_10m,wind_direction_10m,is_day',
                'timezone' => 'Asia/Bangkok', 'forecast_days' => 7, 'wind_speed_unit' => 'kmh',
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
                'current' => [
                    'time' => isset($current['time']) ? Carbon::parse($current['time'], 'Asia/Bangkok')->toIso8601String() : null,
                    'temperature' => $this->number($current['temperature_2m'] ?? null, 70, -80),
                    'feels_like' => $this->number($current['apparent_temperature'] ?? null, 80, -100),
                    'humidity' => $this->number($current['relative_humidity_2m'] ?? null, 100),
                    'code' => $this->number($current['weather_code'] ?? null, 99),
                    'wind_kmh' => $this->number($current['wind_speed_10m'] ?? null, 500),
                    'wind_direction' => $this->number($current['wind_direction_10m'] ?? null, 360),
                ]];
        });
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
        $result['summary'] = [
            'rain_mm' => $mm, 'category' => $this->rainLabel($mm),
            'probability' => $probComplete ? max($probs) : null,
            'first_rain' => $wet ? Carbon::parse($wet[0]['time'])->subHour()->toIso8601String() : null,
            'from' => Carbon::parse($next[0]['time'])->subHour()->toIso8601String(), 'to' => $next[23]['time'],
        ];
        $result['source'] = 'Open-Meteo';

        return $result;
    }

    public function radar(): array
    {
        return $this->cached('public-weather:radar:v1', 60, 900, function () {
            $raw = Http::withOptions(['verify' => config('weather.ca_bundle')])->connectTimeout(4)->timeout(10)->acceptJson()
                ->get('https://api.rainviewer.com/public/weather-maps.json')->throw()->json();
            if (($raw['host'] ?? '') !== 'https://tilecache.rainviewer.com') {
                throw new RuntimeException('Invalid radar host');
            }
            $frames = array_values(array_filter($raw['radar']['past'] ?? [], fn ($f) => is_array($f) && is_numeric($f['time'] ?? null) && $f['time'] <= now()->timestamp + 600
                && preg_match('#^/v2/radar/[a-f0-9]{9,32}$#', $f['path'] ?? '')
            ));
            usort($frames, fn ($a, $b) => $a['time'] <=> $b['time']);
            if (! $frames) {
                throw new RuntimeException('No radar frames');
            }

            return ['host' => $raw['host'], 'frames' => array_slice($frames, -24),
                'generated' => $raw['generated'] ?? null, 'updated_at' => now()->toIso8601String(), 'source' => 'RainViewer'];
        });
    }

    private function cached(string $key, int $freshSeconds, int $maxAge, callable $fetch): array
    {
        $old = Cache::get($key);
        if ($old && now()->timestamp - $old['at'] < $freshSeconds) {
            return $old['data'] + ['stale' => false];
        }
        // A short failure backoff prevents every visitor from retrying a provider outage.
        if (! Cache::has($key.':failed')) {
            try {
                $data = $fetch();
                Cache::put($key, ['at' => now()->timestamp, 'data' => $data], $maxAge);

                return $data + ['stale' => false];
            } catch (Throwable $e) {
                report($e);
                Cache::put($key.':failed', true, 60);
            }
        }
        if ($old && now()->timestamp - $old['at'] < $maxAge) {
            return $old['data'] + ['stale' => true];
        }
        throw new RuntimeException('Weather provider unavailable');
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
