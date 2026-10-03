<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * LINE Login (OAuth 2.1 / OpenID Connect) เขียนเองด้วย Http client ไม่ต้องลง package เพิ่ม
 * โหมด:
 *  - ยังไม่ล็อกอิน + LINE ผูกกับบัญชีแล้ว  -> เข้าสู่ระบบ
 *  - ยังไม่ล็อกอิน + LINE ยังไม่ผูก        -> ไปหน้าสมัคร (เติมชื่อจาก LINE)
 *  - ล็อกอินอยู่ (กดผูกจากหน้าโปรไฟล์)       -> ผูก LINE กับบัญชีนี้
 */
class LineLoginController extends Controller
{
    public function redirect(Request $request)
    {
        $cfg = config('floodthai.line');
        abort_unless(filled($cfg['channel_id']), 404, 'ยังไม่ได้ตั้งค่า LINE Login');

        $state = Str::random(40);
        $request->session()->put('line_state', $state);
        $request->session()->put('line_mode', Auth::check() ? 'link' : 'login');

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $cfg['channel_id'],
            'redirect_uri' => $cfg['callback'] ?: route('line.callback'),
            'state' => $state,
            'scope' => 'profile openid',
            'bot_prompt' => 'normal',
        ]);

        return redirect('https://access.line.me/oauth2/v2.1/authorize?'.$query);
    }

    public function callback(Request $request)
    {
        $cfg = config('floodthai.line');
        $mode = $request->session()->pull('line_mode', 'login');
        $back = $mode === 'link' ? 'profile.edit' : 'login';

        if ($request->filled('error') || $request->input('state') !== $request->session()->pull('line_state')) {
            return redirect()->route($back)->withErrors(['phone' => 'เชื่อมต่อ LINE ไม่สำเร็จ ลองใหม่อีกครั้ง']);
        }

        $token = Http::asForm()->post('https://api.line.me/oauth2/v2.1/token', [
            'grant_type' => 'authorization_code',
            'code' => $request->input('code'),
            'redirect_uri' => $cfg['callback'] ?: route('line.callback'),
            'client_id' => $cfg['channel_id'],
            'client_secret' => $cfg['channel_secret'],
        ]);
        if ($token->failed()) {
            return redirect()->route($back)->withErrors(['phone' => 'LINE ปฏิเสธการเข้าสู่ระบบ']);
        }

        $profile = Http::withToken($token->json('access_token'))->get('https://api.line.me/v2/profile');
        if ($profile->failed()) {
            return redirect()->route($back)->withErrors(['phone' => 'อ่านข้อมูลโปรไฟล์ LINE ไม่ได้']);
        }

        $line = [
            'id' => $profile->json('userId'),
            'name' => $profile->json('displayName'),
            'picture' => $profile->json('pictureUrl'),
        ];

        // ผูกบัญชี
        if ($mode === 'link' && Auth::check()) {
            if (User::where('line_user_id', $line['id'])->where('id', '!=', Auth::id())->exists()) {
                return redirect()->route('profile.edit')->withErrors(['line' => 'บัญชี LINE นี้ผูกกับผู้ใช้อื่นแล้ว']);
            }
            Auth::user()->update([
                'line_user_id' => $line['id'],
                'line_display_name' => $line['name'],
                'avatar_url' => $line['picture'],
            ]);

            return redirect()->route('profile.edit')->with('success', 'ผูกบัญชี LINE เรียบร้อย');
        }

        // เข้าสู่ระบบ
        $user = User::where('line_user_id', $line['id'])->first();
        if ($user) {
            if ($redirect = TwoFactorController::intercept($request, $user, true, 'LINE')) {
                return $redirect;
            }
            Auth::login($user, true);
            $request->session()->put('last_active_at', time());
            $request->session()->regenerate();
            $user->forceFill(['last_login_at' => now(), 'avatar_url' => $line['picture'], 'line_display_name' => $line['name']])->saveQuietly();
            AuditLog::record('login', $user, null, null, 'เข้าสู่ระบบด้วย LINE');

            return redirect()->intended(route('dashboard'));
        }

        // ยังไม่มีบัญชี ไปสมัคร
        $request->session()->put('line_pending', $line);

        return redirect()->route('register')->with('success', 'เชื่อม LINE แล้ว กรอกข้อมูลเพื่อขอใช้งานระบบ');
    }
}
