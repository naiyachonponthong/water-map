<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * ยืนยันตัวตน 2 ชั้น: หน้ากรอกรหัสหลังรหัสผ่าน/LINE ถูกต้อง และการเปิด/ปิดในหน้าโปรไฟล์
 */
class TwoFactorController extends Controller
{
    /** เรียกจาก Login/LINE: ถ้าบัญชีเปิด 2 ชั้น พักไว้ก่อน ยังไม่ให้เข้า */
    public static function intercept(Request $request, User $user, bool $remember, string $via)
    {
        if (! $user->hasTwoFactor()) {
            return null;
        }
        $request->session()->put('2fa.pending', ['id' => $user->id, 'remember' => $remember, 'via' => $via, 'at' => time()]);

        return redirect()->route('two-factor.challenge');
    }

    public function challenge(Request $request)
    {
        $p = $request->session()->get('2fa.pending');
        if (! $p || time() - $p['at'] > 600) {
            $request->session()->forget('2fa.pending');

            return redirect()->route('login')->with('error', 'หมดเวลา กรุณาเข้าสู่ระบบใหม่');
        }

        return view('auth.two-factor');
    }

    public function verify(Request $request)
    {
        $p = $request->session()->get('2fa.pending');
        abort_unless($p && time() - $p['at'] <= 600, 419);
        $data = $request->validate(['code' => ['required', 'string', 'max:20']], [], ['code' => 'รหัส']);

        $user = User::find($p['id']);
        if (! $user || ! $user->verifyTwoFactor($data['code'])) {
            AuditLog::record('login_failed', $user, null, null, 'รหัสยืนยัน 2 ชั้นไม่ถูกต้อง');

            throw ValidationException::withMessages(['code' => 'รหัสไม่ถูกต้อง หรือใช้ไปแล้ว']);
        }

        $request->session()->forget('2fa.pending');
        Auth::login($user, (bool) $p['remember']);
        $request->session()->regenerate();
        $request->session()->put('last_active_at', time());
        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        AuditLog::record('login', $user, null, null, 'เข้าสู่ระบบ ('.$p['via'].') + ยืนยัน 2 ชั้น');

        return redirect()->intended(route('dashboard'));
    }

    /* ---------------- ตั้งค่าในโปรไฟล์ ---------------- */

    public function enable(Request $request)
    {
        $user = $request->user();
        if ($user->hasTwoFactor()) {
            return back()->with('error', 'เปิดใช้งานอยู่แล้ว');
        }
        $user->forceFill(['two_factor_secret' => Totp::generateSecret(), 'two_factor_confirmed_at' => null, 'two_factor_recovery_codes' => null])->save();

        return redirect()->route('profile.edit', ['tab' => '2fa'])->with('success', 'สแกน QR ด้วยแอป Authenticator แล้วกรอกรหัส 6 หลักเพื่อยืนยัน');
    }

    public function confirm(Request $request)
    {
        $user = $request->user();
        $code = $request->validate(['code' => ['required', 'string', 'max:10']], [], ['code' => 'รหัส'])['code'];
        $step = $user->two_factor_secret ? Totp::verify($user->two_factor_secret, $code) : null;
        if ($step === null) {
            throw ValidationException::withMessages(['code' => 'รหัสไม่ถูกต้อง ตรวจว่าเวลาบนมือถือตรง']);
        }
        $codes = Totp::recoveryCodes();
        $user->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_last_step' => $step, 'two_factor_recovery_codes' => $codes])->save();
        AuditLog::record('updated', $user, null, ['two_factor' => 'enabled'], 'เปิดยืนยันตัวตน 2 ชั้น');

        return redirect()->route('profile.edit', ['tab' => '2fa'])->with('success', 'เปิดยืนยันตัวตน 2 ชั้นแล้ว')->with('recovery_codes', $codes);
    }

    public function recoveryCodes(Request $request)
    {
        $user = $request->user();
        abort_unless($user->hasTwoFactor(), 400);
        $this->checkPassword($request, $user);
        $codes = Totp::recoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $codes])->save();

        return back()->with('success', 'สร้างรหัสสำรองชุดใหม่แล้ว ชุดเดิมใช้ไม่ได้อีก')->with('recovery_codes', $codes);
    }

    public function disable(Request $request)
    {
        $user = $request->user();
        $this->checkPassword($request, $user);
        $user->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null, 'two_factor_recovery_codes' => null, 'two_factor_last_step' => null])->save();
        AuditLog::record('updated', $user, null, ['two_factor' => 'disabled'], 'ปิดยืนยันตัวตน 2 ชั้น');

        return back()->with('success', $user->mustUseTwoFactor() ? 'ปิดแล้ว บทบาทของคุณต้องตั้งค่าใหม่ก่อนใช้งานต่อ' : 'ปิดยืนยันตัวตน 2 ชั้นแล้ว');
    }

    protected function checkPassword(Request $request, User $user): void
    {
        $pw = $request->validate(['password' => ['required', 'string']], [], ['password' => 'รหัสผ่าน'])['password'];
        if (! $user->password || ! Hash::check($pw, $user->password)) {
            throw ValidationException::withMessages(['password' => 'รหัสผ่านไม่ถูกต้อง']);
        }
    }
}
