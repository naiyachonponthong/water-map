<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /** จังหวัดที่กำลังทำงาน หรือ 404 ถ้ายังไม่มี */
    protected function province(): \App\Models\Province
    {
        return current_province() ?? abort(404, 'ยังไม่ได้กำหนดจังหวัด');
    }

    /** ผู้ใช้ระดับจังหวัดแตะข้อมูลจังหวัดอื่นไม่ได้ */
    protected function authorizeProvince(?int $provinceId): void
    {
        abort_unless(auth()->user()?->canManageProvince($provinceId), 403, 'ไม่มีสิทธิ์จัดการข้อมูลของจังหวัดนี้');
    }

    protected function ok(string $message, ?string $route = null, array $params = [])
    {
        return ($route ? redirect()->route($route, $params) : back())->with('success', $message);
    }
}
