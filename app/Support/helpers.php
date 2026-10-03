<?php

use App\Support\Settings;
use App\Support\ThaiDate;

if (! function_exists('setting')) {
    /** อ่านค่าตั้งค่าของจังหวัดปัจจุบัน (หรือจังหวัดที่ระบุ) */
    function setting(string $key, mixed $default = null, ?int $provinceId = null): mixed
    {
        $provinceId ??= current_province()?->id;

        return Settings::get($key, $provinceId, $default);
    }
}

if (! function_exists('current_province')) {
    /** จังหวัดที่กำลังทำงานอยู่ (กำหนดโดย SetCurrentProvince middleware) */
    function current_province(): ?App\Models\Province
    {
        return app()->bound('currentProvince') ? app('currentProvince') : null;
    }
}

if (! function_exists('thai_date')) {
    function thai_date($date, string $format = 'short'): string
    {
        return match ($format) {
            'long' => ThaiDate::long($date),
            'datetime' => ThaiDate::dateTime($date),
            'compact' => ThaiDate::compact($date),
            'ago' => ThaiDate::ago($date),
            default => ThaiDate::short($date),
        };
    }
}

if (! function_exists('phone_format')) {
    /** 0834265959 -> 083-426-5959, 021234567 -> 02-123-4567 */
    function phone_format(?string $phone): string
    {
        $d = preg_replace('/\D+/', '', (string) $phone);

        return match (true) {
            strlen($d) === 10 => substr($d, 0, 3).'-'.substr($d, 3, 3).'-'.substr($d, 6),
            strlen($d) === 9 => substr($d, 0, 2).'-'.substr($d, 2, 3).'-'.substr($d, 5),
            default => (string) $phone,
        };
    }
}
