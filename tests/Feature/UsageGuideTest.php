<?php

namespace Tests\Feature;

use App\Models\Province;
use App\Models\User;
use App\Support\UsageGuide;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UsageGuideTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    public function test_public_manual_contains_complete_instructions_without_provider_calls(): void
    {
        $this->seed(DatabaseSeeder::class);
        Http::preventStrayRequests();
        $response = $this->get('/guide')->assertOk()->assertViewIs('guide.index')->assertViewHas('province', null);
        $sections = UsageGuide::sections();
        $this->assertCount(18, $sections);
        $this->assertCount(18, array_unique(array_column($sections, 'id')));
        foreach ($sections as $section) {
            $response->assertSee('id="'.$section['id'].'"', false)->assertSee($section['title']);
            $this->assertGreaterThanOrEqual(4, count($section['steps']));
        }
        foreach (['guideSearch', 'guidePrint', 'guideExpand', 'guideEmpty', 'data-creator-credit', '4 ตัวท้ายเบอร์โทร', 'ไม่ใช่ความลึกน้ำเหนือพื้นบ้าน', 'เลือกจังหวัดเพื่อใช้งาน'] as $text) {
            $response->assertSee($text, false);
        }
        $response->assertDontSee('floodthai@2026')->assertDontSee('0800000000');
        Http::assertNothingSent();
    }

    public function test_manual_links_follow_active_selected_province(): void
    {
        $this->seed(DatabaseSeeder::class);
        foreach (['trang', 'bangkok'] as $slug) {
            $this->get('/'.$slug.'/guide')->assertOk()
                ->assertSee('/'.$slug.'/map', false)->assertSee('/'.$slug.'/water-map#nearby', false)
                ->assertSee('/'.$slug.'/weather', false);
        }
        $this->withCookie('flood_province', 'trang')->get('/guide')->assertOk()
            ->assertViewHas('province', fn ($province) => $province->slug === 'trang');
        Province::where('slug', 'trang')->update(['is_active' => false]);
        $this->get('/trang/guide')->assertNotFound();
        $this->withCookie('flood_province', 'trang')->get('/guide')->assertOk()->assertViewHas('province', null);
    }

    public function test_staff_manual_and_menu_do_not_grant_other_permissions(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->get('/admin/guide')->assertRedirect('/login');
        $user = User::create(['name' => 'Guide reader', 'phone' => '0811111222', 'password' => 'test-only',
            'province_id' => Province::where('slug', 'trang')->firstOrFail()->id, 'status' => 'active']);
        $user->assignRole('team-member');
        $this->actingAs($user)->get('/admin/guide')->assertOk()->assertViewHas('layout', 'layouts.app')
            ->assertSee('คู่มือการใช้งาน')->assertSee('Naiyachon ponthong');
        $this->get('/admin/menu')->assertOk()->assertSee(route('admin.guide'), false);
        $this->get('/admin/settings')->assertForbidden();
    }
}
