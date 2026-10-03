<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasBoundary;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class District extends Model
{
    use Auditable, HasBoundary;

    protected $fillable = [
        'province_id', 'code', 'name_th', 'name_en', 'center_lat', 'center_lng', 'boundary',
        'bbox_south', 'bbox_west', 'bbox_north', 'bbox_east', 'sort',
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

    public function subdistricts(): HasMany
    {
        return $this->hasMany(Subdistrict::class)->orderBy('name_th');
    }

    /** "อ.บางคล้า" หรือ "เขตบางรัก" สำหรับ กทม. */
    public function shortName(): string
    {
        return str_starts_with($this->name_th, 'เขต') ? $this->name_th : 'อ.'.$this->name_th;
    }
}
