<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TeamSos;
use App\Support\Live;
use Illuminate\Http\Request;

class SosController extends Controller
{
    public function ack(Request $request, TeamSos $sos)
    {
        $this->authorizeProvince($sos->province_id);
        if ($sos->status === 'open') {
            $sos->update(['status' => 'ack', 'acked_by' => $request->user()->id, 'acked_at' => now()]);
            Live::sos($sos);
        }

        return back()->with('success', 'รับทราบ SOS ของทีม '.$sos->team->name.' แล้ว');
    }

    public function resolve(Request $request, TeamSos $sos)
    {
        $this->authorizeProvince($sos->province_id);
        $note = $request->validate(['note' => ['nullable', 'string', 'max:500']])['note'] ?? null;
        $sos->update([
            'status' => 'resolved',
            'acked_by' => $sos->acked_by ?? $request->user()->id,
            'acked_at' => $sos->acked_at ?? now(),
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
            'resolve_note' => $note,
        ]);
        Live::sos($sos);

        return back()->with('success', 'ปิด SOS ของทีม '.$sos->team->name.' แล้ว');
    }
}
