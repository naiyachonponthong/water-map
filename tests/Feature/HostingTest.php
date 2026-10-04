<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\HostingStatus;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HostingTest extends TestCase
{
    use RefreshDatabase;

    public function test_hosting_page_is_only_visible_to_super_administrators(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->get('/admin/hosting')->assertRedirect('/login');
        $user = User::create(['name' => 'ทดสอบโฮสต์', 'phone' => '0812349876', 'password' => 'test-password', 'status' => 'active']);
        $user->assignRole('province-admin');
        $this->actingAs($user)->get('/admin/hosting')->assertForbidden();
        $user->syncRoles(['super-admin']);
        $this->get('/admin/hosting')->assertOk()->assertSee('flood:cron')->assertSee('ยังมีรายการไม่ผ่าน');
    }

    public function test_recent_worker_heartbeats_are_required_and_old_ones_fail(): void
    {
        $status = $this->getMockBuilder(HostingStatus::class)->onlyMethods(['lastBeat'])->getMock();
        $status->expects($this->atLeastOnce())->method('lastBeat')->willReturn(now()->timestamp - 181);
        $checks = array_values(array_filter($status->checks(), fn ($c) => str_contains($c['label'], 'ทำงานล่าสุด')));
        $this->assertCount(2, $checks);
        $this->assertFalse($checks[0]['ok']);
        $this->assertFalse($checks[1]['ok']);
        $fresh = $this->getMockBuilder(HostingStatus::class)->onlyMethods(['lastBeat'])->getMock();
        $fresh->expects($this->atLeastOnce())->method('lastBeat')->willReturn(now()->timestamp - 30);
        $checks = array_values(array_filter($fresh->checks(), fn ($c) => str_contains($c['label'], 'ทำงานล่าสุด')));
        $this->assertTrue($checks[0]['ok']);
        $this->assertTrue($checks[1]['ok']);
    }

    public function test_public_image_fallback_rejects_private_paths_and_non_images(): void
    {
        config(['floodthai.public_uploads_through_app' => true]);
        foreach (['../installer/setup-key.php', '.env', 'private/secret.php', 'fake.jpg.php'] as $path) {
            $this->get('/media/'.$path)->assertNotFound();
        }
    }

    public function test_public_image_fallback_serves_only_real_images_without_a_symlink(): void
    {
        config(['floodthai.public_uploads_through_app' => true]);
        $folder = 'hosting-test-'.bin2hex(random_bytes(8));
        $directory = storage_path('app/public/'.$folder);
        mkdir($directory, 0700, true);
        $image = $directory.'/valid.png';
        $fake = $directory.'/fake.png';
        try {
            file_put_contents($image, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
            file_put_contents($fake, '<?php echo "not an image";');
            $this->get('/media/'.$folder.'/valid.png')->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->get('/media/'.$folder.'/fake.png')->assertNotFound();
            config(['floodthai.public_uploads_through_app' => false]);
            $this->get('/media/'.$folder.'/valid.png')->assertNotFound();
        } finally {
            unlink($image);
            unlink($fake);
            rmdir($directory);
        }
    }
}
