<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

/**
 * ค่าตั้งค่าแบบ DB-first: อ่านจากตาราง settings ก่อน ไม่มีจึงใช้ config('floodthai.defaults')
 * ลำดับ: ค่าของจังหวัด > ค่าระดับระบบ (province_id null) > ค่าใน config
 */
class Settings
{
    public static function get(string $key, ?int $provinceId = null, mixed $default = null): mixed
    {
        $all = static::all($provinceId);

        if (Arr::has($all, $key)) {
            return Arr::get($all, $key);
        }

        return $default ?? config("floodthai.defaults.$key");
    }

    /** ค่าทั้งหมดของจังหวัด (รวมค่าระบบ) เก็บ cache 10 นาที */
    public static function all(?int $provinceId = null): array
    {
        return Cache::remember(static::cacheKey($provinceId), 600, function () use ($provinceId) {
            $global = Setting::whereNull('province_id')->pluck('value', 'key')->all();
            $own = $provinceId ? Setting::where('province_id', $provinceId)->pluck('value', 'key')->all() : [];

            return array_replace(static::unwrap($global), static::unwrap($own));
        });
    }

    public static function set(string $key, mixed $value, ?int $provinceId = null): void
    {
        Setting::updateOrCreate(
            ['province_id' => $provinceId, 'key' => $key],
            ['value' => ['v' => $value]],
        );
        static::flush($provinceId);
    }

    public static function flush(?int $provinceId = null): void
    {
        Cache::forget(static::cacheKey($provinceId));
        if ($provinceId === null) {
            // ค่าระบบเปลี่ยน ล้าง cache ทุกจังหวัด (increment ใช้กับ key ที่ยังไม่มีไม่ได้ในบาง driver)
            Cache::forever('settings:gen', (int) Cache::get('settings:gen', 0) + 1);
        }
    }

    protected static function cacheKey(?int $provinceId): string
    {
        return 'settings:'.Cache::get('settings:gen', 0).':'.($provinceId ?? 'global');
    }

    /** value เก็บเป็น {"v": ...} เพื่อรองรับ string/number/bool/array ได้เหมือนกัน */
    protected static function unwrap(array $rows): array
    {
        return array_map(fn ($v) => is_array($v) && array_key_exists('v', $v) ? $v['v'] : $v, $rows);
    }
}
