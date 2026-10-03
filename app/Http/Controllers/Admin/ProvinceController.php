<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Province;
use Illuminate\Http\Request;

/**
 * ผู้ดูแลระบบสูงสุด: ดูทุกจังหวัด เปิด/ปิดศูนย์สั่งการ ตั้งพิกัดกลาง
 */
class ProvinceController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $filter = $request->query('filter', 'all');

        $provinces = Province::query()
            ->withCount(['districts', 'users as active_users_count' => fn ($w) => $w->where('status', 'active')])
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('name_th', 'like', "%$q%")->orWhere('name_en', 'like', "%$q%")->orWhere('slug', 'like', "%$q%")))
            ->when($filter === 'open', fn ($w) => $w->where('command_open', true))
            ->when($filter === 'help', fn ($w) => $w->where('web_help_open', true))
            ->orderByDesc('command_open')->orderBy('code')
            ->get();

        $totals = [
            'all' => Province::count(),
            'open' => Province::where('command_open', true)->count(),
            'help' => Province::where('web_help_open', true)->count(),
        ];

        return view('admin.provinces.index', compact('provinces', 'totals', 'filter'));
    }

    public function update(Request $request, Province $prov)
    {
        $data = $request->validate([
            'name_th' => ['required', 'string', 'max:100'],
            'name_en' => ['nullable', 'string', 'max:100'],
            'center_lat' => ['nullable', 'numeric', 'between:5,21'],
            'center_lng' => ['nullable', 'numeric', 'between:97,106'],
            'default_zoom' => ['required', 'integer', 'between:7,15'],
            'is_active' => ['nullable', 'boolean'],
            'command_open' => ['nullable', 'boolean'],
            'web_help_open' => ['nullable', 'boolean'],
        ], [], ['name_th' => 'ชื่อจังหวัด', 'center_lat' => 'ละติจูด', 'center_lng' => 'ลองจิจูด']);

        foreach (['is_active', 'command_open', 'web_help_open'] as $k) {
            $data[$k] = $request->boolean($k);
        }
        // รับแจ้งทางเว็บได้เฉพาะเมื่อเปิดศูนย์สั่งการแล้ว
        $data['web_help_open'] = $data['web_help_open'] && $data['command_open'];
        if ($data['command_open'] && ! $prov->command_open) {
            $data['command_opened_at'] = now();
        }

        $prov->update($data);

        return $this->ok('บันทึก '.$prov->fullName().' แล้ว');
    }
}
