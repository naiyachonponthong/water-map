<?php

namespace App\Support;

/**
 * งานคำนวณพิกัดแบบไม่พึ่ง extension: bbox, point-in-polygon, ระยะทาง
 * พิกัด GeoJSON เป็น [lng, lat]
 */
class Geo
{
    /** คืน [west, south, east, north] ของ geometry หรือ null */
    public static function bbox(?array $geometry): ?array
    {
        if (! $geometry || empty($geometry['coordinates'])) {
            return null;
        }

        $w = $s = INF;
        $e = $n = -INF;
        foreach (static::points($geometry) as [$lng, $lat]) {
            $w = min($w, $lng);
            $e = max($e, $lng);
            $s = min($s, $lat);
            $n = max($n, $lat);
        }

        return is_finite($w) ? [round($w, 7), round($s, 7), round($e, 7), round($n, 7)] : null;
    }

    /** ทุกจุดใน geometry (generator) */
    public static function points(array $geometry): \Generator
    {
        $rings = static::rings($geometry);
        foreach ($rings as $ring) {
            foreach ($ring as $pt) {
                yield [(float) $pt[0], (float) $pt[1]];
            }
        }
    }

    /** แตก Polygon / MultiPolygon เป็นรายการ ring */
    public static function rings(array $geometry): array
    {
        return match ($geometry['type'] ?? null) {
            'Polygon' => $geometry['coordinates'],
            'MultiPolygon' => array_merge(...array_values($geometry['coordinates'])),
            default => [],
        };
    }

    public static function pointInGeometry(float $lat, float $lng, ?array $geometry): bool
    {
        if (! $geometry) {
            return false;
        }

        $polygons = match ($geometry['type'] ?? null) {
            'Polygon' => [$geometry['coordinates']],
            'MultiPolygon' => $geometry['coordinates'],
            default => [],
        };

        foreach ($polygons as $polygon) {
            // ring แรกเป็นขอบนอก ring ถัดไปเป็นรู
            if (! static::pointInRing($lat, $lng, $polygon[0] ?? [])) {
                continue;
            }
            $inHole = false;
            foreach (array_slice($polygon, 1) as $hole) {
                if (static::pointInRing($lat, $lng, $hole)) {
                    $inHole = true;
                    break;
                }
            }
            if (! $inHole) {
                return true;
            }
        }

        return false;
    }

    /** ray casting */
    public static function pointInRing(float $lat, float $lng, array $ring): bool
    {
        $inside = false;
        $count = count($ring);
        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            [$xi, $yi] = [(float) $ring[$i][0], (float) $ring[$i][1]];
            [$xj, $yj] = [(float) $ring[$j][0], (float) $ring[$j][1]];
            if ((($yi > $lat) !== ($yj > $lat)) && ($lng < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1e-12) + $xi)) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    /** ระยะทางเป็นเมตร (haversine) */
    public static function distance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * อ่านพิกัดจากข้อความ: "13.69, 101.07", ลิงก์ Google Maps (@lat,lng / q=lat,lng / !3d!4d)
     * คืน [lat, lng] หรือ null (ลิงก์ย่อ maps.app.goo.gl ต้อง resolve ก่อน)
     */
    public static function parseLatLng(string $text): ?array
    {
        $text = urldecode(trim($text));
        $patterns = [
            '/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/',
            '/@(-?\d+\.\d+),\s*(-?\d+\.\d+)/',
            '/[?&](?:q|query|ll|destination)=(-?\d+\.\d+),\s*(-?\d+\.\d+)/',
            '/(-?\d{1,2}\.\d{3,})\s*[, ]\s*(-?\d{2,3}\.\d{3,})/',
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $text, $m)) {
                $lat = (float) $m[1];
                $lng = (float) $m[2];
                if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
                    return [$lat, $lng];
                }
            }
        }

        return null;
    }
}
