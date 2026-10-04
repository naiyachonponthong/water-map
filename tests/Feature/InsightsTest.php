<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\District;
use App\Models\Province;
use App\Models\Subdistrict;
use App\Models\WaterReport;
use App\Models\WaterStation;
use App\Models\User;
use App\Support\DataSourceHealth;
use App\Support\PublicAreaReference;
use App\Support\PublicWaterHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InsightsTest extends TestCase
{
    use RefreshDatabase;

    private Province $province;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now('Asia/Bangkok')->setDate(2026, 10, 4)->setTime(10, 35));
        Cache::flush();
        Http::preventStrayRequests();
        $this->province = Province::create(['code' => '92', 'slug' => 'trang', 'name_th' => 'ตรัง', 'region' => 'south', 'is_active' => true]);
    }

    public function test_public_pages_render_without_blocking_external_requests_and_hide_inactive_provinces(): void
    {
        $this->get('/my-area')->assertOk()->assertSee('พื้นที่ที่คุณห่วงใย')->assertSee('data-insights-config', false);
        $this->get('/trang/insights')->assertOk()->assertSee('ฝน 24 ชั่วโมงข้างหน้า')->assertSee('insights.js', false);
        $this->get('/trang/data-status')->assertOk()->assertSee('ความสดของข้อมูล')->assertSee('data-health.js', false);
        $this->get('/trang/data-status.json')->assertOk()->assertJsonCount(3, 'sources')->assertJsonPath('sources.0.state', 'unchecked');
        Http::assertNothingSent();
        $this->province->update(['is_active' => false]);
        foreach (['insights', 'insights/summary.json', 'insights/history.json?source=local&station=1', 'data-status', 'data-status.json'] as $path) {
            $this->get('/trang/'.$path)->assertNotFound();
        }
    }

    private function report(array $attributes = []): WaterReport
    {
        return WaterReport::create($attributes + ['province_id' => $this->province->id, 'lat' => 7.8, 'lng' => 99.6,
            'level' => 3, 'status' => 'published', 'outside_province' => false, 'expires_at' => now()->addHours(6),
            'reporter_name' => 'PRIVATE PERSON', 'reporter_phone' => '0812345678', 'note' => 'PRIVATE NOTE', 'device_hash' => 'PRIVATE DEVICE']);
    }

    public function test_reports_are_scoped_aggregated_and_never_expose_personal_data(): void
    {
        $area = (new PublicAreaReference)->areas($this->province)[0];
        $district = District::create(['province_id' => $this->province->id, 'code' => $area['code'], 'name_th' => $area['name']]);
        $subdistrict = Subdistrict::create(['province_id' => $this->province->id, 'district_id' => $district->id, 'code' => $area['subdistricts'][0]['code'], 'name_th' => $area['subdistricts'][0]['name']]);
        $this->report(['district_id' => $district->id, 'subdistrict_id' => $subdistrict->id, 'verified' => true]);
        $this->report(); // coverage gap, must not be silently assigned to selected area
        $this->report(['status' => 'pending']);
        $this->report(['outside_province' => true]);
        $this->report(['level' => 1]);
        $this->report(['expires_at' => now()->subMinute()]);
        $old = $this->report(); $old->forceFill(['updated_at' => now()->subHours(25)])->save();
        $future = $this->report(); $future->forceFill(['updated_at' => now()->addHour()])->save();
        $edge = $this->report(); $edge->forceFill(['updated_at' => now()->subHours(24)->addMinute()])->save();
        $result = $this->get('/trang/insights/summary.json')->assertOk()->assertJsonPath('reports.count', 3);
        $this->assertSame(3, array_sum(array_column($result->json('reports.hours'), 'count')));
        $this->assertSame(25, count($result->json('reports.hours'))); // first and last are partial hours
        $scoped = $this->get('/trang/insights/summary.json?district='.$district->code.'&subdistrict='.$subdistrict->code)
            ->assertOk()->assertJsonPath('reports.count', 1)->assertJsonPath('reports.verified', 1)->assertJsonPath('reports.unassigned_in_province', 2);
        foreach (['PRIVATE', '0812345678', 'lat', 'lng', 'reporter_phone', 'fetch_url'] as $private) {
            $scoped->assertDontSee($private);
        }
        $this->getJson('/trang/insights/summary.json?district=1001')->assertStatus(422);
        $this->getJson('/trang/insights/summary.json?subdistrict='.$subdistrict->code)->assertStatus(422);
        Http::assertNothingSent();
    }

    public function test_history_preserves_gaps_and_unit_and_hides_private_stations(): void
    {
        $station = WaterStation::create(['province_id' => $this->province->id, 'name' => 'สถานีสาธารณะ', 'unit' => 'ม. รทก.',
            'bank_level' => 4.5, 'lat' => 7.8, 'lng' => 99.6, 'is_active' => true, 'is_public' => true, 'fetch_url' => 'https://private.example/secret']);
        $station->readings()->create(['value' => 2.2, 'measured_at' => now()->subHours(2)->startOfHour()]);
        $station->readings()->create(['value' => 2.3, 'measured_at' => now()->subHours(2)->startOfHour()->addMinutes(30)]);
        $station->readings()->create(['value' => 0, 'measured_at' => now()->startOfHour()]);
        $station->readings()->create(['value' => 9, 'measured_at' => now()->addHour()]);
        $url = '/trang/insights/history.json?source=local&station='.$station->id;
        $this->get($url)->assertOk()->assertJsonCount(72, 'points')->assertJsonPath('points.69.value', 2.3)
            ->assertJsonPath('points.70.value', null)->assertJsonPath('points.71.value', 0)->assertJsonPath('bank', 4.5)->assertDontSee('private.example');
        $station->update(['is_public' => false]);
        $this->get($url)->assertNotFound();
        $this->get('/trang/insights/summary.json')->assertOk()->assertJsonCount(0, 'local_stations');
        $station->update(['is_public' => true, 'is_active' => false]); $this->get($url)->assertNotFound();
    }

    public function test_thaiwater_history_is_idempotent_scoped_and_does_not_mix_datums(): void
    {
        $service = new PublicWaterHistory;
        $row = ['id' => '42', 'name' => 'ThaiWater station', 'value' => 3.2, 'bank' => 7.5, 'unit' => 'ม. รทก.', 'measured_at' => now()->subHour()->toIso8601String()];
        $service->record($this->province, [$row]); $service->record($this->province, [$row]);
        $service->record($this->province, [array_replace($row, ['unit' => 'ม. (ระดับอ้างอิงสถานี)', 'value' => 1.2])]);
        $service->record($this->province, [array_replace($row, ['id' => '43', 'value' => null])]);
        $this->assertSame(2, DB::table('public_water_readings')->count());
        $this->get('/trang/insights/history.json?source=thaiwater&station=42&datum=local')->assertOk()->assertJsonPath('bank', null)->assertJsonPath('points.70.value', 1.2);
        $this->get('/trang/insights/history.json?source=thaiwater&station=42&datum=msl')->assertOk()->assertJsonPath('bank', 7.5)->assertJsonPath('points.70.value', 3.2);
        $other = Province::create(['code' => '81', 'slug' => 'krabi', 'name_th' => 'กระบี่', 'region' => 'south', 'is_active' => true]);
        $this->get('/krabi/insights/history.json?source=thaiwater&station=42')->assertNotFound();
        $this->getJson('/trang/insights/history.json?source=local&station[]=1')->assertStatus(422);
    }

    public function test_source_health_distinguishes_fetch_and_measurement_and_recovers_without_flood_alerts(): void
    {
        $health = new DataSourceHealth;
        $health->success('water', $this->province->id, now()->toIso8601String(), now()->subHours(7)->toIso8601String(), ['total' => 1, 'current' => 0]);
        $this->get('/trang/data-status.json')->assertJsonPath('sources.0.state', 'delayed');
        $health->success('water', $this->province->id, now()->toIso8601String(), now()->toIso8601String(), ['total' => 2, 'current' => 1]);
        $this->get('/trang/data-status.json')->assertJsonPath('sources.0.state', 'partial');
        $health->failure('water', $this->province->id);
        $this->get('/trang/data-status.json')->assertJsonPath('sources.0.state', 'unavailable')->assertJsonPath('sources.0.has_cached_data', true);
        $this->travel(1)->seconds();
        $health->success('water', $this->province->id, now()->toIso8601String(), now()->toIso8601String(), ['total' => 2, 'current' => 2]);
        $this->get('/trang/data-status.json')->assertJsonPath('sources.0.state', 'fresh');
        $this->travel(11)->minutes();
        $this->get('/trang/data-status.json')->assertJsonPath('sources.0.state', 'delayed');
        $this->assertSame(0, Alert::count()); Http::assertNothingSent();
    }

    public function test_upgrade_adds_missing_table_and_preserves_existing_data_and_encryption_key(): void
    {
        $report = $this->report();
        $key = config('app.key');
        Schema::drop('public_water_readings');
        DB::table('migrations')->where('migration', '2026_10_12_000001_create_public_water_readings_table')->delete();
        $this->artisan('flood:upgrade')->assertSuccessful();
        $this->assertTrue(Schema::hasTable('public_water_readings'));
        $this->assertSame($key, config('app.key'));
        $this->assertSame('0812345678', $report->fresh()->reporter_phone);
        $this->assertSame(1, Province::count());
        $this->artisan('flood:upgrade')->assertSuccessful();
        $this->assertSame(1, WaterReport::count());
    }

    public function test_admin_health_page_requires_dashboard_permission_and_uses_own_province(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        config(['floodthai.two_factor_roles' => []]);
        $this->get('/admin/data-health')->assertRedirect('/login');
        $admin = User::create(['name' => 'Test director', 'phone' => '0811111222', 'password' => 'test-only', 'province_id' => $this->province->id, 'status' => 'active']);
        $admin->assignRole('province-admin');
        $this->actingAs($admin)->get('/admin/data-health')->assertOk()->assertSee('ความสดของข้อมูล')->assertSee('/trang/data-status.json', false);
        $member = User::create(['name' => 'Test team member', 'phone' => '0811111333', 'password' => 'test-only', 'province_id' => $this->province->id, 'status' => 'active']);
        $member->assignRole('team-member');
        $this->actingAs($member)->get('/admin/data-health')->assertForbidden();
    }
}
