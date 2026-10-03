<?php

namespace Tests\Feature;

use App\Models\Province;
use App\Models\RiskPoint;
use App\Models\Team;
use App\Models\User;
use App\Models\WaterReport;
use App\Support\Settings;
use App\Support\WaterReportService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    protected Province $province;

    protected User $moderator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->province = Province::where('slug', 'chachoengsao')->firstOrFail();
        $this->province->update(['command_open' => true, 'web_help_open' => true]);
        $this->moderator = User::create(['name' => 'ผู้ตรวจ', 'phone' => '0811111111', 'password' => 'secret123', 'province_id' => $this->province->id, 'status' => 'active']);
        $this->moderator->assignRole('moderator');
    }

    protected function report(float $lat, float $lng, int $level, string $device): WaterReport
    {
        [$r] = app(WaterReportService::class)->submit($this->province, ['lat' => $lat, 'lng' => $lng, 'level' => $level], ['source' => 'web', 'device_hash' => hash('sha256', $device)]);

        return $r;
    }

    public function test_citizen_report_shows_on_public_map(): void
    {
        $this->get('/chachoengsao/report')->assertOk()->assertSee('น้ำสูงแค่ไหน');
        $this->get('/chachoengsao/map')->assertOk()->assertSee('แผนที่สถานการณ์น้ำ');

        $this->post('/chachoengsao/report', [
            'lat' => 13.69, 'lng' => 101.07, 'level' => 3, 'trend' => 'rising', 'note' => 'ถนนหน้าตลาด', 'reporter_phone' => '081-234-5678',
        ])->assertRedirect();

        $r = WaterReport::firstOrFail();
        $this->assertSame('published', $r->status);
        $this->assertSame('0812345678', $r->reporter_phone);
        $this->assertFalse($r->isTrusted());

        $json = $this->getJson('/chachoengsao/map.geojson')->assertOk()->json();
        $this->assertCount(1, $json['reports']['features']);
        $this->assertArrayNotHasKey('reporter_phone', $json['reports']['features'][0]['properties']);
    }

    public function test_extremely_low_gps_accuracy_does_not_block_a_report(): void
    {
        $this->post('/chachoengsao/report', [
            'lat' => 13.69, 'lng' => 101.07, 'accuracy_m' => 200000, 'level' => 3,
        ])->assertRedirect();

        $this->assertNull(WaterReport::firstOrFail()->accuracy_m);
    }

    public function test_same_device_updates_and_nearby_devices_corroborate(): void
    {
        $a = $this->report(13.69, 101.07, 3, 'device-a');
        [$again, $updated] = app(WaterReportService::class)->submit($this->province, ['lat' => 13.6902, 'lng' => 101.0701, 'level' => 4], ['source' => 'web', 'device_hash' => hash('sha256', 'device-a')]);
        $this->assertTrue($updated);
        $this->assertSame($a->id, $again->id);
        $this->assertSame(4, $again->level);
        $this->assertSame(1, WaterReport::count());

        $b = $this->report(13.6915, 101.0705, 3, 'device-b');
        $this->assertSame(1, $b->confirm_count);
        $this->assertSame(1, $a->fresh()->confirm_count);
        $this->assertTrue($a->fresh()->isTrusted());

        // ห่างเกิน 300 ม. ไม่นับยืนยัน
        $c = $this->report(13.70, 101.07, 3, 'device-c');
        $this->assertSame(0, $c->confirm_count);
    }

    public function test_votes_one_per_device_and_wrong_votes_hide(): void
    {
        $r = $this->report(13.69, 101.07, 3, 'owner');

        $s = app(WaterReportService::class);
        $this->assertFalse($s->vote($r, hash('sha256', 'owner'), 'confirm')['ok']);
        $this->assertTrue($s->vote($r, 'v1', 'confirm')['ok']);
        $this->assertFalse($s->vote($r, 'v1', 'wrong')['ok']);
        $this->assertSame(1, $r->fresh()->confirm_count);

        $s->vote($r, 'v2', 'wrong');
        $s->vote($r, 'v3', 'wrong');
        $s->vote($r, 'v4', 'wrong');
        $this->assertSame('pending', $r->fresh()->status);

        $this->actingAs($this->moderator)->post(route('reports.moderate', $r), ['action' => 'verify'])->assertRedirect();
        $this->assertTrue($r->fresh()->verified);
        $this->assertSame('published', $r->fresh()->status);
    }

    public function test_receded_votes_expire_soon_and_http_vote_works(): void
    {
        $r = $this->report(13.69, 101.07, 3, 'owner');
        $this->postJson("/chachoengsao/reports/{$r->id}/vote", ['kind' => 'receded'])->assertOk()->assertJson(['ok' => true]);
        app(WaterReportService::class)->vote($r->fresh(), 'v2', 'receded');

        $r->refresh();
        $this->assertSame('falling', $r->trend);
        $this->assertTrue($r->expires_at->lte(now()->addHour()->addMinute()));
    }

    public function test_only_trusted_reports_feed_risk_engine(): void
    {
        $risk = RiskPoint::create([
            'province_id' => $this->province->id, 'type' => 'road', 'name' => 'ถนนเลียบคลอง', 'lat' => 13.69, 'lng' => 101.07,
            'radius_m' => 500, 'trigger_level' => 3, 'severity' => 'high', 'is_public' => true, 'status' => 'normal', 'review' => 'approved', 'source' => 'staff',
        ]);

        $this->report(13.6905, 101.07, 4, 'd1');
        $this->assertSame('normal', $risk->fresh()->status);

        // คนที่สองแจ้งใกล้กัน = ยืนยันกัน ระบบเตือนทำงานทันที
        $this->report(13.691, 101.0702, 4, 'd2');
        $this->assertSame('threatened', $risk->fresh()->status);
        $this->assertStringContainsString('รายงาน', $risk->fresh()->threat_reason);
    }

    public function test_premoderate_and_closed_switches(): void
    {
        Settings::set('report_premoderate', true, $this->province->id);
        $r = $this->report(13.69, 101.07, 2, 'x');
        $this->assertSame('pending', $r->status);
        $this->assertCount(0, $this->getJson('/chachoengsao/map.geojson')->json('reports.features'));

        $this->actingAs($this->moderator)->get(route('reports.index'))->assertOk()->assertSee('รอตรวจ');

        Settings::set('reports_open', false, $this->province->id);
        $this->post('/chachoengsao/report', ['lat' => 13.69, 'lng' => 101.07, 'level' => 3])->assertRedirect();
        $this->assertSame(1, WaterReport::count());
    }

    public function test_team_report_is_verified(): void
    {
        $leader = User::create(['name' => 'หัวหน้า', 'phone' => '0855555555', 'password' => 'secret123', 'province_id' => $this->province->id, 'status' => 'active']);
        $leader->assignRole('team-leader');
        $team = Team::create(['province_id' => $this->province->id, 'name' => 'ทีมเรือ', 'type' => 'foundation', 'base_lat' => 13.70, 'base_lng' => 101.07, 'status' => 'available']);
        $team->members()->attach($leader->id, ['role_in_team' => 'leader']);

        $this->actingAs($leader)->postJson('/field/api/action', [
            'key' => (string) Str::uuid(), 'type' => 'report', 'payload' => ['lat' => 13.7, 'lng' => 101.08, 'level' => 5, 'trend' => 'rising'],
        ])->assertOk()->assertJson(['ok' => true]);

        $r = WaterReport::firstOrFail();
        $this->assertSame('team', $r->source);
        $this->assertTrue($r->verified);
        $this->assertSame($team->id, $r->team_id);
        $this->assertNotEmpty($this->actingAs($leader)->getJson('/field/api/state')->json('reports'));
    }
}
