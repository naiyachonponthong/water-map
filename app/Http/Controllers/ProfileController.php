<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('profile.edit', [
            'user' => $request->user()->load('province'),
            'lineEnabled' => filled(config('floodthai.line.channel_id')),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users')->ignore($user->id)],
            'organization' => ['nullable', 'string', 'max:150'],
            'position' => ['nullable', 'string', 'max:100'],
        ], [], ['name' => 'ชื่อ', 'phone' => 'เบอร์โทร', 'email' => 'อีเมล']);

        $data['phone'] = User::normalizePhone($data['phone']);
        if (User::withTrashed()->where('phone', $data['phone'])->where('id', '!=', $user->id)->exists()) {
            return back()->withErrors(['phone' => 'เบอร์นี้ถูกใช้กับบัญชีอื่นแล้ว'])->withInput();
        }

        $user->update($data);

        return $this->ok('บันทึกข้อมูลส่วนตัวแล้ว');
    }

    public function password(Request $request)
    {
        $user = $request->user();
        $request->validate([
            'current_password' => [$user->password ? 'required' : 'nullable', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [], ['current_password' => 'รหัสผ่านเดิม', 'password' => 'รหัสผ่านใหม่']);

        $user->update(['password' => $request->input('password')]);

        return $this->ok('เปลี่ยนรหัสผ่านแล้ว');
    }

    public function unlinkLine(Request $request)
    {
        $user = $request->user();
        if (! $user->password) {
            return back()->withErrors(['line' => 'ตั้งรหัสผ่านก่อนยกเลิกการผูก LINE ไม่เช่นนั้นจะเข้าระบบไม่ได้']);
        }
        $user->update(['line_user_id' => null, 'line_display_name' => null]);

        return $this->ok('ยกเลิกการผูก LINE แล้ว');
    }
}
