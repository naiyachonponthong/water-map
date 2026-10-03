<?php

namespace App\Support;

class ReportOptions
{
    /** แนวโน้มน้ำ => [ป้าย, ไอคอน] */
    public const TRENDS = [
        'rising' => ['น้ำกำลังขึ้น', 'arrow-up-circle'],
        'steady' => ['ทรงตัว', 'dash-circle'],
        'falling' => ['น้ำกำลังลด', 'arrow-down-circle'],
    ];

    /** จุดที่รายงาน => [ป้าย, ไอคอน] */
    public const PLACES = [
        'road' => ['ถนน', 'sign-turn-right'],
        'house' => ['บ้าน/ชุมชน', 'house'],
        'field' => ['ทุ่งนา/สวน', 'flower1'],
        'canal' => ['ริมคลอง/แม่น้ำ', 'water'],
        'other' => ['อื่นๆ', 'geo-alt'],
    ];

    public const STATUSES = [
        'pending' => ['รอตรวจ', 'chip-warning'],
        'published' => ['แสดงอยู่', 'chip-success'],
        'hidden' => ['ซ่อน', ''],
        'rejected' => ['ปฏิเสธ', 'chip-danger'],
    ];

    public const SOURCES = [
        'web' => ['ประชาชน', 'globe2'],
        'staff' => ['เจ้าหน้าที่', 'person-badge'],
        'team' => ['ทีมกู้ภัย', 'truck'],
        'line' => ['LINE', 'chat-dots'],
    ];

    /** ปุ่มโหวตของคนในพื้นที่ */
    public const VOTES = [
        'confirm' => 'ยังท่วมอยู่จริง',
        'receded' => 'น้ำลดแล้ว',
        'wrong' => 'ข้อมูลไม่ถูกต้อง',
    ];

    /** โหวต "ไม่ถูกต้อง" ถึงจำนวนนี้ ซ่อนอัตโนมัติรอตรวจ */
    public const WRONG_HIDE = 3;

    /** โหวต "น้ำลดแล้ว" ถึงจำนวนนี้ หมดอายุใน 1 ชม. */
    public const RECEDE_EXPIRE = 2;

    /** ผู้แจ้งเดิมแจ้งซ้ำในรัศมี/เวลานี้ ถือว่าอัปเดตจุดเดิม */
    public const SAME_SPOT_M = 150;

    public const SAME_SPOT_MIN = 60;
}
