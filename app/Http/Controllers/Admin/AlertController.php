<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\District;
use App\Support\AlertService;
use App\Support\StationOptions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * ประกาศเตือนภัยล่วงหน้า (ระบบสร้างเองจากสถานี/ฝน/จุดเสี่ยง และศูนย์ประกาศเอง)
 */
class AlertController extends Controller
{
    public function index(Request $request)
    {
        $province = $this->province();

        return view('admin.alerts.index', [
            'province' => $province,
            'active' => Alert::inProvince($province->id)->active()->with('acker:id,name')->severeFirst()->get(),
            'history' => Alert::inProvince($province->id)
                ->where(fn ($q) => $q->where('status', 'resolved')->orWhere('expires_at', '<=', now()))
                ->latest('updated_at')->paginate(20)->withQueryString(),
            'districts' => District::where('province_id', $province->id)->orderBy('sort')->get(['id', 'name_th']),
        ]);
    }

    public function store(Request $request, AlertService $alerts)
    {
        $province = $this->province();
        $data = $request->validate([
            'level' => ['required', Rule::in(array_keys(StationOptions::ALERT_LEVELS))],
            'title' => ['required', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:2000'],
            'district_ids' => ['nullable', 'array'],
            'district_ids.*' => [Rule::exists('districts', 'id')->where('province_id', $province->id)],
            'hours' => ['nullable', 'integer', 'between:1,168'],
        ], [], ['title' => 'หัวข้อ', 'level' => 'ระดับ']);

        $alerts->raise($province->id, 'manual:'.now()->format('YmdHis').':'.$request->user()->id, 'manual', $data['level'], $data['title'], $data['body'] ?? null, [
            'district_ids' => $data['district_ids'] ?? null,
            'is_public' => $request->boolean('is_public', true),
            'created_by' => $request->user()->id,
            'expires_at' => ! empty($data['hours']) ? now()->addHours((int) $data['hours']) : null,
        ]);

        return $this->ok('ประกาศเตือนภัยแล้ว');
    }

    public function acknowledge(Request $request, Alert $alert, AlertService $alerts)
    {
        $this->authorizeProvince($alert->province_id);
        $alerts->acknowledge($alert, $request->user());

        return $this->ok('รับทราบแล้ว');
    }

    public function resolve(Request $request, Alert $alert, AlertService $alerts)
    {
        $this->authorizeProvince($alert->province_id);
        $alerts->resolve($alert, $request->user());

        return $this->ok('ยกเลิกประกาศแล้ว');
    }
}
