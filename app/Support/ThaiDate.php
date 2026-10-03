<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * วันที่แบบไทย พ.ศ.
 */
class ThaiDate
{
    public const MONTHS = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];

    public const MONTHS_SHORT = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];

    protected static function parse(CarbonInterface|string|null $date): ?Carbon
    {
        if ($date === null || $date === '') {
            return null;
        }

        return Carbon::parse($date)->timezone(config('app.timezone'));
    }

    /** 2 ต.ค. 2569 */
    public static function short(CarbonInterface|string|null $date): string
    {
        $d = static::parse($date);

        return $d ? $d->day.' '.self::MONTHS_SHORT[$d->month].' '.($d->year + 543) : '-';
    }

    /** 2 ตุลาคม 2569 */
    public static function long(CarbonInterface|string|null $date): string
    {
        $d = static::parse($date);

        return $d ? $d->day.' '.self::MONTHS[$d->month].' '.($d->year + 543) : '-';
    }

    /** 2 ต.ค. 2569 18:40 น. */
    public static function dateTime(CarbonInterface|string|null $date): string
    {
        $d = static::parse($date);

        return $d ? static::short($d).' '.$d->format('H:i').' น.' : '-';
    }

    /** 2 ต.ค. 18:40 น. (ปีปัจจุบันไม่ต้องใส่ปี) */
    public static function compact(CarbonInterface|string|null $date): string
    {
        $d = static::parse($date);
        if (! $d) {
            return '-';
        }
        $year = $d->year === now()->year ? '' : ' '.($d->year + 543);

        return $d->day.' '.self::MONTHS_SHORT[$d->month].$year.' '.$d->format('H:i').' น.';
    }

    /** 5 นาทีที่แล้ว / 2 ชม.ที่แล้ว / เมื่อวาน 18:40 น. */
    public static function ago(CarbonInterface|string|null $date): string
    {
        $d = static::parse($date);
        if (! $d) {
            return '-';
        }
        $sec = now()->diffInSeconds($d, true);

        return match (true) {
            $sec < 60 => 'เมื่อสักครู่',
            $sec < 3600 => (int) floor($sec / 60).' นาทีที่แล้ว',
            $sec < 86400 && $d->isToday() => (int) floor($sec / 3600).' ชม.ที่แล้ว',
            $d->isYesterday() => 'เมื่อวาน '.$d->format('H:i').' น.',
            default => static::compact($d),
        };
    }
}
