<?php

namespace Tests\Feature;

use App\Jobs\EvaluateProvinceRisks;
use App\Jobs\SendAnnouncementLine;
use App\Models\Province;
use App\Models\User;
use App\Support\LiveSnapshot;
use App\Support\Totp;
use App\Support\WaterReportService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SecurityTest extends TestCase
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

    protected function user(string $role, string $phone = '0811111111'): User
    {
        $u = User::create(['name' => 'ผู้ใช้', 'phone' => $phone, 'password' => 'secret123', 'province_id' => $this->province->id, 'status' => 'active']);
        $u->assignRole($role);

        return $u;
    }

    public function test_two_factor_setup_and_login_challenge(): void
    {
        $u = $this->user('dispatcher');

        $this->actingAs($u)->post(route('profile.2fa.enable'))->assertRedirect();
        $secret = $u->fresh()->two_factor_secret;
        $this->assertNotEmpty($secret);
        $this->actingAs($u)->get(route('profile.edit'))->assertOk()->assertSee('otpauth://totp/', false);

        $this->actingAs($u)->post(route('profile.2fa.confirm'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->actingAs($u)->post(route('profile.2fa.confirm'), ['code' => Totp::code($secret, Totp::step() - 1)])->assertSessionHas('recovery_codes');
        $this->assertTrue($u->fresh()->hasTwoFactor());
        $codes = $u->fresh()->two_factor_recovery_codes;
        $this->assertCount(8, $codes);

        auth()->logout();
        // รหัสผ่านถูก แต่ยังไม่เข้า ต้องผ่านหน้า 2 ชั้นก่อน
        $this->post(route('login.attempt'), ['phone' => '0811111111', 'password' => 'secret123'])->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
        $this->get(route('two-factor.challenge'))->assertOk();
        $this->post(route('two-factor.verify'), ['code' => '111111'])->assertSessionHasErrors('code');
        $this->assertGuest();

        // รหัสสำรองใช้ได้ครั้งเดียว
        $this->post(route('two-factor.verify'), ['code' => $codes[0]])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($u);
        $this->assertCount(7, $u->fresh()->two_factor_recovery_codes);
    }

    public function test_required_roles_are_forced_to_setup(): void
    {
        config(['floodthai.require_2fa_roles' => 'province-admin']);
        $admin = $this->user('province-admin');
        $this->actingAs($admin)->get('/admin/cases')->assertRedirect(route('profile.edit', ['tab' => '2fa']));
        $this->actingAs($admin)->get(route('profile.edit'))->assertOk();
        $this->actingAs($admin)->getJson('/admin/live/snapshot')->assertForbidden();

        // บทบาทที่ไม่บังคับใช้งานได้ตามปกติ
        $mod = $this->user('moderator', '0822222222');
        $this->actingAs($mod)->get('/admin/cases')->assertOk();
    }

    public function test_admin_can_reset_lost_phone(): void
    {
        $admin = $this->user('province-admin');
        $u = $this->user('dispatcher', '0822222222');
        $u->forceFill(['two_factor_secret' => Totp::generateSecret(), 'two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => ['a-b']])->save();

        $this->actingAs($admin)->post(route('admin.users.reset-2fa', $u))->assertRedirect();
        $this->assertFalse($u->fresh()->hasTwoFactor());
    }

    public function test_idle_timeout_but_not_for_background_polls_or_tv(): void
    {
        config(['floodthai.idle_timeout_min' => 30]);
        $u = $this->user('dispatcher');

        $this->actingAs($u)->withSession(['last_active_at' => time() - 10 * 60])->get('/admin/cases')->assertOk();
        $this->actingAs($u)->withSession(['last_active_at' => time() - 31 * 60])->get('/admin/cases')->assertRedirect(route('login'));
        $this->assertGuest();

        $this->actingAs($u)->withSession(['last_active_at' => time() - 31 * 60])->getJson('/admin/live/snapshot')->assertUnauthorized();
        $this->actingAs($u)->withSession(['last_active_at' => time() - 31 * 60, 'kiosk' => true])->getJson('/admin/live/snapshot')->assertOk();
    }

    public function test_heavy_work_is_queued(): void
    {
        Queue::fake();
        $svc = app(WaterReportService::class);
        $svc->submit($this->province, ['lat' => 13.69, 'lng' => 101.07, 'level' => 4], ['source' => 'web', 'device_hash' => 'a']);
        $svc->submit($this->province, ['lat' => 13.6905, 'lng' => 101.0702, 'level' => 4], ['source' => 'web', 'device_hash' => 'b']);
        Queue::assertPushed(EvaluateProvinceRisks::class, fn ($job) => $job->provinceId === $this->province->id);

        $admin = $this->user('province-admin');
        \App\Support\LineMessenger::saveSecret('line_oa_token', 'T', $this->province->id);
        $this->actingAs($admin)->post(route('announcements.store'), ['title' => 'ทดสอบ', 'body' => 'x', 'level' => 'info', 'audience' => 'public', 'send_line' => 1, 'action' => 'publish'])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'กำลังส่ง LINE'));
        Queue::assertPushed(SendAnnouncementLine::class);
    }

    public function test_snapshot_cache_invalidates_on_change(): void
    {
        $u = $this->user('dispatcher');
        $this->actingAs($u);
        $before = LiveSnapshot::cached($this->province)['stats']['triage'];
        app(\App\Support\HelpRequestService::class)->submit($this->province, [
            'lat' => 13.69, 'lng' => 101.07, 'water_level' => 3, 'people_count' => 1, 'needs' => ['food'], 'requester_name' => 'ก', 'requester_phone' => '0899999999',
        ], ['source' => 'web']);
        $this->assertSame($before + 1, LiveSnapshot::cached($this->province)['stats']['triage']);
    }

    public function test_privacy_page_and_form_notice(): void
    {
        $this->get('/chachoengsao/privacy')->assertOk()->assertSee('ประกาศความเป็นส่วนตัว')->assertSee('180');
        $this->get('/chachoengsao/help')->assertOk()->assertSee('อ่านประกาศความเป็นส่วนตัว');
        $this->get('/chachoengsao/report')->assertOk()->assertSee('อ่านประกาศความเป็นส่วนตัว');
    }
}
