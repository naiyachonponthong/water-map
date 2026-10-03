<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\District;
use App\Models\Subdistrict;
use App\Support\AreaImporter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AreaController extends Controller
{
    public function index(Request $request)
    {
        $province = $this->province();

        $districts = District::where('province_id', $province->id)
            ->select(['id', 'province_id', 'code', 'name_th', 'name_en', 'sort', 'center_lat', 'center_lng', 'bbox_south'])
            ->withCount([
                'subdistricts',
                'subdistricts as subdistricts_with_boundary_count' => fn ($q) => $q->whereNotNull('boundary'),
            ])
            ->orderBy('sort')->orderBy('name_th')
            ->get();

        $selected = $districts->firstWhere('id', $request->integer('district')) ?? $districts->first();
        $subdistricts = $selected
            ? Subdistrict::where('district_id', $selected->id)->orderBy('name_th')
                ->get(['id', 'district_id', 'code', 'name_th', 'postcode', 'center_lat', 'center_lng', 'bbox_south'])
            : collect();

        return view('admin.areas.index', compact('province', 'districts', 'selected', 'subdistricts'));
    }

    public function geojson(Request $request)
    {
        return app(\App\Http\Controllers\DashboardController::class)->areas($request);
    }

    public function storeDistrict(Request $request)
    {
        $province = $this->province();
        $data = $this->validateDistrict($request);
        $district = District::create($data + ['province_id' => $province->id]);

        return $this->ok("เพิ่ม {$district->shortName()} แล้ว", 'admin.areas.index', ['district' => $district->id]);
    }

    public function updateDistrict(Request $request, District $district)
    {
        $this->authorizeProvince($district->province_id);
        $district->update($this->validateDistrict($request, $district));

        return $this->ok("บันทึก {$district->shortName()} แล้ว");
    }

    public function destroyDistrict(Request $request, District $district)
    {
        $this->authorizeProvince($district->province_id);
        // เฟสถัดไป: ห้ามลบถ้ามีเคส/รายงานผูกอยู่
        $district->delete();

        return $this->ok("ลบ {$district->shortName()} และตำบลในอำเภอนี้แล้ว", 'admin.areas.index');
    }

    public function storeSubdistrict(Request $request)
    {
        $data = $this->validateSubdistrict($request);
        $district = District::findOrFail($data['district_id']);
        $this->authorizeProvince($district->province_id);
        $sub = Subdistrict::create($data + ['province_id' => $district->province_id]);

        return $this->ok("เพิ่ม ต.{$sub->name_th} แล้ว", 'admin.areas.index', ['district' => $district->id]);
    }

    public function updateSubdistrict(Request $request, Subdistrict $subdistrict)
    {
        $this->authorizeProvince($subdistrict->province_id);
        $data = $this->validateSubdistrict($request, $subdistrict);
        $subdistrict->update($data);

        return $this->ok("บันทึก ต.{$subdistrict->name_th} แล้ว");
    }

    public function destroySubdistrict(Request $request, Subdistrict $subdistrict)
    {
        $this->authorizeProvince($subdistrict->province_id);
        $subdistrict->delete();

        return $this->ok("ลบ ต.{$subdistrict->name_th} แล้ว");
    }

    public function import(Request $request)
    {
        $province = $this->province();
        $request->validate([
            'level' => ['required', Rule::in(['district', 'subdistrict'])],
            'file' => ['required', 'file', 'max:51200'],
            'boundary_only' => ['nullable', 'boolean'],
        ], [], ['level' => 'ระดับพื้นที่', 'file' => 'ไฟล์']);

        $json = json_decode((string) file_get_contents($request->file('file')->getRealPath()), true);
        if (! is_array($json) || ! in_array($json['type'] ?? null, ['FeatureCollection', 'Feature'], true)) {
            return back()->withErrors(['file' => 'ไฟล์ต้องเป็น GeoJSON (FeatureCollection) ระบบพิกัด WGS84']);
        }

        @set_time_limit(300);
        $importer = new AreaImporter($province);
        $report = $importer->import($json, $request->input('level'), $request->boolean('boundary_only'));

        $levelLabel = $request->input('level') === 'district' ? 'อำเภอ' : 'ตำบล';
        AuditLog::record('imported', $province, null, $report, "นำเข้าขอบเขต{$levelLabel}");

        $msg = "นำเข้าขอบเขต{$levelLabel}: เพิ่มใหม่ {$report['created']} แก้ไข {$report['updated']}";
        if ($report['other_province']) {
            $msg .= " (ข้ามพื้นที่จังหวัดอื่น {$report['other_province']})";
        }

        return back()->with('success', $msg)->with('import_errors', array_slice($report['errors'], 0, 20));
    }

    /* ------------------------------------------------------------------ */

    protected function validateDistrict(Request $request, ?District $district = null): array
    {
        return $request->validate([
            'name_th' => ['required', 'string', 'max:100'],
            'name_en' => ['nullable', 'string', 'max:100'],
            'code' => ['nullable', 'digits:4', Rule::unique('districts', 'code')->ignore($district?->id)],
            'sort' => ['nullable', 'integer', 'min:0', 'max:999'],
            'center_lat' => ['nullable', 'numeric', 'between:5,21'],
            'center_lng' => ['nullable', 'numeric', 'between:97,106'],
        ], [], ['name_th' => 'ชื่ออำเภอ', 'code' => 'รหัสอำเภอ']);
    }

    protected function validateSubdistrict(Request $request, ?Subdistrict $sub = null): array
    {
        return $request->validate([
            'district_id' => [$sub ? 'sometimes' : 'required', 'exists:districts,id'],
            'name_th' => ['required', 'string', 'max:100'],
            'name_en' => ['nullable', 'string', 'max:100'],
            'code' => ['nullable', 'digits:6', Rule::unique('subdistricts', 'code')->ignore($sub?->id)],
            'postcode' => ['nullable', 'digits:5'],
            'center_lat' => ['nullable', 'numeric', 'between:5,21'],
            'center_lng' => ['nullable', 'numeric', 'between:97,106'],
        ], [], ['name_th' => 'ชื่อตำบล', 'code' => 'รหัสตำบล', 'postcode' => 'รหัสไปรษณีย์']);
    }
}
