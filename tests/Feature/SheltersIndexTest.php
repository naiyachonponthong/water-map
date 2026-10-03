<?php

namespace Tests\Feature;

use App\Models\Province;
use App\Models\Shelter;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SheltersIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    public function test_admin_shelters_renders_with_valid_script_safe_map_data(): void
    {
        $this->seed(DatabaseSeeder::class);
        $province = Province::where('slug', 'chachoengsao')->firstOrFail();
        $province->update(['command_open' => true, 'web_help_open' => true]);
        $admin = User::create([
            'name' => 'Shelter administrator',
            'phone' => '0811111111',
            'password' => 'secret123',
            'province_id' => $province->id,
            'status' => 'active',
        ]);
        $admin->assignRole('province-admin');

        $shelter = Shelter::create([
            'province_id' => $province->id,
            'name' => "ศูนย์ \"ทดสอบ\" ' & </script><script>alert(1)</script>\n\\",
            'type' => 'temple',
            'lat' => 13.67,
            'lng' => 101.07,
            'capacity' => 100,
            'occupancy' => 7,
            'status' => 'open',
            'is_public' => true,
        ]);

        $response = $this->actingAs($admin)->get('/admin/shelters');

        $response->assertOk()
            ->assertViewIs('admin.shelters.index')
            ->assertSee($shelter->name)
            ->assertSee('id="shelterMap"', false)
            ->assertDontSee('@json', false)
            ->assertDontSee('fn ($s)', false)
            ->assertDontSee(')->values()', false)
            ->assertDontSee('</script><script>alert(1)</script>', false);

        $this->assertSame(1, preg_match('/const data = ([^\r\n]+);/', $response->getContent(), $matches));
        $this->assertStringNotContainsString('<', $matches[1]);
        $this->assertSame([
            [
                'name' => $shelter->name,
                'lat' => 13.67,
                'lng' => 101.07,
                'color' => $shelter->color(),
                'status' => $shelter->statusLabel(),
                'occ' => 7,
                'cap' => 100,
                'url' => route('shelters.show', $shelter),
            ],
        ], json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR));
    }
}
