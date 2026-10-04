<?php

namespace Tests\Unit;

use App\Support\ReleasePackage;
use PHPUnit\Framework\TestCase;

class ReleasePackageTest extends TestCase
{
    public function test_release_allowlist_rejects_secrets_data_and_generated_files(): void
    {
        foreach (['.env', '.env.backup', 'storage/app/public/help/photo.jpg', 'storage/app/installer/setup-key.php',
            'storage/logs/laravel.log', 'public/storage/reports/a.jpg', 'bootstrap/cache/config.php',
            'database/database.sqlite', 'database/export.sql', '.tools/php83/php.exe', 'output/screenshot.png',
            'public/uploads/old.jpg', 'app/.aws/credentials', 'public/hot'] as $path) {
            $this->assertFalse(ReleasePackage::allowed($path), $path);
        }
        foreach (['public/images/flood-rescue-hero.png', 'public/install.php', 'installer/Installer.php',
            '.env.example', 'composer.lock', 'LICENSE.md', 'database/migrations/schema.php', 'resources/views/public/province.blade.php'] as $path) {
            $this->assertTrue(ReleasePackage::allowed($path), $path);
        }
    }
}
