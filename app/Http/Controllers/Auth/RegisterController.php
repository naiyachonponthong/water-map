<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Province;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * เจ้าหน้าที่/ทีมกู้ภัยสมัครเอง สถานะ pending จนกว่าผู้อำนวยการศูนย์จะอนุมัติและกำหนดบทบาท
 */
class RegisterController extends Controller
{
    /** บทบาทที่ขอได้ตอนสมัคร (ผู้อำนวยการศูนย์และผู้ดูแลระบบสูงสุดกำหนดโดยผู้ดูแลเท่านั้น) */
    public const REQUESTABLE = ['dispatcher', 'moderator', 'team-leader', 'team-member', 'shelter-staff'];

    public function show(Request $request)
    {
        return view('auth.register', [
            'provinces' => Province::orderBy('name_th')->get(['id', 'name_th']),
            'roles' => collect(config('floodthai.roles'))->only(self::REQUESTABLE),
            'line' => $request->session()->get('line_pending'),
        ]);
    }

    public function store(Request $request)
    {
        $line = $request->session()->get('line_pending');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:20'],
            'province_id' => ['required', 'exists:provinces,id'],
            'organization' => ['required', 'string', 'max:150'],
            'position' => ['nullable', 'string', 'max:100'],
            'requested_role' => ['required', Rule::in(self::REQUESTABLE)],
            'password' => [$line ? 'nullable' : 'required', 'confirmed', Password::defaults()],
        ], [], [
            'name' => 'ชื่อ-นามสกุล', 'phone' => 'เบอร์โทร', 'province_id' => 'จังหวัด',
            'organization' => 'หน่วยงาน', 'requested_role' => 'บทบาทที่ขอ', 'password' => 'รหัสผ่าน',
        ]);

        $data['phone'] = User::normalizePhone($data['phone']);
        if (strlen($data['phone']) < 9 || User::withTrashed()->where('phone', $data['phone'])->exists()) {
            return back()->withInput()->withErrors(['phone' => strlen($data['phone']) < 9 ? 'เบอร์โทรไม่ถูกต้อง' : 'เบอร์นี้มีบัญชีอยู่แล้ว']);
        }

        $user = User::create($data + [
            'status' => 'pending',
            'line_user_id' => $line['id'] ?? null,
            'line_display_name' => $line['name'] ?? null,
            'avatar_url' => $line['picture'] ?? null,
        ]);
        $request->session()->forget('line_pending');

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('pending');
    }

    public function pending(Request $request)
    {
        if ($request->user()->isActive()) {
            return redirect()->route('dashboard');
        }

        return view('auth.pending', ['user' => $request->user()->load('province')]);
    }
}
