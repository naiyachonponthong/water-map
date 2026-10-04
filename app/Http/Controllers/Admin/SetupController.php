<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Province;
use App\Support\HostingStatus;
use App\Support\Settings;
use App\Support\SetupChecklist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SetupController extends Controller
{
    public function index(Request $request, SetupChecklist $checklist, HostingStatus $hosting)
    {
        $province = $this->province();
        $steps = $checklist->steps($province, $hosting);
        $step = max(1, min(5, $request->integer('step', 1)));
        $checks = collect($steps)->flatMap(fn ($item) => $item['checks']);

        return view('admin.setup', [
            'province' => $province,
            'provinces' => Province::where('is_active', true)->orderBy('name_th')->get(['id', 'name_th']),
            'steps' => $steps, 'step' => $step,
            'done' => $checks->where('ok', true)->count(), 'total' => $checks->count(),
            'review' => (array) Settings::get('setup_verification', $province->id, []),
        ]);
    }

    public function selectProvince(Request $request)
    {
        $data = $request->validate(['province_id' => ['required', 'integer', Rule::exists('provinces', 'id')->where('is_active', true)]]);
        $request->session()->put('admin_province_id', (int) $data['province_id']);

        return redirect()->route('admin.setup', ['step' => 2])->with('success', 'เลือกจังหวัดแล้ว');
    }

    public function saveBasics(Request $request)
    {
        $province = $this->province();
        $data = $request->validate([
            // Bind the visible form to its province, including when another tab switches province.
            'province_id' => ['required', 'integer', Rule::in([$province->id])],
            'privacy_controller' => ['required', 'string', 'max:200'],
            'privacy_contact' => ['required', 'string', 'max:300'],
            'hotline' => ['required', 'regex:/^[0-9][0-9\- ]{2,19}$/'],
            'center_lat' => ['required', 'numeric', 'between:5,21'],
            'center_lng' => ['required', 'numeric', 'between:97,106'],
            'default_zoom' => ['required', 'integer', 'between:7,15'],
        ], ['province_id.in' => 'จังหวัดถูกเปลี่ยนในหน้าต่างอื่น กรุณาโหลดหน้านี้ใหม่ก่อนบันทึก'], [
            'privacy_controller' => 'หน่วยงานผู้รับผิดชอบ', 'privacy_contact' => 'ช่องทางติดต่อ', 'hotline' => 'สายด่วน',
            'center_lat' => 'ละติจูด', 'center_lng' => 'ลองจิจูด',
        ]);
        DB::transaction(function () use ($data, $province) {
            foreach (['privacy_controller', 'privacy_contact', 'hotline'] as $key) {
                Settings::set($key, trim($data[$key]), $province->id);
            }
            $province->update(collect($data)->only(['center_lat', 'center_lng', 'default_zoom'])->all());
        });

        return redirect()->route('admin.setup', ['step' => 3])->with('success', 'บันทึกหน่วยงาน สายด่วน และพิกัดแล้ว');
    }

    public function saveReview(Request $request)
    {
        $province = $this->province();
        $request->validate([
            'province_id' => ['required', 'integer', Rule::in([$province->id])],
            'workflow' => ['nullable', 'boolean'], 'backup' => ['nullable', 'boolean'], 'sources' => ['nullable', 'boolean'],
        ]);
        Settings::set('setup_verification', [
            'workflow' => $request->boolean('workflow'), 'backup' => $request->boolean('backup'), 'sources' => $request->boolean('sources'),
            'confirmed_by' => $request->user()->id, 'confirmed_at' => now()->toIso8601String(),
        ], $province->id);

        return redirect()->route('admin.setup', ['step' => 5])->with('success', 'บันทึกผลที่คุณทดลองแล้ว กรุณาตรวจรายการที่ยังไม่ผ่านก่อนเปิดศูนย์');
    }
}
