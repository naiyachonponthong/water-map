<?php

namespace Tests\Feature;

use App\Models\DamageClaim;
use App\Models\Province;
use App\Models\User;
use App\Models\WaterReport;
use App\Support\HelpRequestService;
use App\Support\RecoveryService;
use App\Support\Settings;
use App\Support\WaterReportService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class RecoveryTest extends TestCase
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

    protected function form(array $over = []): array
    {
        return $over + [
            'head_name' => 'นางสมใจ ใจดี', 'phone' => '081-234-5678', 'address' => '45 หมู่ 3 ต.บางพระ', 'lat' => 13.69, 'lng' => 101.07,
            'members' => 4, 'tenure' => 'own', 'house_damage' => 'major', 'water_level' => 4, 'flood_days' => 7, 'losses' => ['furniture', 'appliances'],
        ];
    }

    public function test_closed_by_default_then_citizen_submits_and_tracks(): void
    {
        $this->get('/chachoengsao/recovery')->assertOk()->assertSee('ยังไม่เปิดรับคำร้องเยียวยา');
        $this->post('/chachoengsao/recovery', $this->form())->assertRedirect();
        $this->assertSame(0, DamageClaim::count());

        Settings::set('recovery_open', true, $this->province->id);
        $res = $this->post('/chachoengsao/recovery', $this->form());
        $claim = DamageClaim::firstOrFail();
        $res->assertRedirect($claim->trackUrl());
        $this->assertSame('0812345678', $claim->phone);
        $this->get($claim->trackUrl())->assertOk()->assertSee($claim->code)->assertSee('รอสำรวจ');
        $this->get('/recovery/'.$claim->code)->assertForbidden();

        // ยื่นซ้ำด้วยเบอร์เดิม = ได้คำร้องเดิม
        $this->post('/chachoengsao/recovery', $this->form(['head_name' => 'คนเดิม']));
        $this->assertSame(1, DamageClaim::count());

        // ลืมลิงก์: ใช้เลขคำร้อง + เบอร์
        $this->post('/chachoengsao/recovery/lookup', ['code' => strtolower($claim->code), 'phone' => '0812345678'])->assertRedirect($claim->trackUrl());
        $this->post('/chachoengsao/recovery/lookup', ['code' => $claim->code, 'phone' => '0899999999'])->assertSessionHas('error');
    }

    public function test_evidence_from_flood_period(): void
    {
        app(HelpRequestService::class)->submit($this->province, [
            'lat' => 13.69, 'lng' => 101.07, 'water_level' => 5, 'people_count' => 4, 'needs' => ['evacuate'],
            'requester_name' => 'สมใจ', 'requester_phone' => '0812345678',
        ], ['source' => 'web']);
        app(WaterReportService::class)->submit($this->province, ['lat' => 13.6905, 'lng' => 101.0702, 'level' => 4], ['source' => 'web', 'device_hash' => 'x']);

        [$claim] = app(RecoveryService::class)->submit($this->province, $this->form());
        $this->assertArrayHasKey('help_request', $claim->evidence);
        $this->assertArrayHasKey('water_report', $claim->evidence);
        $this->assertNotNull($claim->help_request_id);
        $this->assertGreaterThanOrEqual(65, $claim->evidence_score);

        [$stranger] = app(RecoveryService::class)->submit($this->province, $this->form(['phone' => '0899999999', 'lat' => 13.95, 'lng' => 101.40]));
        $this->assertSame(0, $stranger->evidence_score);
    }

    public function test_full_workflow_with_suggested_amount(): void
    {
        Settings::set('recovery_rates', ['house' => ['minor' => 0, 'major' => 20000, 'destroyed' => 50000], 'loss' => ['furniture' => 3000, 'appliances' => 5000], 'crop_per_rai' => 0, 'cap' => 25000], $this->province->id);
        [$claim] = app(RecoveryService::class)->submit($this->province, $this->form());

        $surveyor = User::create(['name' => 'นายสำรวจ', 'phone' => '0833333333', 'password' => 'secret123', 'province_id' => $this->province->id, 'status' => 'active']);
        $surveyor->assignRole('team-leader');

        $this->actingAs($this->admin)->post(route('recovery.action', $claim), ['action' => 'schedule', 'surveyor_id' => $surveyor->id])->assertSessionHas('success');
        $this->assertSame('surveying', $claim->fresh()->status);

        // ผู้สำรวจทำได้แค่บันทึกผลสำรวจ อนุมัติไม่ได้
        $this->actingAs($surveyor)->get(route('recovery.index', ['tab' => 'surveying', 'mine' => 1]))->assertOk()->assertSee('นางสมใจ');
        $this->actingAs($surveyor)->post(route('recovery.action', $claim), ['action' => 'survey', 'verified_damage' => 'major', 'losses' => ['furniture', 'appliances'], 'survey_note' => 'ผนังร้าว'])->assertSessionHas('success');
        $this->actingAs($surveyor)->post(route('recovery.action', $claim), ['action' => 'approve', 'amount' => 999999])->assertForbidden();

        $claim->refresh();
        $this->assertSame('surveyed', $claim->status);
        $this->assertSame(25000.0, $claim->suggested_amount);   // 20000 + 3000 + 5000 ติดเพดาน 25000

        $this->actingAs($this->admin)->post(route('recovery.action', $claim), ['action' => 'approve', 'amount' => 25000])->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('recovery.action', $claim), ['action' => 'pay', 'amount' => 30000])->assertSessionHas('error');
        $this->actingAs($this->admin)->post(route('recovery.action', $claim), ['action' => 'pay', 'amount' => 25000, 'payment_ref' => 'TRX-001'])->assertSessionHas('success');

        $claim->refresh();
        $this->assertSame('paid', $claim->status);
        $this->assertSame(25000.0, $claim->paid_amount);
        $this->assertSame(['submitted', 'scheduled', 'surveyed', 'approved', 'paid'], $claim->events()->pluck('type')->all());
        $this->get($claim->trackUrl())->assertSee('จ่ายแล้ว')->assertSee('25,000.00');
    }

    public function test_cannot_skip_steps_or_pay_rejected(): void
    {
        [$claim] = app(RecoveryService::class)->submit($this->province, $this->form());
        $svc = app(RecoveryService::class);

        $this->expectException(RuntimeException::class);
        $svc->approve($claim, 1000, $this->admin);
    }

    public function test_reject_and_admin_pages_and_export(): void
    {
        [$claim] = app(RecoveryService::class)->submit($this->province, $this->form());
        $this->actingAs($this->admin)->post(route('recovery.action', $claim), ['action' => 'reject', 'reason' => 'ไม่ได้อยู่ในพื้นที่น้ำท่วม'])->assertSessionHas('success');
        $this->assertSame('rejected', $claim->fresh()->status);
        $this->actingAs($this->admin)->post(route('recovery.action', $claim), ['action' => 'pay', 'amount' => 100])->assertSessionHas('error');

        $this->actingAs($this->admin)->get(route('recovery.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('recovery.show', $claim))->assertOk()->assertSee($claim->code);
        $this->actingAs($this->admin)->get(route('recovery.create'))->assertOk();
        $this->actingAs($this->admin)->put(route('recovery.rates'), ['recovery_open' => 1, 'house' => ['major' => 10000]])->assertRedirect();
        $this->assertTrue((bool) Settings::get('recovery_open', $this->province->id));
        $this->get('/chachoengsao')->assertSee('ขอรับความช่วยเหลือหลังน้ำลด');

        $csv = $this->actingAs($this->admin)->get(route('exports.download', ['dataset' => 'claims']))->assertOk()->streamedContent();
        $this->assertStringContainsString($claim->code, $csv);
        $this->assertStringContainsString('0xx-xxx-5678', $csv);
    }
}
