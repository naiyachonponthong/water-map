<?php

namespace App\Http\Controllers;

use App\Support\LiveSnapshot;
use Illuminate\Http\Request;

/**
 * ข้อมูลสดของศูนย์สั่งการ: หน้าเว็บโหลดใหม่เมื่อได้ socket event หรือทุก 20 วินาทีถ้าไม่มี realtime
 */
class LiveController extends Controller
{
    public function snapshot(Request $request)
    {
        return response()->json(LiveSnapshot::cached($this->province(), $request->integer('since'), $request->boolean('tv')))
            ->header('Cache-Control', 'no-store');
    }

    /** โหมดทีวีสำหรับห้องสั่งการ */
    public function tv()
    {
        $province = $this->province();

        return view('live.tv', [
            'province' => $province,
            'snapshot' => LiveSnapshot::cached($province, 0, true),
        ]);
    }
}
