<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\Evacuee;
use App\Models\HelpRequest;
use App\Models\Province;
use App\Models\Shelter;
use App\Models\Team;
use App\Models\User;
use App\Support\HelpRequestService;
use App\Support\ReportService;
use App\Support\ShelterService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportTest extends TestCase
{
    use RefreshDatabase;

    protected Province $province;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->province = Province::where('slug', 'chachoengsao')->firstOrFail();
        $this->province->update(['command_open' => true, 'web_help_open' => true, 'command_opened_at' => now()->subDays(2)]);
        $this->admin = User::create(['name' => 'ผอ.', 'phone' => '0811111111', 'password' => 'secret123', 'province_id' => $this->province->id, 'status' => 'active']);
        $this->admin->assignRole('province-admin');
    }

    protected function caseWithJob(int $acceptAfter, int $arriveAfter, string $phone = '0812345678'): HelpRequest
    {
        [$case] = app(HelpRequestService::class)->submit($this->province, [
            'lat' => 13.69, 'lng' => 101.07, 'water_level' => 4, 'people_count' => 3, 'needs' => ['evacuate'],
            'requester_name' => '=HYPERLINK("x")', 'requester_phone' => $phone,
        ], ['source' => 'web']);
        $team = Team::firstOrCreate(['province_id' => $this->province->id, 'name' => 'ทีมเรือ'], ['type' => 'foundation', 'status' => 'available']);
        $t0 = $case->created_at;
        Assignment::create([
            'help_request_id' => $case->id, 'team_id' => $team->id, 'status' => 'done', 'via' => 'dispatch', 'people_rescued' => 3,
            'offered_at' => $t0, 'responded_at' => $t0->copy()->addMinutes($acceptAfter), 'arrived_at' => $t0->copy()->addMinutes($arriveAfter), 'done_at' => $t0->copy()->addMinutes($arriveAfter + 10),
        ]);
        $case->forceFill(['status' => 'rescued', 'outcome' => 'shelter', 'people_rescued' => 3, 'closed_at' => now(), 'team_id' => $team->id])->save();

        return $case;
    }

    public function test_report_numbers_and_pages(): void
    {
        $this->caseWithJob(5, 30, '0811000001');
        $this->caseWithJob(10, 50, '0811000002');
        $this->caseWithJob(15, 70, '0811000003');

        $r = app(ReportService::class)->build($this->province, now()->subDay(), now()->addMinute());
        $this->assertSame(3, $r['cases']['total']);
        $this->assertSame(9, $r['cases']['people_rescued']);
        $this->assertSame(50.0, $r['response']['arrive']['median']);
        $this->assertSame(10.0, $r['response']['accept']['median']);
        $this->assertSame(3, $r['teams'][0]['done']);

        $this->actingAs($this->admin)->get(route('exports.index'))->assertOk()->assertSee('ทีมเรือ');
        $this->actingAs($this->admin)->get(route('exports.sitrep'))->assertOk()->assertSee('รายงานสถานการณ์อุทกภัย');
    }

    public function test_stats_percentiles(): void
    {
        $s = app(ReportService::class)->stats([10, 1, 5, 7, 3, 9, 2, 8, 4, 6]);
        $this->assertSame(5, $s['median']);
        $this->assertSame(9, $s['p90']);
        $this->assertSame(6, $s['avg']);
        $this->assertNull(app(ReportService::class)->stats([])['median']);
    }

    public function test_csv_masks_phone_escapes_formula_and_logs(): void
    {
        $this->caseWithJob(5, 30);

        $res = $this->actingAs($this->admin)->get(route('exports.download', ['dataset' => 'cases']));
        $res->assertOk();
        $csv = $res->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('0xx-xxx-5678', $csv);
        $this->assertStringNotContainsString('0812345678', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);

        $full = $this->actingAs($this->admin)->get(route('exports.download', ['dataset' => 'cases', 'phones' => 1]))->streamedContent();
        $this->assertStringContainsString('0812345678', $full);
        $this->assertSame(2, AuditLog::where('action', 'exported')->count());
    }

    public function test_dataset_permissions(): void
    {
        $mod = User::create(['name' => 'ผู้ตรวจ', 'phone' => '0822222222', 'password' => 'secret123', 'province_id' => $this->province->id, 'status' => 'active']);
        $mod->assignRole('moderator');
        $mod->givePermissionTo('exports.view');

        $this->actingAs($mod)->get(route('exports.download', ['dataset' => 'evacuees']))->assertForbidden();
        $this->actingAs($mod)->get(route('exports.download', ['dataset' => 'nope']))->assertNotFound();
        $csv = $this->actingAs($mod)->get(route('exports.download', ['dataset' => 'reports', 'phones' => 1]))->assertOk()->streamedContent();
        $this->assertStringContainsString('ระดับน้ำ', $csv);
    }

    public function test_open_data_has_no_personal_data(): void
    {
        $this->caseWithJob(5, 30);
        $shelter = Shelter::create(['province_id' => $this->province->id, 'name' => 'วัดโสธร', 'type' => 'temple', 'lat' => 13.67, 'lng' => 101.07, 'capacity' => 100, 'status' => 'open', 'is_public' => true]);
        app(ShelterService::class)->register($shelter, [['name' => 'สมชาย ใจดี', 'phone' => '0899999999']]);

        $res = $this->getJson('/chachoengsao/open-data.json')->assertOk()->assertHeader('Access-Control-Allow-Origin', '*');
        $body = $res->getContent();
        $this->assertSame(1, $res->json('evacuees_now'));
        $this->assertSame('วัดโสธร', $res->json('shelters.0.name'));
        foreach (['สมชาย', '0899999999', '0812345678', 'HYPERLINK'] as $pii) {
            $this->assertStringNotContainsString($pii, $body);
        }
    }

    public function test_prune_removes_old_phones(): void
    {
        $case = $this->caseWithJob(5, 30);
        $case->forceFill(['closed_at' => now()->subDays(200)])->save();
        $shelter = Shelter::create(['province_id' => $this->province->id, 'name' => 'วัด', 'type' => 'temple', 'lat' => 13.67, 'lng' => 101.07, 'status' => 'open']);
        $e = app(ShelterService::class)->register($shelter, [['name' => 'ก', 'phone' => '0899999999']])->first();
        $e->update(['status' => 'out', 'checked_out_at' => now()->subDays(200)]);
        $fresh = app(ShelterService::class)->register($shelter, [['name' => 'ข', 'phone' => '0888888888']])->first();

        $this->artisan('flood:prune-data')->assertSuccessful();

        $this->assertSame('', $case->fresh()->requester_phone);
        $this->assertNull($e->fresh()->phone);
        $this->assertSame('0888888888', $fresh->fresh()->phone);
        $this->assertSame(3, $case->fresh()->people_rescued);
    }

    public function test_security_headers(): void
    {
        $this->actingAs($this->admin)->get('/admin')->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get('/chachoengsao/map')->assertHeaderMissing('X-Frame-Options');
    }
}
