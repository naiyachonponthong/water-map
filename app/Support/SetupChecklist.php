<?php

namespace App\Support;

use App\Models\Province;
use App\Models\User;

class SetupChecklist
{
    public function steps(Province $province, HostingStatus $hosting): array
    {
        $pid = $province->id;
        $roles = User::where('province_id', $pid)->where('status', 'active')->with('roles:id,name')->get()
            ->flatMap(fn ($user) => $user->roles->pluck('name'))->countBy();
        $districts = $province->districts()->count();
        $districtBoundaries = $province->districts()->whereNotNull('boundary')->count();
        $subdistricts = $province->subdistricts()->count();
        $subdistrictBoundaries = $province->subdistricts()->whereNotNull('boundary')->count();
        $review = (array) Settings::get('setup_verification', $pid, []);
        $check = fn ($label, $ok, $help) => compact('label', 'ok', 'help');

        return [
            1 => ['title' => 'เลือกจังหวัด', 'icon' => 'geo-alt', 'checks' => [
                $check('จังหวัดที่กำลังตั้งค่า', session('admin_province_id') === $pid, 'ทุกขั้นตอนถัดไปใช้ข้อมูลของ'.$province->fullName()),
            ]],
            2 => ['title' => 'หน่วยงานและสายด่วน', 'icon' => 'building', 'checks' => [
                $check('หน่วยงานผู้รับผิดชอบ', filled(Settings::get('privacy_controller', $pid, '')), 'ระบุหน่วยงานที่รับผิดชอบระบบและข้อมูลของประชาชน'),
                $check('ช่องทางติดต่อเรื่องข้อมูล', filled(Settings::get('privacy_contact', $pid, '')), 'ระบุเบอร์ อีเมล หรือช่องทางติดต่อหน่วยงาน'),
                $check('สายด่วนของจังหวัด', filled(Settings::get('hotline', $pid, '')) && $province->emergencyContacts()->where('is_active', true)->exists(), 'ตั้งสายด่วนหลักและเพิ่มเบอร์ฉุกเฉินในพื้นที่'),
                $check('พิกัดกลางจังหวัด', $province->hasCenter(), 'ใช้สำหรับเปิดแผนที่และพยากรณ์ของจังหวัด'),
            ]],
            3 => ['title' => 'เจ้าหน้าที่และพื้นที่', 'icon' => 'people', 'checks' => [
                $check('ผู้อำนวยการศูนย์', ($roles['province-admin'] ?? 0) > 0, 'สร้างหรืออนุมัติบัญชีที่ใช้งานได้ และผูกกับจังหวัดนี้'),
                $check('เจ้าหน้าที่สั่งการ', ($roles['dispatcher'] ?? 0) > 0, 'มีเจ้าหน้าที่รับแจ้งและมอบหมายทีมในจังหวัดนี้'),
                $check('ขอบเขตอำเภอ', $districts > 0 && $districts === $districtBoundaries, "มีขอบเขต {$districtBoundaries}/{$districts} อำเภอ · นำเข้าขอบเขตปฏิบัติงานของจริง"),
                $check('ขอบเขตตำบล', $subdistricts > 0 && $subdistricts === $subdistrictBoundaries, "มีขอบเขต {$subdistrictBoundaries}/{$subdistricts} ตำบล · ขอบเขตอ้างอิงในแผนที่ไม่ใช่การนำเข้าพื้นที่ปฏิบัติงาน"),
            ]],
            4 => ['title' => 'ตรวจโฮสต์และ Cron', 'icon' => 'server', 'checks' => $hosting->checks()],
            5 => ['title' => 'ทดลองก่อนเปิดศูนย์', 'icon' => 'clipboard-check', 'checks' => [
                $check('ทดลองรับเหตุครบกระบวนการ', ($review['workflow'] ?? false) === true, 'แจ้งเหตุ → คัดกรอง → มอบหมายทีม → รับงาน → ปิดงาน ในระบบทดสอบ'),
                $check('ทดลองสำรองและกู้คืน', ($review['backup'] ?? false) === true, 'สำรองฐานข้อมูล รูป และ .env พร้อมทดลองกู้คืนในระบบแยก'),
                $check('ตรวจข้อมูลและบริการภายนอก', ($review['sources'] ?? false) === true, 'ตรวจสถานี พยากรณ์ สายด่วน ศูนย์พักพิง และช่องทางส่งประกาศที่หน่วยงานใช้งาน'),
            ]],
        ];
    }
}
