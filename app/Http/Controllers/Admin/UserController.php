<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Province;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $me = $request->user();
        $status = $request->query('status', 'all');
        $role = $request->query('role');
        $q = trim((string) $request->query('q'));

        $base = User::query()
            ->when(! $me->isSuperAdmin(), fn ($w) => $w->where('province_id', $me->province_id))
            ->when($me->isSuperAdmin() && $request->filled('province'), fn ($w) => $w->where('province_id', $request->integer('province')));

        $counts = (clone $base)->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        $users = (clone $base)
            ->with(['roles:id,name', 'province:id,name_th', 'approver:id,name'])
            ->when($status !== 'all', fn ($w) => $w->where('status', $status))
            ->when($role, fn ($w) => $w->role($role))
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s
                ->where('name', 'like', "%$q%")
                ->orWhere('phone', 'like', '%'.User::normalizePhone($q).'%')
                ->orWhere('organization', 'like', "%$q%")))
            ->orderByRaw("case status when 'pending' then 0 when 'active' then 1 else 2 end")
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'counts' => $counts,
            'status' => $status,
            'roles' => $this->assignableRoles($me),
            'allRoles' => config('floodthai.roles'),
            'provinces' => $me->isSuperAdmin() ? Province::orderBy('name_th')->get(['id', 'name_th']) : collect(),
        ]);
    }

    public function store(Request $request)
    {
        $me = $request->user();
        $data = $this->validated($request);
        $plain = $data['password'] ?: $this->randomPassword();

        $user = User::create([
            ...collect($data)->except(['role', 'password'])->all(),
            'password' => $plain,
            'status' => 'active',
            'approved_by' => $me->id,
            'approved_at' => now(),
        ]);
        $user->syncRoles([$data['role']]);

        return back()->with('success', "เพิ่มผู้ใช้ {$user->name} แล้ว")
            ->with('credential', ['name' => $user->name, 'phone' => $user->phone, 'password' => $data['password'] ? null : $plain]);
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeUser($request, $user);
        $data = $this->validated($request, $user);

        $user->update(collect($data)->except(['role', 'password'])->all());
        if (! empty($data['password'])) {
            $user->update(['password' => $data['password']]);
        }
        if ($user->id !== $request->user()->id) {
            $user->syncRoles([$data['role']]);
        }

        return $this->ok("บันทึกข้อมูล {$user->name} แล้ว");
    }

    public function approve(Request $request, User $user)
    {
        $this->authorizeUser($request, $user);
        $role = $request->validate([
            'role' => ['required', Rule::in(array_keys($this->assignableRoles($request->user())))],
        ], [], ['role' => 'บทบาท'])['role'];

        $user->update(['status' => 'active', 'approved_by' => $request->user()->id, 'approved_at' => now()]);
        $user->syncRoles([$role]);
        AuditLog::record('approved', $user, null, ['role' => $role], "อนุมัติ {$user->name} เป็น ".config("floodthai.roles.$role"));

        return $this->ok("อนุมัติ {$user->name} แล้ว");
    }

    public function suspend(Request $request, User $user)
    {
        $this->authorizeUser($request, $user);
        abort_if($user->id === $request->user()->id, 422, 'ระงับบัญชีตัวเองไม่ได้');
        $user->update(['status' => 'suspended']);
        AuditLog::record('suspended', $user, null, null, "ระงับบัญชี {$user->name}");

        return $this->ok("ระงับบัญชี {$user->name} แล้ว");
    }

    public function activate(Request $request, User $user)
    {
        $this->authorizeUser($request, $user);
        abort_if($user->roles->isEmpty(), 422, 'กำหนดบทบาทก่อนเปิดใช้งาน');
        $user->update(['status' => 'active']);

        return $this->ok("เปิดใช้งาน {$user->name} อีกครั้งแล้ว");
    }

    public function resetPassword(Request $request, User $user)
    {
        $this->authorizeUser($request, $user);
        $plain = $this->randomPassword();
        $user->update(['password' => $plain]);

        return back()->with('success', "ตั้งรหัสผ่านใหม่ให้ {$user->name} แล้ว")
            ->with('credential', ['name' => $user->name, 'phone' => $user->phone, 'password' => $plain]);
    }

    /** ผู้ใช้ทำมือถือหาย: ล้าง 2 ชั้น ให้ตั้งใหม่ตอนเข้าครั้งถัดไป */
    public function resetTwoFactor(Request $request, User $user)
    {
        $this->authorizeUser($request, $user);
        abort_if($user->id === $request->user()->id, 422, 'ล้างของตัวเองที่หน้าโปรไฟล์');
        $user->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null, 'two_factor_recovery_codes' => null, 'two_factor_last_step' => null])->save();
        \App\Models\AuditLog::record('updated', $user, null, ['two_factor' => 'reset'], 'ล้างยืนยันตัวตน 2 ชั้นของ '.$user->name);

        return back()->with('success', "ล้างยืนยันตัวตน 2 ชั้นของ {$user->name} แล้ว");
    }

    public function destroy(Request $request, User $user)
    {
        $this->authorizeUser($request, $user);
        abort_if($user->id === $request->user()->id, 422, 'ลบบัญชีตัวเองไม่ได้');
        $user->delete();

        return $this->ok("ลบ {$user->name} แล้ว");
    }

    /* ------------------------------------------------------------------ */

    protected function validated(Request $request, ?User $user = null): array
    {
        $me = $request->user();
        $roles = array_keys($this->assignableRoles($me));
        // แก้ไขตัวเอง: คงบทบาทเดิมไว้ได้แม้ไม่อยู่ในรายการที่กำหนดได้
        if ($user && $user->id === $me->id) {
            $roles = array_merge($roles, $user->getRoleNames()->all());
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users')->ignore($user?->id)],
            'organization' => ['nullable', 'string', 'max:150'],
            'position' => ['nullable', 'string', 'max:100'],
            'role' => ['required', Rule::in($roles)],
            'province_id' => [$me->isSuperAdmin() ? 'nullable' : 'prohibited', 'exists:provinces,id'],
            'password' => ['nullable', Password::defaults()],
        ], [], [
            'name' => 'ชื่อ', 'phone' => 'เบอร์โทร', 'email' => 'อีเมล', 'role' => 'บทบาท',
            'province_id' => 'จังหวัด', 'password' => 'รหัสผ่าน',
        ]);

        $data['phone'] = User::normalizePhone($data['phone']);
        $dupe = User::withTrashed()->where('phone', $data['phone'])->when($user, fn ($q) => $q->where('id', '!=', $user->id))->exists();
        if (strlen($data['phone']) < 9 || $dupe) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'phone' => $dupe ? 'เบอร์นี้มีบัญชีอยู่แล้ว' : 'เบอร์โทรไม่ถูกต้อง',
            ]);
        }

        if (! $me->isSuperAdmin()) {
            $data['province_id'] = $me->province_id;
        }
        if ($data['role'] !== 'super-admin' && empty($data['province_id'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['province_id' => 'บทบาทนี้ต้องเลือกจังหวัด']);
        }

        return $data;
    }

    /** บทบาทที่ผู้ใช้คนนี้กำหนดให้คนอื่นได้ */
    protected function assignableRoles(User $me): array
    {
        $roles = config('floodthai.roles');

        return $me->isSuperAdmin() ? $roles : collect($roles)->except('super-admin')->all();
    }

    protected function authorizeUser(Request $request, User $user): void
    {
        $me = $request->user();
        if ($me->isSuperAdmin()) {
            return;
        }
        abort_if($user->isSuperAdmin(), 403, 'แก้ไขผู้ดูแลระบบสูงสุดไม่ได้');
        $this->authorizeProvince($user->province_id);
    }

    protected function randomPassword(): string
    {
        // อ่านง่าย พิมพ์บนมือถือสะดวก: ตัวพิมพ์เล็ก 4 + ตัวเลข 4
        return Str::lower(Str::random(4)).random_int(1000, 9999);
    }
}
