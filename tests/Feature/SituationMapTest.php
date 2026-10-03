<?php

namespace Tests\Feature;

use App\Models\Province;
use App\Models\WaterReport;
use App\Support\Attribution;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SituationMapTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    public function test_map_renders_scoped_data_links_filters_and_credit_without_provider_call(): void
    {
        $this->seed(DatabaseSeeder::class);
        Http::preventStrayRequests();
        $response = $this->get('/trang/map')->assertOk()->assertViewIs('public.reports.map');
        foreach (['situation-water.js', 'situation-map.css', 'situation-riverside-hero.png', 'ภาพประกอบ ไม่ใช่สถานการณ์จริง', '/trang/guide#map',
            'ระดับน้ำจาก ThaiWater', 'รายงานความลึกน้ำจากประชาชน', 'จังหวัดตรัง', 'ดูทั้งจังหวัด',
            'ศูนย์พักพิง', 'กล้อง CCTV', 'data-creator-credit', Attribution::AUTHOR] as $text) {
            $response->assertSee($text, false);
        }
        $response->assertSee('center: [13.5, 101]', false)->assertDontSee('center: [13.7563', false);
        $response->assertSee('name="author" content="'.Attribution::AUTHOR.'"', false);
        Http::assertNothingSent();
        $this->assertFileExists(public_path('images/situation-riverside-hero.png'));
        foreach (['/trang/water/context.json', '/trang/water/stations.json'] as $url) {
            $this->assertStringContainsString($url, str_replace('\\/', '/', $response->getContent()));
        }
        $bangkok = $this->get('/bangkok/map')->assertOk();
        foreach (['/bangkok/water/context.json', '/bangkok/water/stations.json'] as $url) {
            $this->assertStringContainsString($url, str_replace('\\/', '/', $bangkok->getContent()));
        }
        Province::where('slug', 'trang')->update(['is_active' => false]);
        $this->get('/trang/map')->assertNotFound();
    }

    public function test_outside_province_report_is_not_counted_or_exposed_on_map(): void
    {
        $this->seed(DatabaseSeeder::class);
        $province = Province::where('slug', 'trang')->firstOrFail();
        WaterReport::create(['province_id' => $province->id, 'lat' => 13.7, 'lng' => 100.5, 'level' => 5,
            'source' => 'web', 'status' => 'published', 'outside_province' => true, 'expires_at' => now()->addHours(4)]);
        $this->get('/trang/map')->assertOk()->assertViewHas('summary', fn ($value) => $value['reports'] === 0 && $value['deep'] === 0);
        $this->getJson('/trang/map.geojson')->assertOk()->assertJsonCount(0, 'reports.features');
    }

    public function test_public_home_and_login_show_creator_credit(): void
    {
        $this->seed(DatabaseSeeder::class);
        foreach (['/trang', '/login'] as $url) {
            $this->get($url)->assertOk()->assertSee('data-creator-credit', false)->assertSee(Attribution::AUTHOR);
        }
        $this->artisan('flood:credits-check')->expectsOutputToContain(Attribution::AUTHOR)->assertExitCode(0);
    }
}
