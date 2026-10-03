<?php

namespace App\Support;

class RiskOptions
{
    /** ประเภทจุดเสี่ยง => [ป้าย, ไอคอน, สี] */
    public const TYPES = [
        'flood_prone' => ['พื้นที่น้ำท่วมซ้ำซาก', 'water', '#2563eb'],
        'road' => ['ถนน/สะพานอันตราย', 'sign-stop', '#dc2626'],
        'hazard' => ['ไฟฟ้า/สารเคมี', 'lightning-charge', '#7c3aed'],
        'landslide' => ['ดินสไลด์/น้ำป่า', 'triangle', '#a16207'],
        'facility' => ['สถานที่สำคัญ/จุดอพยพ', 'hospital', '#0d9488'],
        'other' => ['อื่นๆ', 'exclamation-triangle', '#64748b'],
    ];

    public const SEVERITY = [
        'low' => ['ต่ำ', ''],
        'medium' => ['กลาง', 'chip-warning'],
        'high' => ['สูง', 'chip-danger'],
    ];

    public const STATUS = [
        'normal' => ['ปกติ', 'chip-success'],
        'threatened' => ['ถูกคุกคาม', 'chip-danger'],
        'inactive' => ['ปิดใช้งาน', ''],
    ];

    /** ภาวะของครัวเรือนเปราะบาง => [ป้าย, ไอคอน, กลุ่มเปราะบางในเคส] */
    public const CONDITIONS = [
        'bedridden' => ['ผู้ป่วยติดเตียง', 'heart-pulse', 'bedridden'],
        'oxygen' => ['ใช้ออกซิเจน/เครื่องช่วยหายใจ', 'lungs', 'sick'],
        'dialysis' => ['ฟอกไต', 'droplet', 'sick'],
        'wheelchair' => ['ผู้พิการ/ใช้รถเข็น', 'person-wheelchair', 'disabled'],
        'elderly_alone' => ['ผู้สูงอายุอยู่ลำพัง', 'person-cane', 'elderly'],
        'infant' => ['เด็กเล็ก', 'balloon-heart', 'infant'],
        'pregnant' => ['หญิงตั้งครรภ์ใกล้คลอด', 'gender-female', 'pregnant'],
        'other' => ['อื่นๆ', 'three-dots', null],
    ];

    public const CHECK = [
        'safe' => ['ปลอดภัย อยู่ได้', 'chip-success'],
        'needs_help' => ['ต้องการความช่วยเหลือ', 'chip-danger'],
        'evacuated' => ['อพยพแล้ว', 'chip-primary'],
        'not_home' => ['ไม่อยู่บ้าน / ติดต่อไม่ได้', 'chip-warning'],
    ];
}
