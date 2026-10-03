<?php

namespace App\Support;

class RecoveryOptions
{
    /** ความเสียหายของบ้าน => [ป้าย, chip, สี] */
    public const HOUSE = [
        'none' => ['บ้านไม่เสียหาย', '', '#94a3b8'],
        'minor' => ['เสียหายบางส่วน', 'chip-warning', '#eab308'],
        'major' => ['เสียหายมาก', 'chip-warning', '#f97316'],
        'destroyed' => ['เสียหายทั้งหลัง', 'chip-danger', '#dc2626'],
    ];

    public const TENURE = ['own' => 'เจ้าของบ้าน', 'rent' => 'เช่า', 'other' => 'อาศัยผู้อื่น'];

    public const LOSSES = [
        'furniture' => ['เครื่องเรือน/ที่นอน', 'lamp'],
        'appliances' => ['เครื่องใช้ไฟฟ้า', 'plug'],
        'vehicle' => ['รถยนต์/จักรยานยนต์', 'car-front'],
        'crops' => ['พืชผลการเกษตร', 'flower2'],
        'livestock' => ['สัตว์เลี้ยง/ปศุสัตว์', 'egg'],
        'tools' => ['เครื่องมือประกอบอาชีพ', 'tools'],
        'business' => ['ร้านค้า/กิจการ', 'shop'],
    ];

    /** สถานะ => [ป้าย, chip, ข้อความที่ผู้ยื่นเห็น] */
    public const STATUS = [
        'submitted' => ['รอสำรวจ', 'chip-warning', 'ได้รับคำร้องแล้ว รอเจ้าหน้าที่นัดสำรวจ'],
        'surveying' => ['นัดสำรวจแล้ว', 'chip-primary', 'เจ้าหน้าที่จะเข้าสำรวจความเสียหาย'],
        'surveyed' => ['สำรวจแล้ว รอพิจารณา', 'chip-primary', 'สำรวจแล้ว อยู่ระหว่างพิจารณา'],
        'approved' => ['อนุมัติแล้ว', 'chip-success', 'อนุมัติความช่วยเหลือแล้ว รอการจ่าย'],
        'paid' => ['จ่ายแล้ว', 'chip-success', 'จ่ายความช่วยเหลือแล้ว'],
        'rejected' => ['ไม่ผ่านเกณฑ์', 'chip-danger', 'คำร้องไม่ผ่านเกณฑ์'],
    ];

    public const OPEN = ['submitted', 'surveying', 'surveyed', 'approved'];

    /** หลักฐานอัตโนมัติ => [ป้าย, คะแนน] */
    public const EVIDENCE = [
        'help_request' => ['เคยแจ้งขอความช่วยเหลือในช่วงน้ำท่วม', 40],
        'evacuee' => ['เคยลงทะเบียนในศูนย์พักพิง', 40],
        'water_report' => ['มีรายงานน้ำท่วมในรัศมี 500 ม.', 25],
        'area_level' => ['ตำบลนี้ศูนย์ประกาศระดับน้ำ', 20],
        'vulnerable' => ['อยู่ในทะเบียนครัวเรือนเปราะบาง', 10],
    ];
}
