<?php

namespace App\Support;

/**
 * ตัวเลือกและป้ายภาษาไทยของเคสขอความช่วยเหลือ (ใช้ร่วมทั้งหน้าประชาชนและหลังบ้าน)
 */
class CaseOptions
{
    /** สถานะ => [ป้าย, ป้ายสำหรับผู้แจ้ง, สี chip, ไอคอน] */
    public const STATUSES = [
        'new' => ['เคสใหม่', 'ได้รับคำขอแล้ว', 'chip-danger', 'bell'],
        'screening' => ['กำลังคัดกรอง', 'เจ้าหน้าที่กำลังตรวจสอบ', 'chip-warning', 'search'],
        'queued' => ['รอมอบหมายทีม', 'รอจัดทีมเข้าช่วย', 'chip-warning', 'hourglass-split'],
        'offered' => ['เสนองานให้ทีม', 'กำลังหาทีมเข้าช่วย', 'chip-primary', 'send'],
        'accepted' => ['ทีมรับงานแล้ว', 'มีทีมรับเรื่องแล้ว', 'chip-primary', 'person-check'],
        'en_route' => ['กำลังเดินทาง', 'ทีมกำลังเดินทางไป', 'chip-primary', 'truck'],
        'on_site' => ['ถึงที่เกิดเหตุ', 'ทีมถึงที่เกิดเหตุแล้ว', 'chip-primary', 'geo-alt'],
        'rescued' => ['ช่วยเหลือแล้ว', 'ช่วยเหลือเรียบร้อย', 'chip-success', 'check-circle'],
        'closed' => ['ปิดเคส', 'ปิดเรื่องแล้ว', '', 'archive'],
        'merged' => ['รวมกับเคสอื่น', 'รวมกับคำขอเดิมของคุณ', '', 'intersect'],
        'cancelled' => ['ยกเลิก', 'ยกเลิกคำขอแล้ว', '', 'x-circle'],
    ];

    /** สถานะที่ยังต้องดำเนินการ */
    public const OPEN = ['new', 'screening', 'queued', 'offered', 'accepted', 'en_route', 'on_site'];

    /** สถานะที่ยังรอทีม (นับเวลารอเพื่อเพิ่มคะแนน) */
    public const WAITING = ['new', 'screening', 'queued', 'offered'];

    public const FINAL = ['rescued', 'closed', 'merged', 'cancelled'];

    /** แท็บในหน้ารายการเคส */
    public const TABS = [
        'triage' => ['รอคัดกรอง', ['new', 'screening']],
        'queued' => ['รอทีม', ['queued', 'offered']],
        'active' => ['กำลังช่วย', ['accepted', 'en_route', 'on_site']],
        'done' => ['ช่วยแล้ว', ['rescued']],
        'closed' => ['ปิด/รวม', ['closed', 'merged', 'cancelled']],
        'all' => ['ทั้งหมด', []],
    ];

    public const PRIORITIES = [
        'critical' => ['วิกฤต', 'chip-danger', '#dc2626'],
        'high' => ['สูง', 'chip-warning', '#ea580c'],
        'medium' => ['กลาง', 'chip-primary', '#ca8a04'],
        'low' => ['ต่ำ', '', '#16a34a'],
    ];

    /** กลุ่มเปราะบาง key => [ป้าย, ไอคอน, หมวดน้ำหนักคะแนน] */
    public const VULNERABLE = [
        'bedridden' => ['ผู้ป่วยติดเตียง', 'heart-pulse', 'bedridden'],
        'sick' => ['ผู้ป่วย / ต้องใช้ยาประจำ', 'capsule', 'bedridden'],
        'disabled' => ['ผู้พิการ', 'person-wheelchair', 'bedridden'],
        'infant' => ['เด็กเล็ก (ต่ำกว่า 5 ปี)', 'balloon-heart', 'child_elderly'],
        'elderly' => ['ผู้สูงอายุ', 'person-cane', 'child_elderly'],
        'pregnant' => ['หญิงตั้งครรภ์', 'gender-female', 'child_elderly'],
        'pets' => ['สัตว์เลี้ยง', 'heart', null],
    ];

    public const NEEDS = [
        'evacuate' => ['อพยพออกจากพื้นที่', 'box-arrow-right'],
        'food' => ['อาหาร / น้ำดื่ม', 'cup-straw'],
        'medicine' => ['ยา / พบแพทย์', 'bandaid'],
        'power' => ['ไฟฟ้า / ชาร์จแบต', 'battery-charging'],
        'other' => ['อื่นๆ', 'three-dots'],
    ];

    public const OUTCOMES = [
        'shelter' => 'ส่งศูนย์พักพิง',
        'hospital' => 'ส่งโรงพยาบาล',
        'relatives' => 'ไปพักบ้านญาติ',
        'stay' => 'อยู่ที่เดิม ส่งของ/ยาแล้ว',
        'self_safe' => 'ผู้แจ้งแจ้งว่าปลอดภัยแล้ว',
        'no_need' => 'ไม่ต้องการความช่วยเหลือแล้ว',
        'unreachable' => 'ติดต่อไม่ได้ / ไม่พบผู้แจ้ง',
        'fake' => 'ข้อมูลไม่จริง',
        'duplicate' => 'เคสซ้ำ',
    ];

    public const SOURCES = [
        'web' => ['เว็บ', 'globe2'],
        'phone' => ['โทรศัพท์', 'telephone'],
        'line' => ['LINE', 'line'],
        'staff' => ['เจ้าหน้าที่พบเอง', 'person-badge'],
        'proactive' => ['ตรวจเยี่ยมเชิงรุก', 'shield-plus'],
    ];

    public static function status(string $key, int $i = 0): string
    {
        return self::STATUSES[$key][$i] ?? $key;
    }

    public static function priority(string $key, int $i = 0): string
    {
        return self::PRIORITIES[$key][$i] ?? $key;
    }

    public static function waterLevel(int $level, string $field = 'short'): string
    {
        return config("floodthai.water_levels.$level.$field", '-');
    }
}
