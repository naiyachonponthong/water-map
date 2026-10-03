<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\FieldAction;
use App\Models\HelpRequest;
use App\Models\Province;
use App\Models\Team;
use App\Models\TeamSos;
use App\Models\User;
use App\Support\DispatchService;
use App\Support\HelpRequestService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FieldTest extends TestCase
{
    use RefreshDatabase;

    protected Province $province;

    protected Team $team;

    protected User $leader;

    protected User $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->province = Province::where('slug', 'chachoengsao')->firstOrFail();
        $this->province->update(['command_open' => true, 'web_help_open' => true]);

        $this->dispatcher = $this->user('dispatcher', '0811111111');
        $this->leader = $this->user('team-leader', '0822222222');
        $this->team = Team::create(['province_id' => $this->province->id, 'name' => 'ทีมเรือ', 'type' => 'foundation', 'base_lat' => 13.70, 'base_lng' => 101.07, 'status' => 'available']);
        $this->team->vehicles()->create(['type' => 'boat', 'name' => 'เรือ 1', 'status' => 'ready']);
        $this->team->members()->attach($this->leader->id, ['role_in_team' => 'leader']);
    }

    protected function user(string $role, string $phone): User
    {
        $u = User::create(['name' => $role, 'phone' => $phone, 'password' => 'secret123', 'province_id' => $this->province->id, 'status' => 'active']);
        $u->assignRole($role);

        return $u;
    }

    protected function queuedCase(string $phone = '0812345678'): HelpRequest
    {
        [$case] = app(HelpRequestService::class)->submit($this->province, [
            'lat' => 13.69, 'lng' => 101.07, 'water_level' => 4, 'people_count' => 3,
            'needs' => ['evacuate'], 'requester_name' => 'ผู้แจ้ง', 'requester_phone' => $phone,
        ], ['source' => 'web']);
        app(HelpRequestService::class)->changeStatus($case, 'queued', $this->dispatcher);

        return $case->fresh();
    }

    protected function act(string $type, array $payload, ?string $key = null)
    {
        return $this->actingAs($this->leader)->postJson('/field/api/action', [
            'key' => $key ?? (string) Str::uuid(), 'type' => $type, 'payload' => $payload, 'client_at' => now()->toIso8601String(),
        ]);
    }

    public function test_field_users_land_on_field_app(): void
    {
        $this->actingAs($this->leader)->get('/admin')->assertRedirect(route('field.app'));
        $this->actingAs($this->leader)->get('/field')->assertOk()->assertSee('ทีมเรือ');
    }

    public function test_state_contains_offer_with_phone(): void
    {
        $case = $this->queuedCase();
        app(DispatchService::class)->offer($case, $this->team, $this->dispatcher);

        $this->actingAs($this->leader)->getJson('/field/api/state')
            ->assertOk()
            ->assertJsonPath('jobs.0.status', 'offered')
            ->assertJsonPath('jobs.0.case.phone', '0812345678');
    }

    public function test_same_key_runs_once(): void
    {
        $case = $this->queuedCase();
        $a = app(DispatchService::class)->offer($case, $this->team, $this->dispatcher);
        $key = (string) Str::uuid();

        $this->act('respond', ['assignment_id' => $a->id, 'accept' => 1], $key)->assertJson(['ok' => true]);
        $this->act('respond', ['assignment_id' => $a->id, 'accept' => 1], $key)->assertJson(['ok' => true, 'duplicate' => true]);

        $this->assertSame(1, FieldAction::count());
        $this->assertSame('accepted', $a->fresh()->status);
        $this->assertSame(1, $case->events()->where('to_status', 'accepted')->count());
    }

    public function test_offline_flow_pick_progress_complete(): void
    {
        $case = $this->queuedCase();

        $this->act('pick', ['case_id' => $case->id])->assertJson(['ok' => true]);
        $a = Assignment::firstOrFail();
        $this->act('progress', ['assignment_id' => $a->id, 'step' => 'en_route'])->assertJson(['ok' => true]);
        $this->act('progress', ['assignment_id' => $a->id, 'step' => 'on_site'])->assertJson(['ok' => true]);
        $this->act('water', ['case_id' => $case->id, 'water_level' => 5])->assertJson(['ok' => true]);
        $this->act('complete', ['assignment_id' => $a->id, 'outcome' => 'shelter', 'people_rescued' => 3])->assertJson(['ok' => true]);

        $case->refresh();
        $this->assertSame('rescued', $case->status);
        $this->assertSame(5, $case->water_level);
        $this->assertSame(3, $case->people_rescued);
        $this->assertSame('available', $this->team->fresh()->status);
    }

    public function test_stale_action_returns_reason_not_error(): void
    {
        $case = $this->queuedCase();
        $a = app(DispatchService::class)->offer($case, $this->team, $this->dispatcher);
        app(DispatchService::class)->cancel($a, $this->dispatcher, 'ย้ายให้ทีมอื่น');

        // ทีมกดรับตอนออฟไลน์ แต่ศูนย์ยกเลิกไปแล้ว
        $this->act('respond', ['assignment_id' => $a->id, 'accept' => 1])->assertOk()->assertJson(['ok' => false]);
        $this->assertSame('queued', $case->fresh()->status);
    }

    public function test_other_team_case_rejected(): void
    {
        $case = $this->queuedCase();
        $this->act('water', ['case_id' => $case->id, 'water_level' => 6])->assertJson(['ok' => false]);
    }

    public function test_sos_flow(): void
    {
        $this->act('sos', ['kind' => 'boat_trouble', 'note' => 'เครื่องเรือดับ', 'lat' => 13.7, 'lng' => 101.1])->assertJson(['ok' => true]);
        $sos = TeamSos::firstOrFail();
        $this->assertSame('open', $sos->status);

        // ซ้ำไม่ได้ระหว่างที่ยังเปิด
        $this->act('sos', ['kind' => 'backup'])->assertJson(['ok' => false]);

        $admin = $this->user('province-admin', '0833333333');
        $this->actingAs($admin)->getJson('/admin/live/snapshot')->assertJsonPath('sos_open.0', $sos->id);
        $this->actingAs($admin)->post("/admin/sos/{$sos->id}/ack")->assertRedirect();
        $this->assertSame('ack', $sos->fresh()->status);

        $this->act('sos_safe', [])->assertJson(['ok' => true]);
        $this->assertSame('resolved', $sos->fresh()->status);
    }

    public function test_user_without_team_sees_message(): void
    {
        $lonely = $this->user('team-member', '0844444444');
        $this->actingAs($lonely)->get('/field')->assertOk()->assertSee('ยังไม่ได้อยู่ในทีม');
        $this->actingAs($lonely)->getJson('/field/api/state')->assertForbidden();
    }
}
