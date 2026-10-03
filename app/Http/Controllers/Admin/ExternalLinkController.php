<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExternalLink;
use Illuminate\Http\Request;

class ExternalLinkController extends Controller
{
    public function store(Request $request)
    {
        $province = $this->province();
        $data = $this->validated($request);
        $global = $request->user()->isSuperAdmin() && $request->boolean('global');
        $data['province_id'] = $global ? null : $province->id;
        $data['sort'] = (int) ExternalLink::where('province_id', $data['province_id'])->max('sort') + 1;
        ExternalLink::create($data);

        return $this->ok('เพิ่มลิงก์แล้ว', 'admin.settings.index', ['tab' => 'links']);
    }

    public function update(Request $request, ExternalLink $link)
    {
        $this->authorizeLink($request, $link);
        $link->update($this->validated($request));

        return $this->ok('บันทึกลิงก์แล้ว', 'admin.settings.index', ['tab' => 'links']);
    }

    public function destroy(Request $request, ExternalLink $link)
    {
        $this->authorizeLink($request, $link);
        $link->delete();

        return $this->ok('ลบลิงก์แล้ว', 'admin.settings.index', ['tab' => 'links']);
    }

    /** ลิงก์กลาง (ทุกจังหวัด) แก้ได้เฉพาะผู้ดูแลระบบสูงสุด */
    protected function authorizeLink(Request $request, ExternalLink $link): void
    {
        if ($link->province_id === null) {
            abort_unless($request->user()->isSuperAdmin(), 403, 'ลิงก์กลางแก้ได้เฉพาะผู้ดูแลระบบสูงสุด');

            return;
        }
        $this->authorizeProvince($link->province_id);
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
            'url' => ['required', 'url:https,http', 'max:500'],
            'source_name' => ['nullable', 'string', 'max:100'],
        ], [], ['title' => 'ชื่อลิงก์', 'url' => 'ที่อยู่ลิงก์']);
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
