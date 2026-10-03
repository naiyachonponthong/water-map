<?php

namespace Tests\Feature;

use App\Events\CaseChanged;
use App\Models\HelpRequest;
use App\Models\Province;
use App\Models\User;
use App\Support\HelpRequestService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class LiveTest extends TestCase
{
    use RefreshDatabase;

    protected Province $province;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->province = Province::where('slug', 'chachoengsao')->firstOrFail();
        $this->province->update(['command_open' => true, 'web_help_open' => true]);
        $this->admin = User::create(['name' => 'ผอ.', 'phone' => '0811111111', 'password' => 'secret123', 'province_id' => $this->province->id, 'status' => 'active']);
        $this->admin->assignRole('province-admin');
    }

    protected function makeCase(array $vulnerable = []): HelpRequest
    {
        [$case] = app(HelpRequestService::class)->submit($this->province, [
            'lat' => 13.69, 'lng' => 101.07, 'water_level' => 6, 'people_count' => 2, 'vulnerable' => $vulnerable,
            'needs' => ['evacuate'], 'requester_name' => 'ผู้แจ้ง', 'requester_phone' => '0812345678',
        ], ['source' => 'web']);

        return $case;
    }

    public function test_snapshot_returns_stats_map_and_html(): void
    {
        $this->makeCase(['bedridden']);

        $res = $this->actingAs($this->admin)->getJson('/admin/live/snapshot')->assertOk();
        $res->assertJsonPath('stats.triage', 1)->assertJsonPath('stats.critical_open', 1);
        $this->assertCount(1, $res->json('cases'));
        $this->assertStringContainsString('FL24', $res->json('urgent_html'));
        $this->assertStringContainsString('FL24', $res->json('feed_html'));
    }

    public function test_snapshot_reports_new_critical_since_last_event(): void
    {
        // เคสแรกไม่วิกฤต (เบอร์อื่น)
        app(HelpRequestService::class)->submit($this->province, [
            'lat' => 13.80, 'lng' => 101.20, 'water_level' => 2, 'people_count' => 1,
            'needs' => ['food'], 'requester_name' => 'ก', 'requester_phone' => '0899999999',
        ], ['source' => 'web']);
        $first = $this->actingAs($this->admin)->getJson('/admin/live/snapshot')->json('last_event_id');
        $this->assertGreaterThan(0, $first);

        $this->makeCase(['bedridden']);

        $this->actingAs($this->admin)->getJson('/admin/live/snapshot?since='.$first)
            ->assertJsonCount(1, 'new_critical');
    }

    public function test_tv_and_dashboard_render(): void
    {
        $this->makeCase();
        $this->actingAs($this->admin)->get('/admin/tv')->assertOk()->assertSee('ศูนย์สั่งการน้ำท่วม');
        $this->actingAs($this->admin)->get('/admin')->assertOk()->assertSee('แผนที่สด');
    }

    public function test_team_member_cannot_open_snapshot(): void
    {
        $member = User::create(['name' => 'ทีม', 'phone' => '0822222222', 'password' => 'secret123', 'province_id' => $this->province->id, 'status' => 'active']);
        $member->assignRole('team-member');

        $this->actingAs($member)->getJson('/admin/live/snapshot')->assertForbidden();
    }

    public function test_case_event_broadcasts_when_reverb_enabled(): void
    {
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'k']);
        Event::fake([CaseChanged::class]);

        $this->makeCase();

        Event::assertDispatched(CaseChanged::class, fn ($e) => $e->type === 'created');
    }

    public function test_unreachable_reverb_does_not_break_submission(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'k',
            'broadcasting.connections.reverb.secret' => 's',
            'broadcasting.connections.reverb.app_id' => 'a',
            'broadcasting.connections.reverb.options.host' => '127.0.0.1',
            'broadcasting.connections.reverb.options.port' => 1,
            'broadcasting.connections.reverb.options.scheme' => 'http',
            'broadcasting.connections.reverb.options.useTLS' => false,
        ]);

        $this->post('/chachoengsao/help', [
            'lat' => 13.69, 'lng' => 101.07, 'water_level' => 3, 'people_count' => 1,
            'needs' => ['food'], 'requester_name' => 'ทดสอบ', 'requester_phone' => '0899999999',
        ])->assertRedirect();

        $this->assertSame(1, HelpRequest::count());
    }
}
