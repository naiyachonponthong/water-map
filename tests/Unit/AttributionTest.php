<?php

namespace Tests\Unit;

use App\Support\Attribution;
use App\Support\ReleasePackage;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class AttributionTest extends TestCase
{
    public function test_actual_release_sources_include_central_credit_and_notice(): void
    {
        Attribution::verifyRelease(dirname(__DIR__, 2));
        $this->assertTrue(ReleasePackage::allowed('NOTICE.md'));
        $this->assertSame('Naiyachon ponthong', Attribution::AUTHOR);
    }

    public function test_distribution_check_rejects_removed_or_replaced_credit(): void
    {
        $root = sys_get_temp_dir().'/floodthai-credit-test-'.bin2hex(random_bytes(8));
        $files = ['resources/views/partials/creator-credit.blade.php', 'resources/views/layouts/public.blade.php',
            'resources/views/layouts/app.blade.php', 'resources/views/layouts/guest.blade.php',
            'app/Support/Attribution.php', 'NOTICE.md', 'public/css/creator-credit.css'];
        try {
            foreach ($files as $file) {
                if (! is_dir(dirname($root.'/'.$file))) {
                    mkdir(dirname($root.'/'.$file), 0700, true);
                }
                copy(dirname(__DIR__, 2).'/'.$file, $root.'/'.$file);
            }
            Attribution::verifyRelease($root);
            file_put_contents($root.'/NOTICE.md', 'Removed creator credit');
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Creator credit missing: NOTICE.md');
            Attribution::verifyRelease($root);
        } finally {
            // Only this random test fixture and its explicitly listed files.
            foreach ($files as $file) {
                if (is_file($root.'/'.$file)) {
                    unlink($root.'/'.$file);
                }
            }
            foreach (['resources/views/partials', 'resources/views/layouts', 'resources/views', 'resources', 'app/Support', 'app', 'public/css', 'public', ''] as $dir) {
                if (is_dir($root.($dir ? '/'.$dir : ''))) {
                    rmdir($root.($dir ? '/'.$dir : ''));
                }
            }
        }
    }
}
