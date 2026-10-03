<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Camera;
use App\Models\WaterStation;
use App\Support\StationOptions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CameraController extends Controller
{
    public function index()
    {
        $province = $this->province();

        return view('admin.cameras.index', [
            'province' => $province,
            'cameras' => Camera::inProvince($province->id)->with('station:id,name')->orderBy('sort')->orderBy('name')->get(),
            'stations' => WaterStation::inProvince($province->id)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $camera = new Camera(['province_id' => $this->province()->id]);
        $this->fill($request, $camera);

        return $this->ok("เพิ่มกล้อง {$camera->name} แล้ว");
    }

    public function update(Request $request, Camera $camera)
    {
        $this->authorizeProvince($camera->province_id);
        $this->fill($request, $camera);

        return $this->ok("บันทึก {$camera->name} แล้ว");
    }

    public function destroy(Camera $camera)
    {
        $this->authorizeProvince($camera->province_id);
        $camera->delete();

        return $this->ok("ลบ {$camera->name} แล้ว");
    }

    protected function fill(Request $request, Camera $camera): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(array_keys(StationOptions::CAMERA_TYPES))],
            // แสดงในเบราว์เซอร์ของผู้ชมโดยตรง บังคับ https กันเนื้อหาผสมและลิงก์ javascript:
            'url' => ['required', 'url:https', 'max:500'],
            'lat' => ['required', 'numeric', 'between:5,21'],
            'lng' => ['required', 'numeric', 'between:97,106'],
            'refresh_sec' => ['required', 'integer', 'between:10,3600'],
            'owner' => ['nullable', 'string', 'max:120'],
            'water_station_id' => ['nullable', Rule::exists('water_stations', 'id')->where('province_id', $camera->province_id)],
            'sort' => ['nullable', 'integer', 'between:0,9999'],
        ], ['url.url' => 'URL ต้องขึ้นต้นด้วย https://'], ['name' => 'ชื่อกล้อง', 'url' => 'URL', 'lat' => 'ตำแหน่ง']);

        $camera->fill($data + ['sort' => 0]);
        $camera->sort = (int) ($data['sort'] ?? 0);
        $camera->is_public = $request->boolean('is_public');
        $camera->is_active = $request->boolean('is_active', true);
        $camera->save();
    }
}
