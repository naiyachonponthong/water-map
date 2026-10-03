<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * ไม่ใช้งานนานเกินกำหนด ออกจากระบบอัตโนมัติ (ลืมเปิดเครื่องทิ้งไว้ในห้องสั่งการ)
 *  - การดึงข้อมูลเบื้องหลังของหน้าจอ (snapshot, poll, แผนที่) ไม่นับเป็นการใช้งาน
 *  - จอทีวี (/admin/tv) เป็นโหมด kiosk ไม่หมดเวลา จนกว่าจะเปิดหน้าอื่น
 *  - แอปภาคสนามไม่ได้ผ่าน middleware นี้ (ทีมอยู่หน้างานต้องเข้าได้ตลอด)
 */
class IdleTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        $limit = (int) config('floodthai.idle_timeout_min');
        if (! Auth::check() || $limit <= 0) {
            return $next($request);
        }

        $session = $request->session();
        $now = time();
        $background = $request->routeIs('live.snapshot', 'cases.poll', 'dashboard.areas') || str_ends_with($request->path(), '.geojson');

        if ($request->routeIs('live.tv')) {
            $session->put('kiosk', true);
        } elseif (! $background) {
            $session->put('kiosk', false);
        }

        $last = (int) $session->get('last_active_at', $now);
        if (! $session->get('kiosk') && $now - $last > $limit * 60) {
            AuditLog::record('logout', $request->user(), null, null, 'ออกจากระบบอัตโนมัติ ไม่ได้ใช้งาน '.$limit.' นาที');
            Auth::logout();
            $session->invalidate();
            $session->regenerateToken();

            return $request->expectsJson()
                ? response()->json(['message' => 'หมดเวลาใช้งาน'], 401)
                : redirect()->route('login')->with('error', 'ไม่ได้ใช้งานเกิน '.$limit.' นาที ระบบออกจากระบบให้เพื่อความปลอดภัย');
        }

        if (! $background || $session->get('kiosk')) {
            $session->put('last_active_at', $now);
        }

        return $next($request);
    }
}
