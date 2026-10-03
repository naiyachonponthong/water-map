<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\Announcement;
use App\Models\ExternalLink;
use App\Models\Province;
use App\Models\RiskPoint;
use App\Models\Shelter;
use App\Models\WaterStation;
use App\Support\ForecastService;
use App\Support\Settings;

/**
 * หน้าเว็บประชาชน (โครงพื้นฐาน เฟสถัดไปจะเติมรายงานน้ำ แผนที่ พยากรณ์ และฟอร์มขอความช่วยเหลือ)
 */
class PublicController extends Controller
{
    public function root()
    {
        $slug = request()->cookie('flood_province');

        return $slug && Province::where('slug', $slug)->exists()
            ? redirect()->route('public.province', $slug)
            : redirect()->route('public.provinces');
    }

    public function provinces()
    {
        $provinces = Province::where('is_active', true)->orderBy('code')->get(['id', 'slug', 'name_th', 'region', 'command_open']);

        return view('public.provinces', [
            'open' => $provinces->where('command_open', true)->sortBy('name_th'),
            'byRegion' => $provinces->groupBy('region'),
        ]);
    }

    public function showProvince(Province $province)
    {
        abort_unless($province->is_active, 404);

        return response()->view('public.province', [
            'province' => $province,
            'provinceOptions' => Province::where('is_active', true)->orderBy('name_th')->get(['slug', 'name_th']),
            'contacts' => $province->publicContacts(),
            'links' => ExternalLink::forProvince($province->id)->get(),
            'hotline' => Settings::get('hotline', $province->id),
            'notice' => Settings::get('public_notice', $province->id),
            'centerContact' => Settings::get('center_contact'),
            'reportsOpen' => (bool) Settings::get('reports_open', $province->id),
            'alerts' => Alert::inProvince($province->id)->active()->where('is_public', true)->severeFirst()->limit(5)->get(),
            'week' => ForecastService::week($province),
            'news' => Announcement::inProvince($province->id)->where('audience', 'public')->live()->orderByDesc('pinned')->latest('published_at')->limit(3)->get(),
            'shelters' => Shelter::inProvince($province->id)->where('is_public', true)->whereIn('status', ['open', 'full'])->orderByRaw("case status when 'open' then 0 else 1 end")->orderBy('name')->limit(4)->get(),
            'lineOaId' => Settings::get('line_oa_id', $province->id),
            'recoveryOpen' => (bool) Settings::get('recovery_open', $province->id),
            'stations' => WaterStation::inProvince($province->id)->where('is_active', true)->where('is_public', true)
                ->orderByRaw("case status when 'critical' then 0 when 'warning' then 1 when 'watch' then 2 when 'normal' then 3 else 4 end")
                ->orderBy('name')->limit(6)->get(),
            'risks' => RiskPoint::inProvince($province->id)->live()->where('is_public', true)
                ->with('subdistrict:id,name_th,code', 'district:id,name_th,code')
                ->orderByRaw("case status when 'threatened' then 0 else 1 end")
                ->orderByRaw("case severity when 'high' then 0 when 'medium' then 1 else 2 end")
                ->limit(200)->get(),
        ])->cookie('flood_province', $province->slug, 60 * 24 * 365);
    }

    /** ประกาศความเป็นส่วนตัว (PDPA) */
    public function privacy(Province $province)
    {
        abort_unless($province->is_active, 404);

        return view('public.privacy', [
            'province' => $province,
            'controller' => Settings::get('privacy_controller', $province->id) ?: 'ศูนย์ช่วยเหลือน้ำท่วม'.$province->fullName(),
            'contact' => Settings::get('privacy_contact', $province->id),
            'extra' => Settings::get('privacy_extra', $province->id),
            'hotline' => Settings::get('hotline', $province->id),
            'days' => (int) config('floodthai.pii_retention_days'),
        ]);
    }
}
