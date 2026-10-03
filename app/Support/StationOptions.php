<?php

namespace App\Support;

class StationOptions
{
    /** สถานะสถานี => [ป้าย, chip, สี, ระดับน้ำเทียบ 1-6 ที่ส่งให้ระบบเตือนจุดเสี่ยง] */
    public const STATUS = [
        'unknown' => ['ยังไม่มีข้อมูล', '', '#94a3b8', 0],
        'offline' => ['ขาดการติดต่อ', '', '#64748b', 0],
        'normal' => ['ปกติ', 'chip-success', '#16a34a', 0],
        'watch' => ['เฝ้าระวัง', 'chip-warning', '#eab308', 1],
        'warning' => ['เตือนภัย', 'chip-warning', '#f97316', 2],
        'critical' => ['วิกฤต', 'chip-danger', '#dc2626', 3],
    ];

    /** ลำดับความรุนแรง */
    public const RANK = ['unknown' => 0, 'offline' => 0, 'normal' => 1, 'watch' => 2, 'warning' => 3, 'critical' => 4];

    /** ไม่มีค่าใหม่เกินกี่ชั่วโมง = ขาดการติดต่อ */
    public const OFFLINE_HOURS = 6;

    public const FETCH_MODES = [
        'manual' => 'บันทึกเอง / นำเข้าไฟล์',
        'json' => 'ดึงจาก JSON API อัตโนมัติ',
    ];

    /** เกณฑ์ฝนรายวัน (มม.) ตามกรมอุตุนิยมวิทยา => [ป้าย, สี] */
    public const RAIN = [
        [0.1, 'ฝนเล็กน้อย', '#93c5fd'],
        [10.1, 'ฝนปานกลาง', '#3b82f6'],
        [35.1, 'ฝนหนัก', '#f97316'],
        [90.1, 'ฝนหนักมาก', '#dc2626'],
    ];

    public const CAMERA_TYPES = [
        'image' => ['ภาพนิ่งรีเฟรช (JPG/PNG)', 'image'],
        'hls' => ['วิดีโอสด HLS (.m3u8)', 'camera-video'],
        'iframe' => ['ฝังหน้าเว็บ (iframe)', 'window'],
        'link' => ['ลิงก์ไปหน้าเว็บ', 'box-arrow-up-right'],
    ];

    public const ALERT_LEVELS = [
        'watch' => ['เฝ้าระวัง', 'chip-warning', '#eab308', 'eye'],
        'warning' => ['เตือนภัย', 'chip-warning', '#f97316', 'exclamation-triangle-fill'],
        'critical' => ['วิกฤต', 'chip-danger', '#dc2626', 'exclamation-octagon-fill'],
    ];

    public const ALERT_KINDS = [
        'station' => ['สถานีวัดน้ำ', 'water'],
        'rain' => ['พยากรณ์ฝน', 'cloud-rain-heavy'],
        'risk' => ['จุดเสี่ยง', 'exclamation-triangle'],
        'manual' => ['ศูนย์ประกาศ', 'megaphone'],
    ];

    public static function rain(?float $mm): ?array
    {
        $hit = null;
        foreach (self::RAIN as [$min, $label, $color]) {
            if ($mm !== null && $mm >= $min) {
                $hit = ['label' => $label, 'color' => $color];
            }
        }

        return $hit;
    }
}
