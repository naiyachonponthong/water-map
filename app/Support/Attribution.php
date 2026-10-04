<?php

namespace App\Support;

use RuntimeException;

/** Central credit; not configurable by site administrators. Never blocks emergency use. */
class Attribution
{
    public const AUTHOR = 'Naiyachon ponthong';

    public static function verifyRelease(string $root): void
    {
        $required = [
            'resources/views/partials/creator-credit.blade.php' => 'App\\Support\\Attribution::AUTHOR',
            'resources/views/layouts/public.blade.php' => "@include('partials.creator-credit')",
            'resources/views/layouts/app.blade.php' => "@include('partials.creator-credit')",
            'resources/views/layouts/guest.blade.php' => "@include('partials.creator-credit')",
            'app/Support/Attribution.php' => self::AUTHOR,
            'NOTICE.md' => self::AUTHOR,
            'LICENSE.md' => self::AUTHOR,
            'public/css/creator-credit.css' => '.creator-credit',
        ];
        foreach ($required as $path => $needle) {
            $file = $root.'/'.$path;
            if (! is_file($file) || ! str_contains(file_get_contents($file), $needle)) {
                throw new RuntimeException('Creator credit missing: '.$path.'. Restore attribution before distributing.');
            }
        }
    }
}
