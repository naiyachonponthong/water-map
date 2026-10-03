<?php

namespace App\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

/** Explicit source allowlist. Never distribute this machine's .env or stored data. */
class ReleasePackage
{
    public const DIRECTORIES = ['app', 'bootstrap', 'config', 'database', 'public', 'resources', 'routes', 'installer', 'deploy', 'docs'];

    public const RUNTIME_DIRECTORIES = ['bootstrap/cache', 'storage/app/private', 'storage/app/public', 'storage/framework/cache/data',
        'storage/framework/sessions', 'storage/framework/views', 'storage/logs'];

    public static function allowed(string $path): bool
    {
        $path = str_replace('\\', '/', $path);
        if (str_starts_with($path, 'bootstrap/cache/') || str_starts_with($path, 'public/storage/') || $path === 'public/storage'
            || str_starts_with($path, 'public/uploads/') || $path === 'public/hot'
            || preg_match('#(^|/)(\.env(?!\.example$)|\.git|\.codex|\.agents|\.aws)(/|$)#', $path)
            || preg_match('/\.(sqlite|sql|log|bak|zip)$/i', $path)) {
            return false;
        }

        return in_array(explode('/', $path)[0], self::DIRECTORIES, true)
            || in_array($path, ['artisan', 'composer.json', 'composer.lock', 'README.md', 'NOTICE.md', '.env.example'], true);
    }

    public static function copySources(string $root, string $target): void
    {
        Attribution::verifyRelease($root);
        foreach (self::DIRECTORIES as $dir) {
            $source = $root.'/'.$dir;
            if (! is_dir($source)) {
                continue;
            }
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS)) as $file) {
                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                if ($file->isLink() || ! $file->isFile() || ! self::allowed($relative)) {
                    continue;
                }
                self::copy($file->getPathname(), $target.'/'.$relative);
            }
        }
        foreach (['artisan', 'composer.json', 'composer.lock', 'README.md', 'NOTICE.md', '.env.example'] as $file) {
            self::copy($root.'/'.$file, $target.'/'.$file);
        }
        // Blank template credentials; the web installer generates per-site values.
        $template = file_get_contents($target.'/.env.example');
        $template = preg_replace('/^(SUPERADMIN_PHONE|SUPERADMIN_PASSWORD|REVERB_APP_SECRET)=.*$/m', '$1=', $template);
        file_put_contents($target.'/.env.example', $template);
        self::copy($root.'/docs/INSTALL-EASY.md', $target.'/START-HERE.md');
        foreach (self::RUNTIME_DIRECTORIES as $dir) {
            if (! is_dir($target.'/'.$dir)) {
                mkdir($target.'/'.$dir, 0755, true);
            }
        }
    }

    private static function copy(string $source, string $target): void
    {
        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0755, true);
        }
        if (! copy($source, $target)) {
            throw new RuntimeException('Copy failed: '.$source);
        }
    }

    public static function archive(string $stage, string $destination): int
    {
        Attribution::verifyRelease($stage);
        $installed = json_decode(file_get_contents($stage.'/vendor/composer/installed.json'), true);
        if (($installed['dev'] ?? true) !== false) {
            throw new RuntimeException('Release dependencies must be installed with --no-dev.');
        }
        $zip = new ZipArchive;
        if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
            throw new RuntimeException('Cannot create a new release ZIP.');
        }
        $count = 0;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($stage, FilesystemIterator::SKIP_DOTS)) as $file) {
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($stage) + 1));
            if (! $file->isFile() || $file->isLink() || $relative === '.env' || str_starts_with($relative, 'storage/')) {
                continue;
            }
            $zip->addFile($file->getPathname(), 'floodthai/'.$relative);
            $count++;
        }
        foreach (self::RUNTIME_DIRECTORIES as $dir) {
            $zip->addEmptyDir('floodthai/'.$dir);
        }
        if (! $zip->close()) {
            throw new RuntimeException('Cannot finish release ZIP.');
        }

        return $count;
    }
}
