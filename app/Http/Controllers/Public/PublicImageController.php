<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;

class PublicImageController extends Controller
{
    /** Same public images as storage:link, without requiring a hosting symlink. */
    public function show(string $path)
    {
        abort_unless(config('floodthai.public_uploads_through_app'), 404);
        abort_unless(preg_match('#^[a-zA-Z0-9/_-]+\.(jpg|jpeg|png|webp)$#D', $path), 404);
        $root = realpath(storage_path('app/public'));
        $file = realpath(storage_path('app/public/'.$path));
        abort_unless($root && $file && str_starts_with($file, $root.DIRECTORY_SEPARATOR) && is_file($file), 404);
        $mime = mime_content_type($file);
        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true), 404);

        return response()->file($file, ['Content-Type' => $mime, 'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600', 'Content-Security-Policy' => "default-src 'none'; sandbox"]);
    }
}
