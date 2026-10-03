<?php

namespace App\Models\Concerns;

use App\Support\Geo;

/**
 * ใช้กับ District / Subdistrict: เก็บขอบเขต GeoJSON + bbox + จุดกึ่งกลาง
 */
trait HasBoundary
{
    /** ตั้งขอบเขตจาก geometry (array หรือ JSON string) แล้วคำนวณ bbox และจุดกึ่งกลางให้ */
    public function setBoundaryFromGeometry(array|string|null $geometry): static
    {
        if ($geometry === null || $geometry === '') {
            $this->boundary = null;
            $this->bbox_south = $this->bbox_west = $this->bbox_north = $this->bbox_east = null;

            return $this;
        }

        $geometry = is_string($geometry) ? json_decode($geometry, true) : $geometry;
        $bbox = Geo::bbox($geometry);

        $this->boundary = json_encode($geometry, JSON_UNESCAPED_UNICODE);
        if ($bbox) {
            [$this->bbox_west, $this->bbox_south, $this->bbox_east, $this->bbox_north] = $bbox;
            if ($this->center_lat === null || $this->center_lng === null) {
                $this->center_lat = round(($bbox[1] + $bbox[3]) / 2, 7);
                $this->center_lng = round(($bbox[0] + $bbox[2]) / 2, 7);
            }
        }

        return $this;
    }

    public function boundaryArray(): ?array
    {
        return $this->boundary ? json_decode($this->boundary, true) : null;
    }

    public function hasBoundary(): bool
    {
        return ! empty($this->boundary);
    }

    /** จุดนี้อยู่ในพื้นที่นี้หรือไม่ (เช็ก bbox ก่อน แล้วค่อยเช็ก polygon) */
    public function containsPoint(float $lat, float $lng): bool
    {
        if (! $this->hasBoundary()) {
            return false;
        }
        if ($lat < $this->bbox_south || $lat > $this->bbox_north || $lng < $this->bbox_west || $lng > $this->bbox_east) {
            return false;
        }

        return Geo::pointInGeometry($lat, $lng, $this->boundaryArray());
    }
}
