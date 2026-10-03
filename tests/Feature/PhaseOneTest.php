<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Province;
use App\Models\User;
use App\Support\AreaImporter;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseOneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    protected function province(): Province
    {
        return Province::where('slug', 'chachoengsao')->firstOrFail();
    }

    protected function makeUser(string $role, string $status = 'active', ?Province $province = null): User
    {
        $user = User::create([
            'name' => 'ทดสอบ '.$role,
            'phone' => '08'.random_int(10000000, 99999999),
            'password' => 'secret123',
            'province_id' => ($province ?? $this->province())->id,
            'status' => $status,
        ]);
        $user->assignRole($role);

        return $user;
    }

    public function test_seed_creates_77_provinces_and_pilot_districts(): void
    {
        $this->assertSame(77, Province::count());
        $this->assertSame(11, District::where('province_id', $this->province()->id)->count());
    }

    public function test_login_with_phone_in_any_format(): void
    {
        $this->post('/login', ['phone' => '080-000-0000', 'password' => config('floodthai.superadmin.password')])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_pending_user_is_sent_to_pending_page(): void
    {
        $user = $this->makeUser('team-member', 'pending');
        $this->actingAs($user)->get('/admin')->assertRedirect(route('pending'));
    }

    public function test_register_creates_pending_user(): void
    {
        $this->post('/register', [
            'name' => 'สมหญิง กู้ภัย', 'phone' => '0891234567', 'province_id' => $this->province()->id,
            'organization' => 'มูลนิธิทดสอบ', 'requested_role' => 'team-member',
            'password' => 'abc12345', 'password_confirmation' => 'abc12345',
        ])->assertRedirect(route('pending'));

        $this->assertDatabaseHas('users', ['phone' => '0891234567', 'status' => 'pending']);
    }

    public function test_dispatcher_cannot_manage_users(): void
    {
        $this->actingAs($this->makeUser('dispatcher'))->get('/admin/users')->assertForbidden();
    }

    public function test_province_admin_only_sees_own_province_users(): void
    {
        $other = Province::where('slug', 'chonburi')->first();
        $mine = $this->makeUser('team-member');
        $theirs = $this->makeUser('team-member', 'active', $other);

        $this->actingAs($this->makeUser('province-admin'))->get('/admin/users')
            ->assertOk()->assertSee(phone_format($mine->phone))->assertDontSee(phone_format($theirs->phone));
    }

    public function test_province_admin_cannot_edit_other_province_user(): void
    {
        $other = $this->makeUser('team-member', 'active', Province::where('slug', 'chonburi')->first());
        $this->actingAs($this->makeUser('province-admin'))
            ->post("/admin/users/{$other->id}/suspend")->assertForbidden();
    }

    public function test_approve_assigns_role(): void
    {
        $pending = $this->makeUser('team-member', 'pending');
        $pending->syncRoles([]);

        $this->actingAs($this->makeUser('province-admin'))
            ->post("/admin/users/{$pending->id}/approve", ['role' => 'team-leader'])
            ->assertRedirect();

        $pending->refresh();
        $this->assertSame('active', $pending->status);
        $this->assertTrue($pending->hasRole('team-leader'));
    }

    public function test_area_importer_matches_cod_ab_properties(): void
    {
        $province = $this->province();
        $geo = ['type' => 'FeatureCollection', 'features' => [
            ['type' => 'Feature', 'properties' => ['ADM2_TH' => 'อำเภอบางคล้า', 'ADM2_PCODE' => 'TH2402'],
                'geometry' => ['type' => 'Polygon', 'coordinates' => [[[101.1, 13.6], [101.3, 13.6], [101.3, 13.8], [101.1, 13.8], [101.1, 13.6]]]]],
            ['type' => 'Feature', 'properties' => ['ADM2_TH' => 'เมืองชลบุรี', 'ADM2_PCODE' => 'TH2001'],
                'geometry' => ['type' => 'Polygon', 'coordinates' => [[[100.9, 13.3], [101.0, 13.3], [101.0, 13.4], [100.9, 13.3]]]]],
        ]];

        $report = (new AreaImporter($province))->import($geo, 'district');

        $this->assertSame(1, $report['updated']);
        $this->assertSame(1, $report['other_province']);
        $district = District::where('code', '2402')->first();
        $this->assertTrue($district->containsPoint(13.7, 101.2));
    }

    public function test_public_province_page_shows_emergency_numbers(): void
    {
        $this->get('/chachoengsao')->assertOk()->assertSee('1784')->assertSee('ยังไม่เปิดศูนย์สั่งการ');
    }
}
