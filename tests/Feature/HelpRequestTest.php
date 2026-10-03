<?php

namespace Tests\Feature;

use App\Models\HelpRequest;
use App\Models\Province;
use App\Models\User;
use App\Support\HelpRequestService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpRequestTest extends TestCase
{
    use RefreshDatabase;

    protected Province $province;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->province = Province::where('slug', 'chachoengsao')->firstOrFail();
        $this->province->update(['command_open' => true, 'web_help_open' => true]);
    }

    protected function payload(array $over = []): array
    {
        return array_replace([
            'lat' => 13.6904, 'lng' => 101.0780, 'location_source' => 'gps',
            'water_level' => 4, 'people_count' => 3,
            'vulnerable' => ['bedridden'], 'needs' => ['evacuate'],
            'requester_name' => 'สมศรี', 'requester_phone' => '081-234-5678',
        ], $over);
    }

    public function test_form_closed_when_web_help_off(): void
    {
        $this->province->update(['web_help_open' => false]);
        $this->get('/chachoengsao/help')->assertOk()->assertSee('ยังไม่รับแจ้งทางเว็บ');
        $this->post('/chachoengsao/help', $this->payload())->assertRedirect();
        $this->assertSame(0, HelpRequest::count());
    }

    public function test_submit_creates_case_with_code_and_priority(): void
    {
        $res = $this->post('/chachoengsao/help', $this->payload());
        $case = HelpRequest::firstOrFail();

        $res->assertRedirect($case->trackUrl());
        $this->assertMatchesRegularExpression('/^FL24-\d{4}-0001$/', $case->code);
        $this->assertSame('new', $case->status);
        // ขั้น 4 × 10 + ติดเตียง 30 = 70 → สูง
        $this->assertSame(70, $case->priority_score);
        $this->assertSame('high', $case->priority);
        $this->assertSame('0812345678', $case->requester_phone);
        $this->assertSame('5678', $case->phone_last4);
        $this->assertCount(1, $case->events);
    }

    public function test_same_phone_is_attached_to_open_case(): void
    {
        $this->post('/chachoengsao/help', $this->payload());
        $this->post('/chachoengsao/help', $this->payload(['water_level' => 6, 'needs' => ['food'], 'requester_phone' => '0812345678']));

        $this->assertSame(1, HelpRequest::count());
        $case = HelpRequest::first();
        $this->assertSame(6, $case->water_level);
        $this->assertSame(2, $case->report_count);
        $this->assertEqualsCanonicalizing(['evacuate', 'food'], $case->needs);
        // น้ำมิดหัว + ติดเตียง = วิกฤตเสมอ
        $this->assertSame('critical', $case->priority);
    }

    public function test_nearby_different_phone_is_flagged_not_merged(): void
    {
        $this->post('/chachoengsao/help', $this->payload());
        $this->post('/chachoengsao/help', $this->payload(['requester_phone' => '0899999999', 'lat' => 13.6905]));

        $this->assertSame(2, HelpRequest::count());
        $second = HelpRequest::latest('id')->first();
        $this->assertSame(HelpRequest::oldest('id')->first()->id, $second->possible_duplicate_of_id);
    }

    public function test_track_page_requires_signature(): void
    {
        $this->post('/chachoengsao/help', $this->payload());
        $case = HelpRequest::first();

        $this->get('/track/'.$case->code)->assertForbidden();
        $this->get($case->trackUrl())->assertOk()->assertSee($case->code)->assertDontSee('081-234-5678');
    }

    public function test_requester_can_mark_safe(): void
    {
        $this->post('/chachoengsao/help', $this->payload());
        $case = HelpRequest::first();

        $this->post($case->signedAction('public.track.safe'))->assertRedirect();
        $case->refresh();
        $this->assertSame('closed', $case->status);
        $this->assertSame('self_safe', $case->outcome);
    }

    public function test_lookup_by_code_and_last4(): void
    {
        $this->post('/chachoengsao/help', $this->payload());
        $case = HelpRequest::first();

        $this->post('/track', ['code' => strtolower($case->code), 'last4' => '5678'])->assertRedirect($case->trackUrl());
        $this->post('/track', ['code' => $case->code, 'last4' => '0000'])->assertSessionHasErrors('code');
    }

    public function test_dispatcher_screens_and_merges(): void
    {
        $user = User::create(['name' => 'สั่งการ', 'phone' => '0811111111', 'password' => 'secret123', 'province_id' => $this->province->id, 'status' => 'active']);
        $user->assignRole('dispatcher');

        $this->post('/chachoengsao/help', $this->payload());
        $this->post('/chachoengsao/help', $this->payload(['requester_phone' => '0899999999']));
        [$a, $b] = HelpRequest::orderBy('id')->get();

        $this->actingAs($user)->post("/admin/cases/{$a->id}/screen", ['action' => 'pass'])->assertRedirect();
        $this->assertSame('queued', $a->fresh()->status);

        $this->actingAs($user)->post("/admin/cases/{$b->id}/merge", ['into' => $a->code])->assertRedirect(route('cases.show', $a));
        $this->assertSame('merged', $b->fresh()->status);
        $this->assertSame(2, $a->fresh()->report_count);
    }

    public function test_staff_key_in_goes_to_queue(): void
    {
        $user = User::create(['name' => 'สั่งการ', 'phone' => '0811111111', 'password' => 'secret123', 'province_id' => $this->province->id, 'status' => 'active']);
        $user->assignRole('dispatcher');

        $this->actingAs($user)->post('/admin/cases', $this->payload(['source' => 'phone', 'needs' => [], 'queue_now' => 1]))->assertRedirect();

        $case = HelpRequest::firstOrFail();
        $this->assertSame('phone', $case->source);
        $this->assertSame('queued', $case->status);
        $this->assertSame($user->id, $case->created_by);
    }

    public function test_waiting_time_raises_score(): void
    {
        $this->post('/chachoengsao/help', $this->payload(['vulnerable' => []]));
        $case = HelpRequest::first();
        $base = $case->priority_score;

        $this->travel(3)->hours();
        $this->artisan('flood:recalc-priority')->assertSuccessful();

        $this->assertSame($base + 15, $case->fresh()->priority_score);
        $this->assertSame($base + 15, HelpRequestService::score($case->fresh()));
    }
}
