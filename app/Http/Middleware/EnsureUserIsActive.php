<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * บัญชีที่ยังไม่อนุมัติ ส่งไปหน้ารออนุมัติ / บัญชีที่ถูกระงับ ออกจากระบบทันที
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($user->status === 'suspended') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['phone' => 'บัญชีนี้ถูกระงับการใช้งาน ติดต่อผู้ดูแลศูนย์ของจังหวัด']);
        }

        if ($user->status === 'pending' && ! $request->routeIs('pending', 'logout')) {
            return redirect()->route('pending');
        }

        return $next($request);
    }
}
