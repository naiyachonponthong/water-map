<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Evacuee;
use App\Models\Province;
use App\Models\Shelter;
use App\Models\SupplyItem;
use App\Models\User;
use App\Support\LineMessenger;
use App\Support\ShelterService;
use App\Support\SupplyService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class ReliefTest extends TestCase
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

    protected function shelter(array $attrs = []): Shelter
    {
        return Shelter::create($attrs + [
            'province_id' => $this->province->id, 'name' => 'วัดโสธร', 'type' => 'temple', 'lat' => 13.67, 'lng' => 101.07,
            'capacity' => 3, 'status' => 'open', 'is_public' => true, 'contact_phone' => '038-111111',
        ]);
    }

    public function test_register_family_auto_full_and_checkout(): void
    {
        $s = $this->shelter();
        $this->actingAs($this->admin)->post(route('shelters.register.store', $s), [
            'people' => [
                ['name' => 'สมชาย ใจดี', 'phone' => '081-222-3333', 'age_group' => 'adult'],
                ['name' => 'สมหญิง ใจดี', 'age_group' => 'elderly', 'needs' => ['bedridden']],
                ['name' => 'ด.ญ.มะลิ ใจดี', 'age_group' => 'child'],
                ['name' => ''],
            ],
            'address' => '45 หมู่ 3', 'allow_lookup' => 1,
        ])->assertRedirect(route('shelters.show', $s));

        $s->refresh();
        $this->assertSame(3, $s->occupancy);
        $this->assertSame('full', $s->status);
        $family = Evacuee::pluck('family_code')->unique();
        $this->assertCount(1, $family);
        $this->assertNotNull($family->first());

        $e = Evacuee::where('name', 'สมชาย ใจดี')->first();
        $this->actingAs($this->admin)->post(route('evacuees.checkout', $e), ['reason' => 'home', 'family' => 1])->assertRedirect();
        $s->refresh();
        $this->assertSame(0, $s->occupancy);
        $this->assertSame('open', $s->status);
    }

    public function test_relatives_lookup_requires_exact_phone_and_consent(): void
    {
        $s = $this->shelter();
        app(ShelterService::class)->register($s, [['name' => 'สมชาย ใจดี', 'phone' => '0812223333']], ['allow_lookup' => true]);
        app(ShelterService::class)->register($s, [['name' => 'ไม่ให้ค้น', 'phone' => '0899999999']], ['allow_lookup' => false]);

        $this->post('/chachoengsao/find', ['phone' => '081-222-3333'])->assertOk()->assertSee('สมชาย ใ***')->assertSee('วัดโสธร')->assertDontSee('ใจดี');
        $this->post('/chachoengsao/find', ['phone' => '0899999999'])->assertOk()->assertSee('ไม่พบ');
        $this->post('/chachoengsao/find', ['phone' => '08122233'])->assertSessionHasErrors('phone');
    }

    public function test_transfer_moves_family(): void
    {
        $a = $this->shelter(['capacity' => 0]);
        $b = $this->shelter(['name' => 'โรงเรียนเบญจมราชรังสฤษฎิ์', 'capacity' => 100]);
        $list = app(ShelterService::class)->register($a, [['name' => 'ก'], ['name' => 'ข']]);
        $this->actingAs($this->admin)->post(route('evacuees.transfer', $list->first()), ['to' => $b->id, 'family' => 1])->assertRedirect();

        $this->assertSame(0, $a->fresh()->occupancy);
        $this->assertSame(2, $b->fresh()->occupancy);
    }

    public function test_supply_stock_movements(): void
    {
        $s = $this->shelter();
        $item = SupplyItem::create(['province_id' => $this->province->id, 'name' => 'น้ำดื่ม', 'category' => 'water', 'unit' => 'แพ็ค', 'min_stock' => 50]);
        $svc = app(SupplyService::class);

        $svc->receive($item, 100, null, $this->admin, 'บริษัท ก');
        $svc->transfer($item, 40, null, $s, $this->admin);
        $svc->issue($item, 15, $s, $this->admin);
        $this->assertSame(60, $svc->stock($item->id, null));
        $this->assertSame(25, $svc->stock($item->id, $s->id));

        try {
            $svc->issue($item, 30, $s, $this->admin);
            $this->fail('ควรจ่ายเกินไม่ได้');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('ไม่พอ', $e->getMessage());
        }

        $svc->adjust($item, null, 45, $this->admin);
        $this->assertSame(45, $svc->stock($item->id, null));
        $this->assertCount(1, $svc->low($this->province));

        $this->actingAs($this->admin)->post(route('supplies.move'), ['kind' => 'out', 'supply_item_id' => $item->id, 'qty' => 999])->assertSessionHas('error');
        $this->actingAs($this->admin)->get(route('supplies.index'))->assertOk()->assertSee('น้ำดื่ม');
    }

    public function test_announcement_web_and_line_broadcast(): void
    {
        LineMessenger::saveSecret('line_oa_token', 'TOKEN123', $this->province->id);
        Http::fake(['api.line.me/*' => Http::response([], 200)]);

        $this->actingAs($this->admin)->post(route('announcements.store'), [
            'title' => 'เปิดศูนย์พักพิงวัดโสธร', 'body' => 'รับผู้อพยพได้ 300 คน', 'level' => 'urgent', 'audience' => 'public', 'send_line' => 1, 'action' => 'publish',
        ])->assertRedirect();

        $a = Announcement::firstOrFail();
        $this->assertSame('sent', $a->line_status);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/message/broadcast')
            && $r->hasHeader('Authorization', 'Bearer TOKEN123')
            && str_contains($r['messages'][0]['text'], 'เปิดศูนย์พักพิงวัดโสธร'));

        $this->get('/chachoengsao')->assertSee('เปิดศูนย์พักพิงวัดโสธร');
        $this->get('/chachoengsao/news')->assertOk()->assertSee('รับผู้อพยพได้ 300 คน');

        // ร่าง ยังไม่แสดง และไม่ส่ง LINE
        $this->actingAs($this->admin)->post(route('announcements.store'), ['title' => 'ร่างลับ', 'body' => 'x', 'level' => 'info', 'audience' => 'public', 'action' => 'draft'])->assertRedirect();
        $this->get('/chachoengsao/news')->assertDontSee('ร่างลับ');
    }

    public function test_line_failure_and_unconfigured_are_recorded(): void
    {
        $this->actingAs($this->admin)->post(route('announcements.store'), ['title' => 'ทดสอบ', 'body' => 'x', 'level' => 'info', 'audience' => 'public', 'send_line' => 1, 'action' => 'publish']);
        $this->assertSame('skipped', Announcement::latest('id')->first()->line_status);

        LineMessenger::saveSecret('line_oa_token', 'BAD', $this->province->id);
        Http::fake(['api.line.me/*' => Http::response(['message' => 'Authentication failed'], 401)]);
        $this->actingAs($this->admin)->post(route('announcements.store'), ['title' => 'ทดสอบ 2', 'body' => 'x', 'level' => 'info', 'audience' => 'public', 'send_line' => 1, 'action' => 'publish']);
        $a = Announcement::latest('id')->first();
        $this->assertSame('failed', $a->line_status);
        $this->assertStringContainsString('401', $a->line_error);
    }

    public function test_line_webhook_verifies_signature_and_replies_nearest_shelter(): void
    {
        $this->shelter();
        LineMessenger::saveSecret('line_oa_token', 'TOKEN', $this->province->id);
        LineMessenger::saveSecret('line_oa_secret', 'SECRET', $this->province->id);
        Http::fake(['api.line.me/*' => Http::response([], 200)]);

        $body = json_encode(['events' => [['type' => 'message', 'replyToken' => 'r1', 'message' => ['type' => 'location', 'latitude' => 13.68, 'longitude' => 101.07]]]]);
        $sig = base64_encode(hash_hmac('sha256', $body, 'SECRET', true));

        $this->call('POST', '/line/webhook/chachoengsao', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_LINE_SIGNATURE' => 'wrong'], $body)->assertForbidden();
        $this->call('POST', '/line/webhook/chachoengsao', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_LINE_SIGNATURE' => $sig], $body)->assertOk();

        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/message/reply') && $r['replyToken'] === 'r1' && str_contains($r['messages'][0]['text'], 'วัดโสธร'));
    }

    public function test_public_shelters_page_and_admin_pages(): void
    {
        $s = $this->shelter();
        $s->needs()->create(['item' => 'นมผงเด็ก', 'priority' => 'urgent', 'status' => 'open']);
        $this->get('/chachoengsao/shelters')->assertOk()->assertSee('วัดโสธร')->assertSee('นมผงเด็ก');
        $this->actingAs($this->admin)->get(route('shelters.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('shelters.show', $s))->assertOk();
        $this->actingAs($this->admin)->get(route('shelters.register', $s))->assertOk();
        $this->actingAs($this->admin)->get(route('announcements.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.settings.index', ['tab' => 'line']))->assertOk()->assertSee('line/webhook/chachoengsao', false);
    }
}
