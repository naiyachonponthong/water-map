<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** บทบาทที่สิทธิ์สูง (เห็นเบอร์โทร ข้อมูลผู้ป่วย) ต้องเปิด 2 ชั้นก่อนใช้งาน */
class RequireTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && $user->mustUseTwoFactor() && ! $user->hasTwoFactor()
            && ! $request->routeIs('profile.*', 'two-factor.*', 'logout')) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'ต้องเปิดยืนยันตัวตน 2 ชั้นก่อนใช้งาน'], 403);
            }

            return redirect()->route('profile.edit', ['tab' => '2fa'])
                ->with('error', 'บทบาทของคุณต้องเปิดยืนยันตัวตน 2 ชั้นก่อนใช้งานระบบ');
        }

        return $next($request);
    }
}
