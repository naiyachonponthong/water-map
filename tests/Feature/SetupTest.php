<?php

namespace Tests\Feature;

use App\Models\Province;
use App\Models\User;
use App\Support\HostingStatus;
use App\Support\Settings;
use App\Support\SetupChecklist;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SetupTest extends TestCase
{
    use RefreshDatabase;

    private Province $province;

    private Province $other;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->province = Province::create(['code' => '24', 'slug' => 'chachoengsao', 'name_th' => 'ฉะเชิงเทรา', 'name_en' => 'Chachoengsao', 'region' => 'east', 'center_lat' => 13.69, 'center_lng' => 101.07, 'default_zoom' => 10, 'is_active' => true]);
        $this->other = Province::create(['code' => '92', 'slug' => 'trang', 'name_th' => 'ตรัง', 'name_en' => 'Trang', 'region' => 'south', 'default_zoom' => 10, 'is_active' => true]);
        $this->admin = User::create(['name' => 'ผู้ดูแล', 'phone' => '0812345678', 'password' => 'test-password', 'status' => 'active']);
        $this->admin->assignRole('super-admin');
        Http::preventStrayRequests();
    }

    public function test_setup_and_writes_are_only_accessible_to_active_super_administrators(): void
    {
        $this->get('/admin/setup')->assertRedirect('/login');
        $user = User::create(['name' => 'ผอ.', 'phone' => '0898765432', 'password' => 'test-password', 'status' => 'active', 'province_id' => $this->province->id]);
        $user->assignRole('province-admin');
        $this->actingAs($user)->get('/admin/setup')->assertForbidden();
        foreach (['province', 'basics', 'review'] as $action) {
            $this->post('/admin/setup/'.$action, ['province_id' => $this->other->id])->assertForbidden();
        }
    }

    public function test_every_step_renders_without_external_api_calls_or_opening_a_center(): void
    {
        $this->actingAs($this->admin)->withSession(['admin_province_id' => $this->province->id]);
        foreach (range(1, 5) as $step) {
            $this->get('/admin/setup?step='.$step)->assertOk()->assertSee('ตั้งค่าเริ่มต้น')->assertSee('ฉะเชิงเทรา');
        }
        $this->get('/admin/setup?step=4')->assertSee('Run a PHP script')->assertSee('flood:cron');
        $this->assertFalse($this->province->fresh()->command_open);
        $this->assertFalse($this->province->fresh()->web_help_open);
        Http::assertNothingSent();
    }

    public function test_province_selection_rejects_inactive_provinces(): void
    {
        $this->actingAs($this->admin)->post('/admin/setup/province', ['province_id' => $this->other->id])
            ->assertRedirect(route('admin.setup', ['step' => 2]))->assertSessionHas('admin_province_id', $this->other->id);
        $this->other->update(['is_active' => false]);
        $this->post('/admin/setup/province', ['province_id' => $this->other->id])->assertSessionHasErrors('province_id');
    }

    public function test_basics_are_saved_for_the_visible_province_only_and_leave_switches_unchanged(): void
    {
        $this->actingAs($this->admin)->withSession(['admin_province_id' => $this->province->id]);
        $data = ['province_id' => $this->province->id, 'privacy_controller' => 'ศูนย์จังหวัด', 'privacy_contact' => 'ติดต่อศูนย์', 'hotline' => '038-123456', 'center_lat' => 13.7, 'center_lng' => 101.08, 'default_zoom' => 11];
        $this->post('/admin/setup/basics', $data)->assertRedirect(route('admin.setup', ['step' => 3]));
        $this->assertSame('ศูนย์จังหวัด', Settings::get('privacy_controller', $this->province->id));
        $this->assertSame('038-123456', Settings::get('hotline', $this->province->id));
        $this->assertSame(13.7, $this->province->fresh()->center_lat);
        $this->assertNull(Settings::get('privacy_controller', $this->other->id));
        $this->assertFalse($this->province->fresh()->command_open);
        $this->assertFalse($this->province->fresh()->web_help_open);
        $this->post('/admin/setup/basics', array_replace($data, ['province_id' => $this->other->id]))->assertSessionHasErrors('province_id');
        $this->post('/admin/setup/basics', array_replace($data, ['center_lat' => 90, 'hotline' => 'tel:<script>']))->assertSessionHasErrors(['center_lat', 'hotline']);
    }

    public function test_review_is_scoped_audited_and_can_be_unchecked_without_marking_host_ready(): void
    {
        $this->actingAs($this->admin)->withSession(['admin_province_id' => $this->province->id]);
        $this->post('/admin/setup/review', ['province_id' => $this->province->id, 'workflow' => 1, 'backup' => 1])->assertRedirect();
        $review = Settings::get('setup_verification', $this->province->id);
        $this->assertTrue($review['workflow']);
        $this->assertTrue($review['backup']);
        $this->assertFalse($review['sources']);
        $this->assertSame($this->admin->id, $review['confirmed_by']);
        $this->assertNull(Settings::get('setup_verification', $this->other->id));
        $this->assertDatabaseHas('audit_logs', ['subject_type' => 'App\\Models\\Setting', 'user_id' => $this->admin->id]);
        $hosting = $this->createStub(HostingStatus::class);
        $hosting->method('checks')->willReturn([['label' => 'Cron', 'ok' => false, 'help' => 'ยังไม่ทำงาน']]);
        $steps = app(SetupChecklist::class)->steps($this->province, $hosting);
        $this->assertFalse($steps[4]['checks'][0]['ok']);
        $this->assertTrue($steps[5]['checks'][0]['ok']);
        $this->post('/admin/setup/review', ['province_id' => $this->province->id])->assertRedirect();
        $this->assertFalse(Settings::get('setup_verification', $this->province->id)['workflow']);
        $this->assertFalse($this->province->fresh()->command_open);
    }

    public function test_role_checks_require_active_staff_in_the_selected_province(): void
    {
        $this->actingAs($this->admin);
        $staff = User::create(['name' => 'เจ้าหน้าที่', 'phone' => '0891111111', 'password' => 'test-password', 'status' => 'active', 'province_id' => $this->other->id]);
        $staff->assignRole('dispatcher');
        $hosting = $this->createStub(HostingStatus::class);
        $hosting->method('checks')->willReturn([]);
        $checklist = app(SetupChecklist::class);
        $this->assertFalse($checklist->steps($this->province, $hosting)[3]['checks'][1]['ok']);
        $staff->update(['province_id' => $this->province->id]);
        $this->assertTrue($checklist->steps($this->province, $hosting)[3]['checks'][1]['ok']);
        $staff->update(['status' => 'suspended']);
        $this->assertFalse($checklist->steps($this->province, $hosting)[3]['checks'][1]['ok']);
    }
}
