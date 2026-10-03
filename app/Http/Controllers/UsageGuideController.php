<?php

namespace App\Http\Controllers;

use App\Models\Province;
use App\Support\UsageGuide;
use Illuminate\Http\Request;

class UsageGuideController extends Controller
{
    public function index(Request $request)
    {
        $province = Province::where('is_active', true)->where('slug', $request->cookie('flood_province'))->first();

        return $this->page($province);
    }

    public function showProvince(Province $province)
    {
        abort_unless($province->is_active, 404);

        return $this->page($province);
    }

    public function staff()
    {
        return $this->page(current_province(), 'layouts.app');
    }

    private function page(?Province $province, string $layout = 'layouts.public')
    {
        return view('guide.index', [
            'layout' => $layout,
            'province' => $province,
            'sections' => UsageGuide::sections(),
            'audiences' => ['all' => 'ทุกหัวข้อ', 'citizen' => 'ประชาชน', 'staff' => 'เจ้าหน้าที่', 'owner' => 'ผู้ดูแลระบบ'],
        ]);
    }
}
