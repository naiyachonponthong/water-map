<?php

namespace App\Http\Middleware;

use App\Models\Province;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * กำหนดจังหวัดที่กำลังทำงานอยู่:
 *  - หน้าเว็บประชาชน /{province} ใช้จังหวัดจาก URL
 *  - ผู้ใช้ระดับจังหวัด ใช้จังหวัดของตัวเองเสมอ
 *  - ผู้ดูแลระบบสูงสุด สลับจังหวัดได้ (เก็บใน session)
 */
class SetCurrentProvince
{
    public function handle(Request $request, Closure $next): Response
    {
        $province = $this->resolve($request);

        if ($province) {
            app()->instance('currentProvince', $province);
        }
        View::share('currentProvince', $province);

        return $next($request);
    }

    protected function resolve(Request $request): ?Province
    {
        $param = $request->route('province');
        if ($param instanceof Province) {
            return $param;
        }
        if (is_string($param) && $param !== '') {
            return Province::where('slug', $param)->first();
        }

        $user = $request->user();
        if (! $user) {
            return null;
        }

        if (! $user->isSuperAdmin()) {
            return $user->province;
        }

        $id = $request->session()->get('admin_province_id');

        return ($id ? Province::find($id) : null)
            ?? Province::where('command_open', true)->orderBy('name_th')->first()
            ?? $user->province
            ?? Province::orderBy('name_th')->first();
    }
}
