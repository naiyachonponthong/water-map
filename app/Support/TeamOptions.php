<?php

namespace App\Support;

class TeamOptions
{
    public const TYPES = [
        'foundation' => 'มูลนิธิ / กู้ภัย',
        'volunteer' => 'อาสาสมัคร',
        'local_gov' => 'อปท. / อปพร.',
        'military' => 'ทหาร / ตำรวจ',
        'medical' => 'การแพทย์',
        'other' => 'อื่นๆ',
    ];

    /** สถานะทีม => [ป้าย, chip, สีบนแผนที่] */
    public const STATUSES = [
        'available' => ['พร้อม', 'chip-success', '#16a34a'],
        'busy' => ['ติดภารกิจ', 'chip-primary', '#2563eb'],
        'resting' => ['พัก / เติมน้ำมัน', 'chip-warning', '#d97706'],
        'offline' => ['ไม่ออกปฏิบัติ', '', '#94a3b8'],
    ];

    /** ยานพาหนะ => [ป้าย, ไอคอน, ความเร็วเฉลี่ย กม./ชม. ในพื้นที่น้ำท่วม] */
    public const VEHICLES = [
        'boat' => ['เรือ', 'water', 10],
        'high_truck' => ['รถยกสูง', 'truck', 20],
        'ambulance' => ['รถพยาบาล', 'hospital', 30],
        'pickup' => ['รถกระบะ', 'truck-flatbed', 30],
        'drone' => ['โดรน', 'airplane', 40],
        'other' => ['อื่นๆ', 'gear', 20],
    ];

    public const VEHICLE_STATUSES = [
        'ready' => 'พร้อมใช้',
        'in_use' => 'กำลังใช้',
        'maintenance' => 'ซ่อม',
    ];

    /** สถานะงานของทีม => [ป้าย, chip] */
    public const ASSIGNMENT = [
        'offered' => ['รอทีมตอบรับ', 'chip-warning'],
        'accepted' => ['รับงานแล้ว', 'chip-primary'],
        'en_route' => ['กำลังเดินทาง', 'chip-primary'],
        'on_site' => ['ถึงที่เกิดเหตุ', 'chip-primary'],
        'done' => ['เสร็จ', 'chip-success'],
        'declined' => ['ปฏิเสธ', 'chip-danger'],
        'cancelled' => ['ยกเลิก', ''],
        'expired' => ['หมดเวลาตอบรับ', ''],
    ];

    public const ACTIVE = ['offered', 'accepted', 'en_route', 'on_site'];

    public const DECLINE_REASONS = [
        'full' => 'เรือ/รถเต็ม',
        'too_far' => 'อยู่ไกลเกินไป',
        'no_access' => 'เข้าพื้นที่ไม่ได้ น้ำลึก/กระแสแรง',
        'no_vehicle' => 'ไม่มียานพาหนะที่เหมาะ',
        'other' => 'อื่นๆ',
    ];
}
