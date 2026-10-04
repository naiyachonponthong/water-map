<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\District;
use App\Models\HelpRequest;
use App\Support\CaseOptions;
use App\Models\Province;
use App\Models\Subdistrict;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user->can('dashboard.view')) {
            return redirect()->route($user->can('field.use') ? 'field.app' : 'menu.index');
        }

        $province = $this->province();
        $pid = $province->id;

        $districtCount = District::where('province_id', $pid)->count();
        $districtWithBoundary = District::where('province_id', $pid)->whereNotNull('boundary')->count();
        $subdistrictCount = Subdistrict::where('province_id', $pid)->count();
        $subdistrictWithBoundary = Subdistrict::where('province_id', $pid)->whereNotNull('boundary')->count();

        $users = User::where('province_id', $pid)->get(['id', 'status']);
        $roleCounts = User::where('province_id', $pid)->where('status', 'active')
            ->with('roles:id,name')->get()
            ->flatMap(fn ($u) => $u->roles->pluck('name'))
            ->countBy();

        $pendingUsers = $users->where('status', 'pending')->count();

        // รายการเตรียมความพร้อมก่อนเปิดศูนย์สั่งการ
        $checklist = [
            ['label' => 'ตั้งพิกัดกลางจังหวัด', 'done' => $province->hasCenter(), 'url' => route('admin.settings.index')],
            ['label' => 'นำเข้าขอบเขตอำเภอ', 'done' => $districtCount > 0 && $districtWithBoundary === $districtCount, 'detail' => "$districtWithBoundary/$districtCount", 'url' => route('admin.areas.index')],
            ['label' => 'นำเข้าขอบเขตตำบล', 'done' => $subdistrictCount > 0 && $subdistrictWithBoundary === $subdistrictCount, 'detail' => "$subdistrictWithBoundary/$subdistrictCount", 'url' => route('admin.areas.index')],
            ['label' => 'ตั้งเบอร์ฉุกเฉินของจังหวัด', 'done' => $province->emergencyContacts()->where('is_active', true)->exists(), 'url' => route('admin.settings.index', ['tab' => 'contacts'])],
            ['label' => 'มีผู้อำนวยการศูนย์', 'done' => ($roleCounts['province-admin'] ?? 0) > 0, 'url' => route('admin.users.index')],
            ['label' => 'มีเจ้าหน้าที่สั่งการ', 'done' => ($roleCounts['dispatcher'] ?? 0) > 0, 'url' => route('admin.users.index')],
        ];

        return view('dashboard.index', [
            'province' => $province,
            'checklist' => $checklist,
            'pendingUsers' => $pendingUsers,
            'dataHealth' => app(\App\Support\DataSourceHealth::class)->snapshot($province),
            'snap' => \App\Support\LiveSnapshot::cached($province),
        ]);
    }

    /** ขอบเขตอำเภอ (และตำบลถ้าขอ) ของจังหวัดปัจจุบัน เป็น GeoJSON FeatureCollection */
    public function areas(Request $request)
    {
        $province = $this->province();
        $level = $request->query('level') === 'subdistrict' ? 'subdistrict' : 'district';
        $model = $level === 'subdistrict' ? Subdistrict::class : District::class;

        $features = $model::query()
            ->where('province_id', $province->id)
            ->whereNotNull('boundary')
            ->get(['id', 'name_th', 'code', 'boundary'])
            ->map(fn ($a) => [
                'type' => 'Feature',
                'id' => $a->id,
                'properties' => ['id' => $a->id, 'name' => $a->name_th, 'code' => $a->code],
                'geometry' => json_decode($a->boundary, true),
            ]);

        return response()->json(['type' => 'FeatureCollection', 'features' => $features])
            ->header('Cache-Control', 'private, max-age=300');
    }

    public function switchProvince(Request $request)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $province = Province::findOrFail($request->integer('province_id'));
        $request->session()->put('admin_province_id', $province->id);

        return back()->with('success', 'สลับไปทำงานที่ '.$province->fullName());
    }
}
