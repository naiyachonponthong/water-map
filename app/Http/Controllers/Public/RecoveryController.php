<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\DamageClaim;
use App\Models\Province;
use App\Models\User;
use App\Support\HelpRequestService;
use App\Support\RecoveryService;
use App\Support\Settings;
use Illuminate\Http\Request;

/**
 * ประชาชนยื่นคำร้องขอรับความช่วยเหลือหลังน้ำลด และติดตามสถานะ
 */
class RecoveryController extends Controller
{
    public function form(Province $province)
    {
        abort_unless($province->is_active, 404);

        return view('public.recovery.form', [
            'province' => $province,
            'open' => (bool) Settings::get('recovery_open', $province->id),
            'hotline' => Settings::get('hotline', $province->id),
            'levels' => config('floodthai.water_levels'),
        ]);
    }

    public function store(Request $request, Province $province, RecoveryService $service)
    {
        abort_unless($province->is_active, 404);
        if (! Settings::get('recovery_open', $province->id)) {
            return redirect()->route('public.recovery', $province)->with('error', 'ยังไม่เปิดรับคำร้อง');
        }
        if (filled($request->input('website'))) {
            return redirect()->route('public.province', $province);
        }
        $data = $request->validate(RecoveryService::rules(), ['phone.regex' => 'เบอร์โทรไม่ถูกต้อง'], RecoveryService::attributes());

        [$claim, $existing] = $service->submit($province, $data, [
            'source' => 'web',
            'photos' => $request->file('photos', []),
            'device_hash' => $request->cookie('fdev') ? hash('sha256', $request->cookie('fdev')) : null,
        ]);

        return redirect()->to($claim->trackUrl())->with('success', $existing
            ? 'เบอร์นี้มีคำร้องที่ยังดำเนินการอยู่แล้ว แสดงคำร้องเดิม'
            : 'ยื่นคำร้องแล้ว เลขที่ '.$claim->code.' บันทึกลิงก์หน้านี้ไว้ติดตามสถานะ');
    }

    public function track(string $code)
    {
        $claim = DamageClaim::where('code', $code)->firstOrFail();

        return view('public.recovery.track', [
            'claim' => $claim,
            'events' => $claim->events()->where('public', true)->get(),
            'province' => $claim->province,
            'hotline' => Settings::get('hotline', $claim->province_id),
        ]);
    }

    /** ลืมลิงก์: ใช้เลขคำร้อง + เบอร์โทรที่ยื่นไว้ */
    public function lookup(Request $request, Province $province)
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:20'], 'phone' => ['required', 'string', 'max:20']], [], ['code' => 'เลขคำร้อง', 'phone' => 'เบอร์โทร']);
        $claim = DamageClaim::inProvince($province->id)->where('code', strtoupper(trim($data['code'])))
            ->where('phone_hash', HelpRequestService::phoneHash(User::normalizePhone($data['phone'])))->first();

        return $claim ? redirect()->to($claim->trackUrl()) : back()->withInput()->with('error', 'ไม่พบคำร้องที่ตรงกับเลขและเบอร์นี้');
    }
}
