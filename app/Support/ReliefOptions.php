<?php

namespace App\Support;

class ReliefOptions
{
    public const SHELTER_TYPES = [
        'temple' => ['วัด', 'bank'],
        'school' => ['โรงเรียน', 'building'],
        'hall' => ['หอประชุม/อาคารเอนกประสงค์', 'buildings'],
        'gov' => ['หน่วยงานราชการ', 'bank2'],
        'other' => ['อื่นๆ', 'house-heart'],
    ];

    /** สถานะศูนย์ => [ป้าย, chip, สี] */
    public const SHELTER_STATUS = [
        'preparing' => ['เตรียมเปิด', 'chip-warning', '#eab308'],
        'open' => ['เปิดรับ', 'chip-success', '#16a34a'],
        'full' => ['เต็ม', 'chip-danger', '#dc2626'],
        'closed' => ['ปิด', '', '#94a3b8'],
    ];

    public const FACILITIES = [
        'toilet' => ['ห้องน้ำเพียงพอ', 'droplet'],
        'power' => ['ไฟฟ้า/ชาร์จมือถือ', 'lightning-charge'],
        'kitchen' => ['ครัว/อาหาร', 'cup-hot'],
        'medical' => ['จุดปฐมพยาบาล', 'bandaid'],
        'accessible' => ['รองรับรถเข็น', 'person-wheelchair'],
        'pets' => ['รับสัตว์เลี้ยง', 'heart'],
        'parking' => ['ที่จอดรถ', 'car-front'],
    ];

    public const AGE_GROUPS = [
        'infant' => 'เด็กเล็ก (0-5)',
        'child' => 'เด็ก (6-17)',
        'adult' => 'ผู้ใหญ่',
        'elderly' => 'ผู้สูงอายุ (60+)',
    ];

    public const GENDERS = ['male' => 'ชาย', 'female' => 'หญิง', 'other' => 'ไม่ระบุ'];

    public const EVACUEE_NEEDS = [
        'medical' => ['ต้องดูแลทางการแพทย์', 'heart-pulse'],
        'bedridden' => ['ติดเตียง', 'hospital'],
        'disabled' => ['ผู้พิการ', 'person-wheelchair'],
        'pregnant' => ['ตั้งครรภ์', 'gender-female'],
        'infant_care' => ['ต้องการนม/ผ้าอ้อม', 'balloon-heart'],
        'pets' => ['มีสัตว์เลี้ยง', 'heart'],
    ];

    public const OUT_REASONS = [
        'home' => 'กลับบ้าน',
        'relatives' => 'ไปบ้านญาติ',
        'hospital' => 'ส่งโรงพยาบาล',
        'transfer' => 'ย้ายศูนย์',
        'other' => 'อื่นๆ',
    ];

    public const SUPPLY_CATEGORIES = [
        'food' => ['อาหาร', 'basket'],
        'water' => ['น้ำดื่ม', 'cup-straw'],
        'medicine' => ['ยา/เวชภัณฑ์', 'capsule'],
        'hygiene' => ['ของใช้ส่วนตัว', 'droplet-half'],
        'baby' => ['ของใช้เด็ก', 'balloon'],
        'bedding' => ['เครื่องนอน', 'moon-stars'],
        'other' => ['อื่นๆ', 'box'],
    ];

    public const MOVEMENT_KINDS = [
        'in' => 'รับเข้า',
        'out' => 'จ่ายออก',
        'transfer_in' => 'รับโอน',
        'transfer_out' => 'โอนออก',
        'adjust' => 'ปรับยอด',
    ];

    public const ANNOUNCE_LEVELS = [
        'info' => ['ข่าวสาร', 'chip-primary', '#2563eb', 'info-circle-fill'],
        'warning' => ['เฝ้าระวัง', 'chip-warning', '#f97316', 'exclamation-triangle-fill'],
        'urgent' => ['ด่วน', 'chip-danger', '#dc2626', 'megaphone-fill'],
    ];
}
