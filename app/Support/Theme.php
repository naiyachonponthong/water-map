<?php

namespace App\Support;

/**
 * สร้างชุดสีทั้งชุดจากสีหลักสีเดียว (แบบ Super App UI)
 * ผลลัพธ์ใส่ใน <style id="theme-vars"> ต่อจาก app.css
 */
class Theme
{
    public const PRESETS = [
        '#1565C0' => 'น้ำเงินน้ำท่วม',
        '#0E7490' => 'ฟ้าน้ำทะเล',
        '#0F766E' => 'เขียวหัวเป็ด',
        '#2563EB' => 'น้ำเงินสด',
        '#4F46E5' => 'คราม',
        '#7C3AED' => 'ม่วง',
        '#DB2777' => 'ชมพู',
        '#DC2626' => 'แดง',
        '#F26522' => 'ส้ม',
        '#CA8A04' => 'เหลืองทอง',
        '#16A34A' => 'เขียว',
        '#334155' => 'เทาเข้ม',
    ];

    public static function css(?string $hex = null): string
    {
        $hex = static::normalize($hex ?: config('floodthai.defaults.theme_color'));
        [$r, $g, $b] = static::rgb($hex);

        $vars = [
            '--sb-primary' => $hex,
            '--sb-primary-rgb' => "$r, $g, $b",
            '--sb-primary-600' => static::mix($hex, '#000000', 0.12),
            '--sb-primary-700' => static::mix($hex, '#000000', 0.28),
            '--sb-primary-50' => static::mix($hex, '#ffffff', 0.93),
            '--sb-primary-100' => static::mix($hex, '#ffffff', 0.86),
            '--sb-primary-200' => static::mix($hex, '#ffffff', 0.72),
            '--sb-grad-from' => static::mix($hex, '#ffffff', 0.28),
            '--sb-grad-to' => static::mix($hex, '#000000', 0.06),
            '--bs-primary' => $hex,
            '--bs-primary-rgb' => "$r, $g, $b",
            '--bs-link-color' => $hex,
            '--bs-link-color-rgb' => "$r, $g, $b",
        ];

        $body = collect($vars)->map(fn ($v, $k) => "$k:$v")->implode(';');

        return ":root{{$body}}";
    }

    public static function normalize(string $hex): string
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return preg_match('/^[0-9a-fA-F]{6}$/', $hex) ? '#'.strtoupper($hex) : '#1565C0';
    }

    public static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    /** ผสมสี $a กับ $b ตามสัดส่วน $t (0 = $a ล้วน, 1 = $b ล้วน) */
    public static function mix(string $a, string $b, float $t): string
    {
        $ca = static::rgb($a);
        $cb = static::rgb($b);
        $out = array_map(fn ($x, $y) => (int) round($x + ($y - $x) * $t), $ca, $cb);

        return sprintf('#%02x%02x%02x', ...$out);
    }
}
