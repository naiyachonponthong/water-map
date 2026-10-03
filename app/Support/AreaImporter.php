<?php

namespace App\Support;

use App\Models\District;
use App\Models\Province;
use App\Models\Subdistrict;

/**
 * นำเข้าขอบเขตอำเภอ/ตำบลจาก GeoJSON FeatureCollection
 * รองรับชื่อ property ที่พบบ่อย เช่นชุดข้อมูล COD-AB ของ OCHA/HDX (ADM2_TH, ADM2_PCODE = TH2401)
 * และไฟล์ทั่วไป (name_th, code, amp_th, tam_th ...)
 */
class AreaImporter
{
    public const NAME_KEYS = [
        'district' => ['ADM2_TH', 'adm2_th', 'AMP_NAMT', 'amp_th', 'AP_TN', 'name_th', 'NAME_TH', 'district_th', 'amphoe', 'name'],
        'subdistrict' => ['ADM3_TH', 'adm3_th', 'TAM_NAMT', 'tam_th', 'TB_TN', 'name_th', 'NAME_TH', 'subdistrict_th', 'tambon', 'name'],
    ];

    public const CODE_KEYS = [
        'district' => ['ADM2_PCODE', 'adm2_pcode', 'AMP_CODE', 'AP_IDN', 'amphoe_code', 'code', 'CODE'],
        'subdistrict' => ['ADM3_PCODE', 'adm3_pcode', 'TAM_CODE', 'TB_IDN', 'tambon_code', 'code', 'CODE'],
    ];

    public const PARENT_KEYS = ['ADM2_PCODE', 'adm2_pcode', 'AMP_CODE', 'AP_IDN', 'amphoe_code', 'district_code'];

    public const PARENT_NAME_KEYS = ['ADM2_TH', 'adm2_th', 'AMP_NAMT', 'amp_th', 'AP_TN', 'district_th', 'amphoe'];

    public const POSTCODE_KEYS = ['postcode', 'POSTCODE', 'zip', 'ZIP'];

    public array $report = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'other_province' => 0, 'errors' => []];

    public function __construct(protected Province $province) {}

    public function import(array $collection, string $level, bool $replaceBoundaryOnly = false): array
    {
        $features = $collection['type'] === 'FeatureCollection' ? ($collection['features'] ?? []) : [$collection];

        foreach ($features as $i => $feature) {
            $props = $feature['properties'] ?? [];
            $geometry = $feature['geometry'] ?? null;

            if (! in_array($geometry['type'] ?? null, ['Polygon', 'MultiPolygon'], true)) {
                $this->report['skipped']++;

                continue;
            }

            $code = $this->code($this->pick($props, self::CODE_KEYS[$level]));
            $name = $this->cleanName($this->pick($props, self::NAME_KEYS[$level]), $level);

            // ข้ามแถวที่เป็นของจังหวัดอื่น (ตัดสินจากรหัส ถ้ามี)
            if ($code && ! str_starts_with($code, $this->province->code)) {
                $this->report['other_province']++;

                continue;
            }
            if (! $name && ! $code) {
                $this->report['errors'][] = 'แถว '.($i + 1).': ไม่พบชื่อหรือรหัสพื้นที่';

                continue;
            }

            try {
                $level === 'district'
                    ? $this->upsertDistrict($code, $name, $geometry, $replaceBoundaryOnly)
                    : $this->upsertSubdistrict($code, $name, $props, $geometry, $replaceBoundaryOnly);
            } catch (\Throwable $e) {
                $this->report['errors'][] = ($name ?: $code).': '.$e->getMessage();
            }
        }

        $this->fillProvinceCenter();

        return $this->report;
    }

    protected function upsertDistrict(?string $code, ?string $name, array $geometry, bool $boundaryOnly): void
    {
        $district = District::where('province_id', $this->province->id)
            ->where(fn ($q) => $q->when($code, fn ($w) => $w->where('code', $code))->when($name, fn ($w) => $w->orWhere('name_th', $name)))
            ->first();

        if (! $district && $boundaryOnly) {
            $this->report['skipped']++;

            return;
        }

        $isNew = ! $district;
        $district ??= new District(['province_id' => $this->province->id, 'sort' => District::where('province_id', $this->province->id)->max('sort') + 1]);
        if ($code && ! $district->code) {
            $district->code = $code;
        }
        if ($name && ! $district->name_th) {
            $district->name_th = $name;
        }
        $district->center_lat = $district->center_lng = null;
        $district->setBoundaryFromGeometry($geometry)->save();

        $this->report[$isNew ? 'created' : 'updated']++;
    }

    protected function upsertSubdistrict(?string $code, ?string $name, array $props, array $geometry, bool $boundaryOnly): void
    {
        // หาอำเภอแม่: จากรหัสอำเภอ หรือ 4 หลักแรกของรหัสตำบล หรือชื่ออำเภอ
        $parentCode = $this->code($this->pick($props, self::PARENT_KEYS)) ?: ($code ? substr($code, 0, 4) : null);
        $parentName = $this->cleanName($this->pick($props, self::PARENT_NAME_KEYS), 'district');

        $district = District::where('province_id', $this->province->id)
            ->where(fn ($q) => $q->when($parentCode, fn ($w) => $w->where('code', $parentCode))->when($parentName, fn ($w) => $w->orWhere('name_th', $parentName)))
            ->first();

        if (! $district) {
            throw new \RuntimeException('ไม่พบอำเภอของตำบลนี้ (นำเข้าอำเภอก่อน)');
        }

        $sub = Subdistrict::where('district_id', $district->id)
            ->where(fn ($q) => $q->when($code, fn ($w) => $w->where('code', $code))->when($name, fn ($w) => $w->orWhere('name_th', $name)))
            ->first();

        if (! $sub && $boundaryOnly) {
            $this->report['skipped']++;

            return;
        }

        $isNew = ! $sub;
        $sub ??= new Subdistrict(['province_id' => $this->province->id, 'district_id' => $district->id]);
        $sub->code = $sub->code ?: $code;
        $sub->name_th = $sub->name_th ?: $name;
        $sub->postcode = $sub->postcode ?: $this->pick($props, self::POSTCODE_KEYS);
        $sub->center_lat = $sub->center_lng = null;
        $sub->setBoundaryFromGeometry($geometry)->save();

        $this->report[$isNew ? 'created' : 'updated']++;
    }

    /** ถ้าจังหวัดยังไม่มีพิกัดกลาง ใช้กึ่งกลางของ bbox อำเภอทั้งหมด */
    protected function fillProvinceCenter(): void
    {
        if ($this->province->hasCenter()) {
            return;
        }
        $box = District::where('province_id', $this->province->id)->whereNotNull('boundary')
            ->selectRaw('min(bbox_south) s, min(bbox_west) w, max(bbox_north) n, max(bbox_east) e')->first();
        if ($box && $box->s !== null) {
            $this->province->update([
                'center_lat' => round(($box->s + $box->n) / 2, 7),
                'center_lng' => round(($box->w + $box->e) / 2, 7),
            ]);
        }
    }

    protected function pick(array $props, array $keys): ?string
    {
        foreach ($keys as $k) {
            if (isset($props[$k]) && trim((string) $props[$k]) !== '') {
                return trim((string) $props[$k]);
            }
        }

        return null;
    }

    /** "TH2401" -> "2401", "240101" -> "240101" */
    protected function code(?string $raw): ?string
    {
        if (! $raw) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $raw);

        return $digits !== '' ? $digits : null;
    }

    /** ตัดคำนำหน้า "อำเภอ" "อ." "ตำบล" "ต." "เขต" "แขวง" */
    protected function cleanName(?string $name, string $level): ?string
    {
        if (! $name) {
            return null;
        }
        $prefixes = $level === 'district' ? ['อำเภอ', 'อ.', 'กิ่งอำเภอ'] : ['ตำบล', 'ต.', 'แขวง'];
        foreach ($prefixes as $p) {
            if (str_starts_with($name, $p)) {
                return trim(mb_substr($name, mb_strlen($p)));
            }
        }

        return $name;
    }
}
