<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * header ความปลอดภัยพื้นฐานสำหรับทุกหน้า
 * (ไม่ตั้ง CSP แบบเข้ม เพราะใช้ CDN และ script ในหน้า หากต้องการให้ตั้งที่ nginx ตาม docs/DEPLOY.md)
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $h = $response->headers;
        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // หน้าเว็บฝังใน iframe ของเว็บอื่นได้เฉพาะหน้าสาธารณะ (ให้หน่วยงานนำแผนที่ไปฝัง) หลังบ้านห้าม
        if (! $request->is('*/map') && ! $request->is('*/open-data.json')) {
            $h->set('X-Frame-Options', 'SAMEORIGIN');
        }
        $h->set('Permissions-Policy', 'geolocation=(self), camera=(self), microphone=()');
        if ($request->isSecure()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        if ($request->is('admin*', 'field*')) {
            $h->set('Cache-Control', 'no-store, private');
            $h->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
