<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\HelpRequest;
use App\Support\HelpRequestService;
use App\Support\Settings;
use Illuminate\Http\Request;

/**
 * หน้าติดตามคำขอของผู้แจ้ง เปิดด้วยลิงก์เซ็นชื่อ หรือกรอกเลขคำขอ + 4 ตัวท้ายเบอร์
 */
class TrackController extends Controller
{
    public function lookup()
    {
        return view('public.track.lookup');
    }

    public function find(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'last4' => ['required', 'digits:4'],
        ], [], ['code' => 'เลขคำขอ', 'last4' => '4 ตัวท้ายเบอร์โทร']);

        $req = HelpRequest::where('code', strtoupper(trim($data['code'])))->where('phone_last4', $data['last4'])->first();
        if (! $req) {
            return back()->withInput()->withErrors(['code' => 'ไม่พบคำขอ ตรวจเลขคำขอและเบอร์โทรอีกครั้ง']);
        }

        return redirect()->to($req->trackUrl());
    }

    public function show(string $code)
    {
        $req = $this->find404($code);

        return view('public.track.show', [
            'req' => $req,
            'events' => $req->events()->where('public', true)->get(),
            'hotline' => Settings::get('hotline', $req->province_id),
            'contacts' => $req->province->publicContacts(),
            'levels' => config('floodthai.water_levels'),
        ]);
    }

    /** ผู้แจ้งกด "ปลอดภัยแล้ว / ไม่ต้องการความช่วยเหลือแล้ว" */
    public function safe(Request $request, string $code, HelpRequestService $service)
    {
        $req = $this->find404($code);
        if ($req->isOpen()) {
            $outcome = $request->input('reason') === 'no_need' ? 'no_need' : 'self_safe';
            $service->changeStatus($req, 'closed', null, 'ผู้แจ้งแจ้งผ่านหน้าติดตาม', ['outcome' => $outcome]);
        }

        return redirect()->to($req->trackUrl())->with('success', 'ขอบคุณที่แจ้ง ศูนย์ปิดคำขอนี้แล้ว ทีมจะไปช่วยคนอื่นต่อ');
    }

    /** ผู้แจ้งกด "สถานการณ์แย่ลง" */
    public function worse(Request $request, string $code, HelpRequestService $service)
    {
        $req = $this->find404($code);
        $data = $request->validate([
            'water_level' => ['required', 'integer', 'between:1,6'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [], ['water_level' => 'ระดับน้ำ']);

        if (! $req->isOpen()) {
            return redirect()->to($req->trackUrl())->with('error', 'คำขอนี้ปิดแล้ว ถ้ายังต้องการความช่วยเหลือ ส่งคำขอใหม่หรือโทรสายด่วน');
        }

        $before = $req->water_level;
        $req->water_level = max((int) $req->water_level, (int) $data['water_level']);
        $req->requester_updated_at = now();
        $service->applyPriority($req);
        $req->save();
        $service->event($req, 'update', [
            'actor' => 'requester',
            'public' => true,
            'note' => trim('สถานการณ์แย่ลง ระดับน้ำ '.$req->waterLabel().'. '.($data['note'] ?? '')),
            'data' => ['water_from' => $before, 'water_to' => $req->water_level],
        ]);

        return redirect()->to($req->trackUrl())->with('success', 'ส่งข้อมูลให้ศูนย์แล้ว ระบบเลื่อนคำขอของคุณขึ้นตามความเร่งด่วน');
    }

    /** ผู้แจ้งเพิ่มข้อมูล */
    public function note(Request $request, string $code, HelpRequestService $service)
    {
        $req = $this->find404($code);
        $data = $request->validate(['note' => ['required', 'string', 'max:500']], [], ['note' => 'ข้อความ']);

        if ($req->isOpen()) {
            $req->update(['requester_updated_at' => now()]);
            $service->event($req, 'update', ['actor' => 'requester', 'public' => true, 'note' => $data['note']]);
        }

        return redirect()->to($req->trackUrl())->with('success', 'ส่งข้อมูลเพิ่มเติมให้ศูนย์แล้ว');
    }

    protected function find404(string $code): HelpRequest
    {
        return HelpRequest::with('province', 'district', 'subdistrict', 'duplicateOf')->where('code', $code)->firstOrFail();
    }
}
