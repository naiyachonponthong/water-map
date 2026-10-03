<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\HelpRequest;
use App\Models\Province;
use App\Models\Team;
use App\Models\User;
use App\Support\DispatchService;
use App\Support\HelpRequestService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DispatchTest extends TestCase
{
    use RefreshDatabase;

    protected Province $province;

    protected User $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->province = Province::where('slug', 'chachoengsao')->firstOrFail();
        $this->province->update(['command_open' => true, 'web_help_open' => true]);
        $this->dispatcher = $this->user('dispatcher', '0811111111');
    }

    protected function user(string $role, string $phone): User
    {
        $u = User::create(['name' => $role, 'phone' => $phone, 'password' => 'secret123', 'province_id' => $this->province->id, 'status' => 'active']);
        $u->assignRole($role);

        return $u;
    }

    protected function makeCase(int $water = 4, string $phone = '0812345678'): HelpRequest
    {
        [$case] = app(HelpRequestService::class)->submit($this->province, [
            'lat' => 13.6904, 'lng' => 101.0780, 'water_level' => $water, 'people_count' => 2,
            'needs' => ['evacuate'], 'requester_name' => 'ผู้แจ้ง', 'requester_phone' => $phone,
        ], ['source' => 'web']);
        app(HelpRequestService::class)->changeStatus($case, 'queued', $this->dispatcher);

        return $case->fresh();
    }

    protected function team(string $name, array $vehicles, float $lat): Team
    {
        $t = Team::create(['province_id' => $this->province->id, 'name' => $name, 'type' => 'foundation', 'base_lat' => $lat, 'base_lng' => 101.0780, 'status' => 'available']);
        foreach ($vehicles as $type) {
            $t->vehicles()->create(['type' => $type, 'name' => $type, 'status' => 'ready']);
        }

        return $t;
    }

    public function test_suggest_prefers_team_with_boat_for_deep_water(): void
    {
        $near = $this->team('ใกล้แต่ไม่มีเรือ', ['pickup'], 13.6910);
        $boat = $this->team('มีเรือ', ['boat'], 13.75);

        $s = app(DispatchService::class)->suggest($this->makeCase(5));

        $this->assertSame($boat->id, $s->first()['team']->id);
        $this->assertFalse($s->firstWhere('team.id', $near->id)['fit']);
    }

    public function test_full_flow_offer_accept_progress_complete(): void
    {
        $case = $this->makeCase();
        $team = $this->team('ทีมเรือ', ['boat'], 13.70);
        $leader = $this->user('team-leader', '0822222222');
        $team->members()->attach($leader->id, ['role_in_team' => 'leader']);

        $this->actingAs($this->dispatcher)->post("/admin/cases/{$case->id}/assign", ['team_id' => $team->id, 'mode' => 'offer'])->assertRedirect();
        $a = Assignment::firstOrFail();
        $this->assertSame('offered', $case->fresh()->status);

        $this->actingAs($leader)->post("/admin/assignments/{$a->id}/respond", ['accept' => 1])->assertRedirect();
        $this->assertSame('accepted', $case->fresh()->status);
        $this->assertSame('busy', $team->fresh()->status);

        $this->actingAs($leader)->post("/admin/assignments/{$a->id}/progress", ['step' => 'en_route']);
        $this->actingAs($leader)->post("/admin/assignments/{$a->id}/progress", ['step' => 'on_site']);
        $this->assertSame('on_site', $case->fresh()->status);

        $this->actingAs($leader)->post("/admin/assignments/{$a->id}/complete", ['outcome' => 'shelter', 'people_rescued' => 2])->assertRedirect();
        $case->refresh();
        $this->assertSame('rescued', $case->status);
        $this->assertSame(2, $case->people_rescued);
        $this->assertSame('done', $a->fresh()->status);
        $this->assertSame('available', $team->fresh()->status);
    }

    public function test_decline_returns_case_to_queue(): void
    {
        $case = $this->makeCase();
        $team = $this->team('ทีม', ['boat'], 13.70);
        $a = app(DispatchService::class)->offer($case, $team, $this->dispatcher);

        $this->actingAs($this->dispatcher)->post("/admin/assignments/{$a->id}/respond", ['accept' => 0, 'reason' => 'full']);

        $case->refresh();
        $this->assertSame('queued', $case->status);
        $this->assertNull($case->team_id);
        $this->assertSame('เรือ/รถเต็ม', $a->fresh()->decline_reason);
    }

    public function test_offer_expires_after_timeout(): void
    {
        $case = $this->makeCase();
        $a = app(DispatchService::class)->offer($case, $this->team('ทีม', ['boat'], 13.70), $this->dispatcher);

        $this->travel(11)->minutes();
        $this->artisan('flood:expire-offers')->assertSuccessful();

        $this->assertSame('expired', $a->fresh()->status);
        $this->assertSame('queued', $case->fresh()->status);
    }

    public function test_requester_safe_closes_team_job_and_frees_team(): void
    {
        $case = $this->makeCase();
        $team = $this->team('ทีม', ['boat'], 13.70);
        $a = app(DispatchService::class)->offer($case, $team, $this->dispatcher, 'phone', true);
        $this->assertSame('busy', $team->fresh()->status);

        $this->post($case->fresh()->signedAction('public.track.safe'));

        $this->assertSame('cancelled', $a->fresh()->status);
        $this->assertSame('available', $team->fresh()->status);
    }

    public function test_other_team_cannot_touch_assignment(): void
    {
        $case = $this->makeCase();
        $a = app(DispatchService::class)->offer($case, $this->team('ทีม A', ['boat'], 13.70), $this->dispatcher);
        $other = $this->team('ทีม B', ['boat'], 13.71);
        $member = $this->user('team-member', '0833333333');
        $other->members()->attach($member->id, ['role_in_team' => 'member']);

        $this->actingAs($member)->post("/admin/assignments/{$a->id}/respond", ['accept' => 1])->assertForbidden();
    }

    public function test_team_can_self_pick_queued_case(): void
    {
        $case = $this->makeCase();
        $team = $this->team('ทีม', ['boat'], 13.70);
        $leader = $this->user('team-leader', '0822222222');
        $team->members()->attach($leader->id, ['role_in_team' => 'leader']);

        $this->actingAs($leader)->get('/admin/my-team')->assertOk()->assertSee($case->requester_name);
        $this->actingAs($leader)->post("/admin/my-team/pick/{$case->id}")->assertRedirect();

        $this->assertSame('accepted', $case->fresh()->status);
        $this->assertSame('self', Assignment::first()->via);
    }

    public function test_dispatch_board_renders(): void
    {
        $this->makeCase();
        $this->actingAs($this->dispatcher)->get('/admin/dispatch')->assertOk()->assertSee('รอทีม');
    }
}
