<?php

namespace Tests\Feature;

use App\Models\Province;
use App\Support\PublicWeatherService;
use Database\Seeders\ProvinceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublicWeatherTest extends TestCase
{
    use RefreshDatabase;

    private Province $province;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now('Asia/Bangkok')->setDate(2026, 10, 3)->setTime(12, 30));
        Cache::flush();
        Http::preventStrayRequests();
        $this->province = Province::create(['code' => '92', 'slug' => 'trang', 'name_th' => 'ตรัง',
            'region' => 'south', 'is_active' => true, 'center_lat' => 7.56, 'center_lng' => 99.61]);
    }

    private function payload(): array
    {
        $start = now('Asia/Bangkok')->startOfDay();
        $times = collect(range(0, 167))->map(fn ($i) => $start->copy()->addHours($i)->format('Y-m-d\TH:00'))->all();

        return [
            'current' => ['time' => $start->copy()->addHours(12)->addMinutes(15)->format('Y-m-d\TH:i'),
                'temperature_2m' => 28.4, 'apparent_temperature' => 32.1, 'relative_humidity_2m' => 85,
                'weather_code' => 61, 'wind_speed_10m' => 9, 'wind_direction_10m' => 240],
            'hourly' => ['time' => $times, 'precipitation' => array_fill(0, 168, 0.5),
                'precipitation_probability' => array_fill(0, 168, 75), 'weather_code' => array_fill(0, 168, 61),
                'temperature_2m' => array_fill(0, 168, 28), 'wind_speed_10m' => array_fill(0, 168, 12),
                'wind_direction_10m' => array_fill(0, 168, 240)],
            'daily' => ['time' => collect(range(0, 6))->map(fn ($i) => $start->copy()->addDays($i)->toDateString())->all(),
                'precipitation_sum' => [12, 4, 0, 3, 5, 7, 9], 'precipitation_probability_max' => [75, 60, 5, 50, 60, 75, 80],
                'weather_code' => [61, 61, 0, 61, 61, 61, 61], 'temperature_2m_max' => array_fill(0, 7, 32),
                'temperature_2m_min' => array_fill(0, 7, 25), 'wind_speed_10m_max' => array_fill(0, 7, 12)],
        ];
    }

    private function forecastUrl(): string
    {
        return route('public.weather.forecast', $this->province);
    }

    public function test_weather_page_renders_without_contacting_providers(): void
    {
        $this->get(route('public.weather', $this->province))->assertOk()
            ->assertSee('เรดาร์ย้อนหลัง')->assertSee('พยากรณ์ฝน–ลม')->assertSee('weatherMap');
        Http::assertNothingSent();
    }

    public function test_rolling_24_hours_are_correct_and_provider_calls_are_cached(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response($this->payload())]);
        $response = $this->getJson($this->forecastUrl())->assertOk()
            ->assertJsonPath('summary.rain_mm', 12)->assertJsonPath('summary.probability', 75)
            ->assertJsonPath('summary.category', 'ฝนปานกลาง')->assertJsonPath('stale', false)
            ->assertJsonPath('summary.first_rain', '2026-10-03T12:00:00+07:00')
            ->assertJsonPath('hours.0.time', '2026-10-03T13:00:00+07:00')
            ->assertJsonPath('summary.to', '2026-10-04T12:00:00+07:00');
        $this->assertCount(7, $response->json('days'));
        $this->getJson($this->forecastUrl())->assertOk();
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['timezone'] === 'Asia/Bangkok' && $request['latitude'] == 7.56 && $request['forecast_days'] === 7);
    }

    public function test_cached_summary_moves_forward_when_the_hour_changes(): void
    {
        $data = $this->payload();
        $data['hourly']['precipitation'][13] = 5;
        Http::fake(['api.open-meteo.com/*' => Http::response($data)]);
        $this->travelTo(now()->setTime(12, 55));
        $this->getJson($this->forecastUrl())->assertJsonPath('summary.rain_mm', 16.5);
        $this->travel(10)->minutes();
        $this->getJson($this->forecastUrl())->assertJsonPath('hours.0.time', '2026-10-03T14:00:00+07:00')
            ->assertJsonPath('summary.rain_mm', 12);
        Http::assertSentCount(1);
    }

    public function test_missing_values_are_not_presented_as_zero_rain_or_zero_probability(): void
    {
        $data = $this->payload();
        $data['hourly']['precipitation'][13] = null;
        $data['hourly']['precipitation_probability'][13] = null;
        Http::fake(['api.open-meteo.com/*' => Http::response($data)]);
        $this->getJson($this->forecastUrl())->assertOk()->assertJsonPath('summary.rain_mm', null)
            ->assertJsonPath('summary.probability', null)->assertJsonPath('summary.category', 'ข้อมูลฝนไม่ครบ');
    }

    public function test_provider_outage_returns_unavailable_with_short_backoff(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response([], 503)]);
        $this->getJson($this->forecastUrl())->assertStatus(503)->assertHeader('Retry-After', '60');
        $this->getJson($this->forecastUrl())->assertStatus(503);
        Http::assertSentCount(1);
    }

    public function test_cached_forecast_is_marked_stale_when_refresh_fails(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::sequence()->push($this->payload())->push([], 503)]);
        $this->getJson($this->forecastUrl())->assertOk();
        $this->travel(16)->minutes();
        $this->getJson($this->forecastUrl())->assertOk()->assertJsonPath('stale', true);
    }

    public function test_expired_forecast_is_not_served_as_current(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::sequence()->push($this->payload())->push([], 503)]);
        $this->getJson($this->forecastUrl())->assertOk();
        $this->travel(3)->hours();
        $this->getJson($this->forecastUrl())->assertStatus(503);
    }

    public function test_malformed_forecast_is_unavailable_not_a_dry_forecast(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response(['hourly' => ['time' => []]])]);
        $this->getJson($this->forecastUrl())->assertStatus(503);
    }

    public function test_radar_uses_only_past_frames_and_is_shared_between_provinces(): void
    {
        $time = now()->timestamp - 600;
        Http::fake(['api.rainviewer.com/*' => Http::response(['host' => 'https://tilecache.rainviewer.com',
            'generated' => $time, 'radar' => ['past' => [['time' => $time, 'path' => '/v2/radar/b8696aacc596']],
                'nowcast' => [['time' => $time + 1800, 'path' => '/v2/radar/'.($time + 1800)]]]])]);
        $this->getJson(route('public.weather.radar', $this->province))->assertOk()
            ->assertJsonCount(1, 'frames')->assertJsonPath('frames.0.time', $time);
        $other = Province::create(['code' => '10', 'slug' => 'bangkok', 'name_th' => 'กรุงเทพมหานคร', 'is_active' => true]);
        $this->getJson(route('public.weather.radar', $other))->assertOk();
        Http::assertSentCount(1);
    }

    public function test_untrusted_radar_host_is_rejected(): void
    {
        Http::fake(['api.rainviewer.com/*' => Http::response(['host' => 'https://evil.example', 'radar' => ['past' => []]])]);
        $this->getJson(route('public.weather.radar', $this->province))->assertStatus(503);
    }

    public function test_inactive_provinces_are_not_exposed_and_missing_coordinates_do_not_use_bangkok_forecasts(): void
    {
        $this->province->update(['center_lat' => null, 'code' => '99']);
        $this->getJson($this->forecastUrl())->assertStatus(422);
        $this->province->update(['is_active' => false]);
        $this->get(route('public.weather', $this->province))->assertNotFound();
        $this->getJson($this->forecastUrl())->assertNotFound();
        $this->getJson(route('public.weather.radar', $this->province))->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_trang_uses_verified_city_fallback_only_when_coordinates_are_unset(): void
    {
        $this->province->update(['center_lat' => null, 'center_lng' => null]);
        Http::fake(['api.open-meteo.com/*' => Http::response($this->payload())]);
        $this->getJson($this->forecastUrl())->assertOk();
        Http::assertSent(fn ($request) => $request['latitude'] == 7.55633 && $request['longitude'] == 99.61141);
        $this->assertNull($this->province->fresh()->center_lat);
    }

    public function test_every_thai_province_has_an_offline_reference_point_without_network_or_database_writes(): void
    {
        $this->assertCount(77, config('weather.city_centers'));
        foreach (ProvinceSeeder::PROVINCES as [$code]) {
            $point = app(PublicWeatherService::class)->center(new Province(['code' => $code]));
            $this->assertCount(2, $point, $code);
            $this->assertTrue($point[0] > 5 && $point[0] < 21 && $point[1] > 97 && $point[1] < 106, $code);
        }
        Http::assertNothingSent();
    }

    public function test_context_changes_location_and_never_contacts_weather_providers(): void
    {
        $other = Province::create(['code' => '50', 'slug' => 'chiang-mai', 'name_th' => 'เชียงใหม่', 'is_active' => true]);
        $this->getJson(route('public.weather.context', $other))->assertOk()
            ->assertJsonPath('location.slug', 'chiang-mai')->assertJsonPath('location.center.0', 18.79038)
            ->assertJsonPath('location.url', '/chiang-mai/weather')->assertJsonPath('boundaries.features', []);
        $this->get(route('public.weather', $this->province))->assertOk()->assertSee('value="chiang-mai"', false)->assertSee('weatherLocations');
        Http::assertNothingSent();
        $other->update(['is_active' => false]);
        $this->getJson(route('public.weather.context', $other))->assertNotFound();
        $this->get(route('public.weather', $this->province))->assertDontSee('value="chiang-mai"', false);
    }

    public function test_forecast_includes_current_model_temperature_humidity_and_wind(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response($this->payload())]);
        $this->getJson($this->forecastUrl())->assertOk()->assertJsonPath('current.temperature', 28.4)
            ->assertJsonPath('current.humidity', 85)->assertJsonPath('current.wind_kmh', 9)
            ->assertJsonPath('current.feels_like', 32.1)->assertJsonPath('current.time', '2026-10-03T12:15:00+07:00');
    }

    public function test_forecasts_and_caches_stay_separate_when_switching_provinces(): void
    {
        $other = Province::create(['code' => '50', 'slug' => 'chiang-mai', 'name_th' => 'เชียงใหม่', 'is_active' => true]);
        Http::fake(['api.open-meteo.com/*' => function ($request) {
            $payload = $this->payload();
            $payload['current']['temperature_2m'] = $request['latitude'] > 18 ? 22 : 28;

            return Http::response($payload);
        }]);
        $this->getJson($this->forecastUrl())->assertOk()->assertJsonPath('current.temperature', 28);
        $this->getJson(route('public.weather.forecast', $other))->assertOk()->assertJsonPath('current.temperature', 22)
            ->assertJsonCount(7, 'days');
        $this->getJson($this->forecastUrl())->assertOk()->assertJsonPath('current.temperature', 28);
        Http::assertSentCount(2);
    }
}
