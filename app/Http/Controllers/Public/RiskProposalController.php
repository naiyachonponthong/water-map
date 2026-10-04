<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Province;
use App\Models\RiskPoint;
use App\Models\User;
use App\Support\RiskOptions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** ประชาชนเสนอจุดเสี่ยง รอผู้ตรวจรายงานอนุมัติก่อนแสดง */
class RiskProposalController extends Controller
{
    public function form(Province $province)
    {
        abort_unless($province->is_active, 404);

        return view('public.risks.propose', [
            'province' => $province,
            'types' => collect(RiskOptions::TYPES)->except('facility'),
        ]);
    }

    public function store(Request $request, Province $province)
    {
        abort_unless($province->is_active, 404);
        if (filled($request->input('website'))) {
            return redirect()->route('public.province', $province);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(array_keys(RiskOptions::TYPES))],
            'description' => ['nullable', 'string', 'max:1000'],
            'lat' => ['required', 'numeric', 'between:5,21'],
            'lng' => ['required', 'numeric', 'between:97,106'],
            'proposer_name' => ['nullable', 'string', 'max:120'],
            'proposer_phone' => ['nullable', 'string', 'max:20'],
        ], ['lat.required' => 'ปักหมุดตำแหน่งบนแผนที่'], ['name' => 'ชื่อจุด', 'type' => 'ประเภท']);

        RiskPoint::create(array_replace($data, [
            'province_id' => $province->id,
            'proposer_phone' => ! empty($data['proposer_phone']) ? User::normalizePhone($data['proposer_phone']) : null,
            'severity' => 'medium',
            'source' => 'citizen',
            'review' => 'pending',
            'is_public' => true,
        ]));

        return redirect()->route('public.province', $province)->with('success', 'ขอบคุณที่แจ้ง เจ้าหน้าที่จะตรวจสอบก่อนแสดงบนแผนที่');
    }
}
