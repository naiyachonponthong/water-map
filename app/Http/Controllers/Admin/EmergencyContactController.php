<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmergencyContact;
use Illuminate\Http\Request;

class EmergencyContactController extends Controller
{
    public function store(Request $request)
    {
        $province = $this->province();
        $data = $this->validated($request);
        $data['sort'] = (int) $province->emergencyContacts()->max('sort') + 1;
        $province->emergencyContacts()->create($data);

        return $this->ok('เพิ่มเบอร์ฉุกเฉินแล้ว', 'admin.settings.index', ['tab' => 'contacts']);
    }

    public function update(Request $request, EmergencyContact $contact)
    {
        $this->authorizeProvince($contact->province_id);
        $contact->update($this->validated($request));

        return $this->ok('บันทึกเบอร์ฉุกเฉินแล้ว', 'admin.settings.index', ['tab' => 'contacts']);
    }

    public function destroy(EmergencyContact $contact)
    {
        $this->authorizeProvince($contact->province_id);
        $contact->delete();

        return $this->ok('ลบเบอร์ฉุกเฉินแล้ว', 'admin.settings.index', ['tab' => 'contacts']);
    }

    /** รับลำดับใหม่จากการลากเรียง: ids[] */
    public function sort(Request $request)
    {
        $province = $this->province();
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];
        foreach ($ids as $i => $id) {
            EmergencyContact::where('province_id', $province->id)->whereKey($id)->update(['sort' => $i + 1]);
        }

        return response()->json(['ok' => true]);
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[\d\s-]+$/'],
            'note' => ['nullable', 'string', 'max:200'],
        ], [], ['label' => 'ชื่อหน่วยงาน', 'phone' => 'เบอร์โทร']);
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
