<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function show()
    {
        return view('auth.login', [
            'lineEnabled' => filled(config('floodthai.line.channel_id')),
        ]);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string'],
        ], [], ['phone' => 'เบอร์โทร', 'password' => 'รหัสผ่าน']);

        $phone = User::normalizePhone($data['phone']);

        if (! Auth::validate(['phone' => $phone, 'password' => $data['password']])) {
            AuditLog::record('login_failed', null, null, ['phone' => $phone], 'เข้าสู่ระบบไม่สำเร็จ');

            throw ValidationException::withMessages(['phone' => 'เบอร์โทรหรือรหัสผ่านไม่ถูกต้อง']);
        }

        /** @var User $user */
        $user = Auth::getLastAttempted();
        // เปิด 2 ชั้นไว้: รหัสผ่านถูกแล้ว แต่ต้องกรอกรหัสจากแอปก่อน
        if ($redirect = TwoFactorController::intercept($request, $user, $request->boolean('remember'), 'เบอร์โทร')) {
            return $redirect;
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $request->session()->put('last_active_at', time());
        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        AuditLog::record('login', $user, null, null, 'เข้าสู่ระบบด้วยเบอร์โทร');

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        AuditLog::record('logout', $request->user());
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
