<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasBoundary;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subdistrict extends Model
{
    use Auditable, HasBoundary;

    protected $fillable = [
        'province_id', 'district_id', 'code', 'name_th', 'name_en', 'postcode', 'center_lat', 'center_lng',
        'boundary', 'bbox_south', 'bbox_west', 'bbox_north', 'bbox_east',
    ];

    protected $hidden = ['boundary'];

    protected array $auditExcept = ['boundary', 'updated_at'];

    protected function casts(): array
    {
        return [
            'center_lat' => 'float',
            'center_lng' => 'float',
            'bbox_south' => 'float',
            'bbox_west' => 'float',
            'bbox_north' => 'float',
            'bbox_east' => 'float',
        ];
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function shortName(): string
    {
        // กทม. (รหัสขึ้นต้น 10) ใช้ "แขวง" ไม่ต้องโหลดจังหวัดเพิ่ม
        return (str_starts_with((string) $this->code, '10') ? 'แขวง' : 'ต.').$this->name_th;
    }

    /** หาตำบลที่มีจุดนี้อยู่ ในจังหวัดที่กำหนด */
    public static function locate(int $provinceId, float $lat, float $lng): ?self
    {
        return static::query()
            ->where('province_id', $provinceId)
            ->whereNotNull('boundary')
            ->where('bbox_south', '<=', $lat)->where('bbox_north', '>=', $lat)
            ->where('bbox_west', '<=', $lng)->where('bbox_east', '>=', $lng)
            ->get()
            ->first(fn (self $s) => $s->containsPoint($lat, $lng));
    }
}
