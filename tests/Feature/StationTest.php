<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Camera;
use App\Models\Province;
use App\Models\RainForecast;
use App\Models\RiskPoint;
use App\Models\User;
use App\Models\WaterStation;
use App\Support\ForecastService;
use App\Support\RiskEngine;
use App\Support\StationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StationTest extends TestCase
{
    use RefreshDatabase;

    protected Province $province;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->province = Province::where('slug', 'chachoengsao')->firstOrFail();
        $this->province->update(['command_open' => true, 'web_help_open' => true, 'center_lat' => 13.69, 'center_lng' => 101.07]);
        $this->admin = User::create(['name' => 'ผอ.', 'phone' => '0811111111', 'password' => 'secret123', 'province_id' => $this->province->id, 'status' => 'active']);
        $this->admin->assignRole('province-admin');
    }

    protected function station(array $attrs = []): WaterStation
    {
        return WaterStation::create($attrs + [
            'province_id' => $this->province->id, 'name' => 'สะพานบางปะกง', 'river' => 'บางปะกง', 'lat' => 13.70, 'lng' => 101.08,
            'unit' => 'ม.รทก.', 'bank_level' => 3.0, 'watch_level' => 2.0, 'warning_level' => 2.5, 'critical_level' => 3.0,
            'influence_radius_m' => 3000, 'fetch_mode' => 'manual', 'is_public' => true, 'is_active' => true,
        ]);
    }

    public function test_readings_set_status_trend_and_alerts(): void
    {
        $s = $this->station();
        $svc = app(StationService::class);

        $svc->record($s, 1.5, now()->subHours(3));
        $this->assertSame('normal', $s->fresh()->status);
        $this->assertSame(0, Alert::count());

        $svc->record($s->fresh(), 2.7, now());
        $s->refresh();
        $this->assertSame('warning', $s->status);
        $this->assertEqualsWithDelta(0.4, $s->trend_per_hour, 0.01);
        $alert = Alert::where('key', 'station:'.$s->id)->firstOrFail();
        $this->assertSame('warning', $alert->level);

        // รุนแรงขึ้น = อัปเดตประกาศเดิม ไม่สร้างใหม่ และต้องรับทราบใหม่
        $alert->update(['acknowledged_at' => now(), 'acknowledged_by' => $this->admin->id]);
        $svc->record($s, 3.2, now()->addMinutes(10));
        $this->assertSame(1, Alert::where('key', 'station:'.$s->id)->count());
        $this->assertSame('critical', $alert->fresh()->level);
        $this->assertNull($alert->fresh()->acknowledged_at);

        $svc->record($s->fresh(), 1.0, now()->addMinutes(20));
        $this->assertSame('resolved', $alert->fresh()->status);
    }

    public function test_station_feeds_risk_engine_within_influence_radius(): void
    {
        $s = $this->station();
        $risk = RiskPoint::create([
            'province_id' => $this->province->id, 'type' => 'road', 'name' => 'ถนนริมน้ำ', 'lat' => 13.715, 'lng' => 101.08,
            'radius_m' => 300, 'trigger_level' => 2, 'severity' => 'high', 'is_public' => true, 'status' => 'normal', 'review' => 'approved', 'source' => 'staff',
        ]);
        app(StationService::class)->record($s, 2.6);
        app(RiskEngine::class)->evaluate($this->province);

        $this->assertSame('threatened', $risk->fresh()->status);
        $this->assertStringContainsString('สถานี', $risk->fresh()->threat_reason);
        $this->assertTrue(Alert::where('key', 'risk:'.$risk->id)->exists());
    }

    public function test_forecast_fetch_and_heavy_rain_alert(): void
    {
        $rain = 120;
        Http::fake(function (HttpRequest $req) use (&$rain) {
            parse_str((string) parse_url($req->url(), PHP_URL_QUERY), $q);
            $n = count(explode(',', $q['latitude']));
            $one = ['daily' => [
                'time' => collect(range(0, 6))->map(fn ($i) => today()->addDays($i)->toDateString())->all(),
                'precipitation_sum' => [5, $rain, 0, 0, 0, 0, 0],
                'precipitation_probability_max' => [40, 90, 10, 0, 0, 0, 0],
                'temperature_2m_max' => array_fill(0, 7, 33),
            ]];

            return Http::response($n > 1 ? array_fill(0, $n, $one) : $one);
        });

        $svc = app(ForecastService::class);
        $this->assertGreaterThanOrEqual(1, $svc->fetch($this->province));
        $this->assertSame(7, RainForecast::whereNull('district_id')->count());
        $svc->evaluate($this->province);

        $alert = Alert::where('key', 'rain:'.today()->addDay()->toDateString())->firstOrFail();
        $this->assertSame('critical', $alert->level);
        $this->assertStringContainsString('พรุ่งนี้', $alert->title);
        $this->assertCount(7, ForecastService::week($this->province));

        $rain = 3;
        $svc->fetch($this->province);
        $this->assertSame(7, RainForecast::whereNull('district_id')->count());
        $svc->evaluate($this->province);
        $this->assertSame('resolved', $alert->fresh()->status);
    }

    public function test_csv_import_with_buddhist_year_and_json_fetch(): void
    {
        $s = $this->station();
        $csv = "\xEF\xBB\xBFเวลา,ระดับน้ำ\n".now()->subHours(4)->format('d/m/').(now()->year + 543).now()->subHours(4)->format(' H:i').",1.80\n".now()->subHour()->format('Y-m-d H:i').",2.10\nไม่ใช่เวลา,1\n";
        $r = app(StationService::class)->importCsv($s, $csv, $this->admin);
        $this->assertSame(2, $r['created']);
        $this->assertCount(1, $r['errors']);
        $this->assertSame('watch', $s->fresh()->status);

        $s->update(['fetch_mode' => 'json', 'fetch_url' => 'https://93.184.216.34/api/level', 'value_path' => 'data.0.level', 'time_path' => 'data.0.time', 'value_offset' => 0.5]);
        Http::fake(['93.184.216.34/*' => Http::response(['data' => [['level' => 2.2, 'time' => now()->format('Y-m-d H:i:s')]]])]);
        $this->assertTrue(app(StationService::class)->fetch($s->fresh()));
        $this->assertEqualsWithDelta(2.7, $s->fresh()->last_value, 0.001);
        $this->assertSame('warning', $s->fresh()->status);

        // URL ภายในถูกปฏิเสธ
        $s->update(['fetch_url' => 'http://127.0.0.1/secret']);
        $this->assertFalse(app(StationService::class)->fetch($s->fresh()));
        $this->assertStringContainsString('ไม่อนุญาต', $s->fresh()->fetch_error);
    }

    public function test_admin_pages_and_validation(): void
    {
        $this->actingAs($this->admin)->post(route('stations.store'), [
            'name' => 'ปตร.ท่าไข่', 'lat' => 13.68, 'lng' => 101.05, 'unit' => 'ม.รทก.', 'influence_radius_m' => 2000, 'fetch_mode' => 'manual',
            'watch_level' => 3, 'warning_level' => 2, 'is_public' => 1, 'is_active' => 1,
        ])->assertSessionHasErrors('warning_level');

        $this->actingAs($this->admin)->post(route('stations.store'), [
            'name' => 'ปตร.ท่าไข่', 'lat' => 13.68, 'lng' => 101.05, 'unit' => 'ม.รทก.', 'influence_radius_m' => 2000, 'fetch_mode' => 'manual',
            'watch_level' => 2, 'critical_level' => 3, 'is_public' => 1, 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $s = WaterStation::firstOrFail();

        $this->actingAs($this->admin)->post(route('stations.reading', $s), ['value' => 3.1])->assertRedirect();
        $this->assertSame('critical', $s->fresh()->status);

        $this->actingAs($this->admin)->get(route('stations.index'))->assertOk()->assertSee('ปตร.ท่าไข่');
        $this->actingAs($this->admin)->get(route('stations.show', $s))->assertOk()->assertSee('levelChart', false);
        $this->actingAs($this->admin)->get(route('alerts.index'))->assertOk()->assertSee('สถานีปตร.ท่าไข่');

        $this->actingAs($this->admin)->post(route('cctv.store'), ['name' => 'กล้อง', 'type' => 'image', 'url' => 'http://cam.example/x.jpg', 'lat' => 13.7, 'lng' => 101.0, 'refresh_sec' => 60])
            ->assertSessionHasErrors('url');
        $this->actingAs($this->admin)->post(route('cctv.store'), ['name' => 'กล้องสะพาน', 'type' => 'image', 'url' => 'https://cam.example/x.jpg', 'lat' => 13.7, 'lng' => 101.0, 'refresh_sec' => 60, 'is_public' => 1])
            ->assertSessionHasNoErrors();
        Camera::create(['province_id' => $this->province->id, 'name' => 'กล้องภายใน', 'type' => 'image', 'url' => 'https://cam.example/y.jpg', 'lat' => 13.7, 'lng' => 101.0, 'is_public' => false]);
        $this->actingAs($this->admin)->get(route('cctv.index'))->assertOk()->assertSee('กล้องสะพาน');

        $map = $this->getJson('/chachoengsao/map.geojson')->assertOk()->json();
        $this->assertCount(1, $map['stations']['features']);
        $this->assertCount(1, $map['cameras']);
        $this->get('/chachoengsao')->assertOk()->assertSee('ปตร.ท่าไข่');
    }

    public function test_manual_alert_ack_and_resolve(): void
    {
        $this->actingAs($this->admin)->post(route('alerts.store'), ['level' => 'critical', 'title' => 'เขื่อนระบายน้ำเพิ่ม', 'hours' => 12, 'is_public' => 1])->assertRedirect();
        $a = Alert::firstOrFail();
        $this->assertTrue($a->expires_at->isFuture());
        $this->get('/chachoengsao')->assertSee('เขื่อนระบายน้ำเพิ่ม');

        $this->actingAs($this->admin)->post(route('alerts.ack', $a))->assertRedirect();
        $this->assertNotNull($a->fresh()->acknowledged_at);
        $this->actingAs($this->admin)->post(route('alerts.resolve', $a))->assertRedirect();
        $this->assertSame('resolved', $a->fresh()->status);
        $this->get('/chachoengsao')->assertDontSee('เขื่อนระบายน้ำเพิ่ม');
    }
}
