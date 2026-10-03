<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Province;
use App\Models\Shelter;
use App\Support\Settings;
use App\Support\ShelterService;
use Illuminate\Http\Request;

/**
 * หน้าประชาชน: ศูนย์พักพิง, ค้นหาญาติในศูนย์, ประกาศ
 */
class ReliefController extends Controller
{
    public function shelters(Province $province)
    {
        abort_unless($province->is_active, 404);
        $shelters = Shelter::inProvince($province->id)->where('is_public', true)->whereIn('status', ['open', 'full', 'preparing'])
            ->with(['district:id,name_th', 'needs' => fn ($q) => $q->where('status', 'open')->orderByRaw("case priority when 'urgent' then 0 else 1 end")])
            ->orderByRaw("case status when 'open' then 0 when 'full' then 1 else 2 end")->orderBy('name')->get();

        return view('public.relief.shelters', [
            'province' => $province,
            'shelters' => $shelters,
            'hotline' => Settings::get('hotline', $province->id),
        ]);
    }

    public function find(Province $province)
    {
        abort_unless($province->is_active, 404);

        return view('public.relief.find', ['province' => $province, 'results' => null, 'phone' => null]);
    }

    public function lookup(Request $request, Province $province, ShelterService $service)
    {
        abort_unless($province->is_active, 404);
        $phone = $request->validate(['phone' => ['required', 'string', 'regex:/^[0-9+\s\-]{9,15}$/']], ['phone.regex' => 'เบอร์โทรไม่ถูกต้อง'], ['phone' => 'เบอร์โทร'])['phone'];

        return view('public.relief.find', ['province' => $province, 'results' => $service->lookup($province, $phone), 'phone' => $phone]);
    }

    public function news(Province $province)
    {
        abort_unless($province->is_active, 404);

        return view('public.relief.news', [
            'province' => $province,
            'items' => Announcement::inProvince($province->id)->where('audience', 'public')->whereNotNull('published_at')
                ->where('published_at', '<=', now())->orderByDesc('pinned')->latest('published_at')->paginate(15),
        ]);
    }
}
