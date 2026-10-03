<?php

namespace Tests\Feature;

use App\Models\AreaWaterLevel;
use App\Models\District;
use App\Models\HelpRequest;
use App\Models\Province;
use App\Models\RiskPoint;
use App\Models\Subdistrict;
use App\Models\Team;
use App\Models\User;
use App\Models\VulnerableHousehold;
use App\Support\DispatchService;
use App\Support\HelpRequestService;
use App\Support\RiskEngine;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RiskTest extends TestCase
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

    protected function webCase(float $lat, float $lng, int $level, string $phone = '0812345678'): HelpRequest
    {
        [$case] = app(HelpRequestService::class)->submit($this->province, [
            'lat' => $lat, 'lng' => $lng, 'water_level' => $level, 'people_count' => 2,
            'needs' => ['evacuate'], 'requester_name' => 'ผู้แจ้ง', 'requester_phone' => $phone,
        ], ['source' => 'web']);

        return $case;
    }

    protected function household(array $attrs = []): VulnerableHousehold
    {
        return VulnerableHousehold::create($attrs + [
            'province_id' => $this->province->id, 'head_name' => 'ยายสมใจ', 'phone' => '0898887777',
            'lat' => 13.6905, 'lng' => 101.0705, 'members' => 2, 'conditions' => ['bedridden', 'elderly_alone'], 'is_active' => true,
        ]);
    }

    public function test_risk_point_threatened_by_nearby_case_and_shows_publicly(): void
    {
        $risk = RiskPoint::create([
            'province_id' => $this->province->id, 'type' => 'road', 'name' => 'สะพานคลองท่าไข่', 'lat' => 13.69, 'lng' => 101.07,
            'radius_m' => 500, 'trigger_level' => 3, 'severity' => 'high', 'is_public' => true, 'status' => 'normal', 'review' => 'approved', 'source' => 'staff',
        ]);

        // น้ำต่ำกว่าเกณฑ์ ยังไม่เตือน
        $this->webCase(13.691, 101.07, 2);
        app(RiskEngine::class)->evaluate($this->province);
        $this->assertSame('normal', $risk->fresh()->status);

        // น้ำถึงเกณฑ์ในรัศมี
        $this->webCase(13.6915, 101.0702, 4, '0823456789');
        $r = app(RiskEngine::class)->evaluate($this->province);
        $this->assertSame(1, $r['threatened']);
        $this->assertSame('threatened', $risk->fresh()->status);

        // ไกลเกินรัศมีไม่นับ
        $far = RiskPoint::create([
            'province_id' => $this->province->id, 'type' => 'road', 'name' => 'ไกล', 'lat' => 13.80, 'lng' => 101.20,
            'radius_m' => 300, 'trigger_level' => 2, 'severity' => 'low', 'is_public' => true, 'status' => 'normal', 'review' => 'approved', 'source' => 'staff',
        ]);
        app(RiskEngine::class)->evaluate($this->province);
        $this->assertSame('normal', $far->fresh()->status);

        $this->get('/chachoengsao')->assertOk()->assertSee('สะพานคลองท่าไข่')->assertSee('จุดเสี่ยงที่ควรระวัง');
    }

    public function test_vulnerable_household_gets_one_proactive_case(): void
    {
        $h = $this->household(['lat' => 13.695]);
        $this->webCase(13.69, 101.07, 3);

        $r = app(RiskEngine::class)->evaluate($this->province);
        $this->assertSame(1, $r['cases']);

        $h->refresh();
        $case = $h->openCase;
        $this->assertNotNull($case);
        $this->assertSame('proactive', $case->source);
        $this->assertSame('queued', $case->status);
        $this->assertSame($h->id, $case->household_id);
        $this->assertContains('bedridden', $case->vulnerable);

        // ประเมินซ้ำไม่เปิดเคสซ้ำ และเคสเชิงรุกไม่ทำให้บ้านข้างๆ ลามต่อ
        $this->household(['head_name' => 'ตาสม', 'phone' => '0897776666', 'lat' => 13.702, 'lng' => 101.07]);
        $r2 = app(RiskEngine::class)->evaluate($this->province);
        $this->assertSame(0, $r2['cases']);
        $this->assertSame(1, HelpRequest::where('source', 'proactive')->count());
    }

    public function test_declared_area_level_triggers_household_by_subdistrict(): void
    {
        $district = District::where('province_id', $this->province->id)->firstOrFail();
        $sub = Subdistrict::create(['province_id' => $this->province->id, 'district_id' => $district->id, 'code' => '240199', 'name_th' => 'ทดสอบ', 'center_lat' => 13.75, 'center_lng' => 101.10]);
        $h = $this->household(['lat' => null, 'lng' => null, 'subdistrict_id' => $sub->id, 'district_id' => $district->id]);

        $this->actingAs($this->admin)->post(route('risks.levels.store'), [
            'subdistrict_ids' => [$sub->id], 'level' => 3, 'hours' => 6,
        ])->assertRedirect();

        $this->assertSame(1, AreaWaterLevel::current()->count());
        $this->assertNotNull($h->fresh()->open_case_id);
    }

    public function test_check_safe_sets_cooldown_and_needs_help_opens_case(): void
    {
        $h = $this->household();
        $engine = app(RiskEngine::class);

        $this->actingAs($this->admin)->post(route('vulnerable.check', $h), ['status' => 'safe', 'note' => 'ญาติอยู่ด้วย'])->assertRedirect();
        $this->webCase(13.69, 101.07, 4);
        $this->assertSame(0, $engine->evaluate($this->province)['cases']);

        $this->actingAs($this->admin)->post(route('vulnerable.check', $h), ['status' => 'needs_help'])->assertRedirect();
        $this->assertNotNull($h->fresh()->open_case_id);
        $this->assertSame(2, $h->checks()->count());
    }

    public function test_citizen_proposal_waits_for_review(): void
    {
        $this->post('/chachoengsao/risks/propose', [
            'name' => 'ถนนหน้าวัด', 'type' => 'flood_prone', 'lat' => 13.68, 'lng' => 101.06, 'proposer_phone' => '081-234-5678',
        ])->assertRedirect('/chachoengsao');

        $risk = RiskPoint::firstOrFail();
        $this->assertSame('pending', $risk->review);
        $this->assertSame('0812345678', $risk->proposer_phone);
        $this->get('/chachoengsao')->assertDontSee('ถนนหน้าวัด');

        $this->actingAs($this->admin)->post(route('risks.review', $risk), ['decision' => 'approved'])->assertRedirect();
        $this->get('/chachoengsao')->assertViewHas('risks', fn ($risks) => $risks->contains('name', 'ถนนหน้าวัด'));
    }

    public function test_admin_pages_and_import(): void
    {
        $this->actingAs($this->admin)->get(route('risks.index'))->assertOk()->assertSee('ประกาศระดับน้ำ');
        $this->actingAs($this->admin)->get(route('vulnerable.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('vulnerable.create'))->assertOk();

        $this->actingAs($this->admin)->post(route('risks.store'), [
            'name' => 'โซนท่วมซ้ำซาก', 'type' => 'flood_prone', 'lat' => 13.7, 'lng' => 101.1, 'trigger_level' => 2, 'severity' => 'medium', 'is_public' => 1,
            'zone' => json_encode(['type' => 'Polygon', 'coordinates' => [[[101.0, 13.6], [101.2, 13.6], [101.2, 13.8], [101.0, 13.8], [101.0, 13.6]]]]),
        ])->assertRedirect();
        $zone = RiskPoint::where('name', 'โซนท่วมซ้ำซาก')->firstOrFail();
        $this->assertTrue($zone->covers(13.75, 101.15));
        $this->assertFalse($zone->covers(13.9, 101.15));

        $csv = "\xEF\xBB\xBFชื่อ,เบอร์,ละติจูด,ลองจิจูด,จำนวนคน,ภาวะ,ยินยอม\nนายเอ,0811112222,13.70,101.08,3,ผู้ป่วยติดเตียง,ใช่\n,0800000000,13.7,101.0,1,อื่นๆ,\nนายเอ,0811112222,13.70,101.08,3,ผู้ป่วยติดเตียง,ใช่\n";
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('h.csv', $csv);
        $this->actingAs($this->admin)->post(route('vulnerable.import'), ['file' => $file])->assertRedirect();
        $this->assertSame(1, VulnerableHousehold::count());
        $this->assertNotNull(VulnerableHousehold::first()->consent_at);
        $this->assertSame(['bedridden'], VulnerableHousehold::first()->conditions);
    }

    public function test_field_team_records_household_check(): void
    {
        $h = $this->household();
        $this->webCase(13.69, 101.07, 3);
        app(RiskEngine::class)->evaluate($this->province);
        $case = $h->fresh()->openCase;

        $leader = User::create(['name' => 'หัวหน้า', 'phone' => '0855555555', 'password' => 'secret123', 'province_id' => $this->province->id, 'status' => 'active']);
        $leader->assignRole('team-leader');
        $team = Team::create(['province_id' => $this->province->id, 'name' => 'ทีมเรือ', 'type' => 'foundation', 'base_lat' => 13.70, 'base_lng' => 101.07, 'status' => 'available']);
        $team->members()->attach($leader->id, ['role_in_team' => 'leader']);
        app(DispatchService::class)->offer($case, $team, $this->admin, 'dispatch', true);

        $state = $this->actingAs($leader)->getJson('/field/api/state')->assertOk()->json();
        $this->assertSame('ยายสมใจ', $state['jobs'][0]['case']['household']['name']);
        $this->assertTrue($state['jobs'][0]['case']['proactive']);
        $this->assertArrayHasKey('risks', $state);

        $this->actingAs($leader)->postJson('/field/api/action', [
            'key' => (string) Str::uuid(), 'type' => 'household_check', 'payload' => ['case_id' => $case->id, 'status' => 'safe', 'note' => 'ยาพอ'],
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertSame('safe', $h->fresh()->last_check_status);
        $this->assertSame($team->id, $h->checks()->first()->team_id);
    }
}
