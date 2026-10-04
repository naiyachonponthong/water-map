<?php

namespace Tests\Feature;

use App\Models\Province;
use App\Models\WaterReport;
use App\Models\WaterStation;
use App\Support\PublicAreaReference;
use App\Support\PublicWaterService;
use Database\Seeders\ProvinceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublicWaterTest extends TestCase
{
    use RefreshDatabase;

    private Province $province;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now('Asia/Bangkok')->setDate(2026, 10, 3)->setTime(10, 0));
        Cache::flush();
        Http::preventStrayRequests();
        $this->province = Province::create(['code' => '92', 'slug' => 'trang', 'name_th' => 'ตรัง', 'region' => 'south', 'is_active' => true]);
    }

    private function row(string $province = '92', int $id = 1): array
    {
        return ['waterlevel_datetime' => '2026-10-03 09:30', 'waterlevel_msl' => '8.08', 'waterlevel_m' => null,
            'waterlevel_msl_previous' => '8.1', 'situation_level' => 3, 'storage_percent' => '26',
            'station' => ['id' => $id, 'tele_station_name' => ['th' => 'สถานีทดสอบ'], 'tele_station_oldcode' => 'X.56',
                'tele_station_lat' => 7.8, 'tele_station_long' => 99.6, 'min_bank' => 15.7],
            'geocode' => ['province_code' => $province, 'province_name' => ['th' => 'ตรัง'], 'amphoe_name' => ['th' => 'ห้วยยอด']],
            'agency' => ['agency_name' => ['th' => 'กรมชลประทาน']]];
    }

    private function payload(): array
    {
        return ['waterlevel_data' => ['result' => 'OK', 'data' => [$this->row(), $this->row('10', 2)]],
            'waterlevel_manual_data' => ['result' => 'OK', 'data' => []]];
    }

    public function test_public_collection_command_shares_one_provider_request_and_saves_only_observed_readings(): void
    {
        Http::fake([PublicWaterService::ENDPOINT => Http::response($this->payload())]);
        Province::create(['code' => '10', 'slug' => 'bangkok', 'name_th' => 'กรุงเทพมหานคร', 'region' => 'central', 'is_active' => true]);
        $this->artisan('flood:sync-public-water')->assertSuccessful();
        $this->artisan('flood:sync-public-water')->assertSuccessful();
        Http::assertSentCount(1);
        $this->assertSame(2, \Illuminate\Support\Facades\DB::table('public_water_readings')->count());
        $this->assertSame(0, WaterStation::count());
    }

    public function test_map_renders_without_provider_requests_and_inactive_province_is_hidden(): void
    {
        $this->get('/trang/water-map')->assertOk()->assertSee('น้ำท่วมใกล้บ้านฉันไหม?')->assertSee('public-water.css', false);
        $this->assertFileExists(public_path('images/water-community-banner.png'));
        Http::assertNothingSent();
        $this->province->update(['is_active' => false]);
        foreach (['water-map', 'water/context.json', 'water/stations.json', 'water/reports.json'] as $path) {
            $this->get('/trang/'.$path)->assertNotFound();
        }
    }

    public function test_real_reference_boundaries_and_area_selectors_cover_all_77_provinces(): void
    {
        $reference = new PublicAreaReference;
        foreach (ProvinceSeeder::PROVINCES as [$code, $slug, $thai]) {
            $province = new Province(['code' => $code, 'slug' => $slug, 'name_th' => $thai]);
            $this->assertSame($code, $reference->boundary($province)['properties']['code'], $thai);
            $this->assertNotEmpty($reference->areas($province), $thai);
        }
        $this->get('/trang/water/context.json')->assertOk()->assertJsonPath('code', '92')->assertJsonCount(10, 'areas');
        $this->assertSame(1, Province::count()); // Reference datasets never seed or overwrite operational areas.
        Http::assertNothingSent();
    }

    public function test_province_station_filters_use_codes_and_share_one_nationwide_cache(): void
    {
        Http::fake([PublicWaterService::ENDPOINT => Http::response($this->payload())]);
        $this->get('/trang/water/stations.json')->assertOk()->assertJsonCount(1, 'stations')
            ->assertJsonPath('stations.0.relative_bank', -7.62)->assertJsonPath('stations.0.unit', 'ม. รทก.')
            ->assertJsonPath('stations.0.trend', -0.02)->assertJsonPath('stale', false);
        $other = Province::create(['code' => '10', 'slug' => 'bangkok', 'name_th' => 'กรุงเทพมหานคร', 'region' => 'central', 'is_active' => true]);
        $this->get('/bangkok/water/stations.json')->assertOk()->assertJsonPath('stations.0.province_code', '10');
        Http::assertSentCount(1);
        $this->assertSame(0, WaterStation::count());
    }

    public function test_missing_values_and_mixed_datums_are_not_fabricated_as_zero_or_bank_depth(): void
    {
        $service = new PublicWaterService;
        $row = $this->row();
        $row['waterlevel_msl'] = null;
        $row['waterlevel_m'] = 2;
        $station = $service->normalize($row);
        $this->assertSame(2.0, $station['value']);
        $this->assertNull($station['relative_bank']);
        $this->assertNull($station['trend']);
        $row['waterlevel_m'] = -999;
        $station = $service->normalize($row);
        $this->assertNull($station['value']);
        $this->assertNull($station['situation']);
        $this->assertSame('#899ba8', $station['color']);
        $row['waterlevel_datetime'] = '2026-02-30 09:30';
        $this->assertNull($service->normalize($row));
    }

    public function test_outage_uses_labeled_recent_stale_cache_then_stops_serving_expired_data(): void
    {
        Http::fake([PublicWaterService::ENDPOINT => Http::sequence()->push($this->payload())->pushStatus(503)->pushStatus(503)]);
        $this->get('/trang/water/stations.json')->assertOk();
        $this->travel(6)->minutes();
        $this->get('/trang/water/stations.json')->assertOk()->assertJsonPath('stale', true)->assertJsonPath('stations.0.color', '#899ba8');
        $this->get('/trang/water/stations.json')->assertOk();
        Http::assertSentCount(2);
        $this->travel(61)->minutes();
        $this->get('/trang/water/stations.json')->assertStatus(503)->assertJsonMissingPath('stations');
    }

    public function test_old_measurements_are_gray_even_if_api_response_is_fresh(): void
    {
        $data = $this->payload();
        $data['waterlevel_data']['data'][0]['waterlevel_datetime'] = '2026-10-02 09:30';
        $data['waterlevel_data']['data'][0]['situation_level'] = 5;
        Http::fake([PublicWaterService::ENDPOINT => Http::response($data)]);
        $this->get('/trang/water/stations.json')->assertOk()->assertJsonPath('stations.0.outdated', true)->assertJsonPath('stations.0.color', '#899ba8');
    }

    public function test_upstream_failure_is_503_not_a_successful_empty_station_set(): void
    {
        Http::fake([PublicWaterService::ENDPOINT => Http::response(['error' => 'secret diagnostics'], 503)]);
        $this->get('/trang/water/stations.json')->assertStatus(503)->assertDontSee('secret diagnostics')->assertJsonMissingPath('stations');
    }

    public function test_nearby_feed_excludes_hidden_expired_dry_old_outside_and_private_fields(): void
    {
        $base = ['province_id' => $this->province->id, 'lat' => 7.8, 'lng' => 99.6, 'level' => 4, 'status' => 'published',
            'expires_at' => now()->addDay(), 'note' => 'PRIVATE NOTE', 'reporter_name' => 'PRIVATE NAME', 'reporter_phone' => '0812345678'];
        $valid = WaterReport::create($base);
        foreach ([['status' => 'pending'], ['status' => 'hidden'], ['level' => 1], ['outside_province' => true], ['expires_at' => now()->subMinute()]] as $change) {
            WaterReport::create(array_merge($base, $change));
        }
        $old = WaterReport::create($base);
        \DB::table('water_reports')->where('id', $old->id)->update(['updated_at' => now()->subHours(13)]);
        $response = $this->get('/trang/water/reports.json')->assertOk()->assertJsonCount(1, 'reports')->assertJsonPath('reports.0.id', $valid->id)
            ->assertDontSee('PRIVATE')->assertDontSee('0812345678');
        $this->assertArrayNotHasKey('reporter_phone', $response->json('reports.0'));
        $this->assertArrayNotHasKey('note', $response->json('reports.0'));
    }
}
