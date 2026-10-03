<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalLink extends Model
{
    use Auditable;

    protected $fillable = ['province_id', 'title', 'description', 'url', 'source_name', 'sort', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /** ลิงก์ที่แสดงในจังหวัดนี้ = ของจังหวัด + ลิงก์กลาง (province_id null) */
    public function scopeForProvince(Builder $q, int $provinceId): Builder
    {
        return $q->where('is_active', true)
            ->where(fn ($w) => $w->where('province_id', $provinceId)->orWhereNull('province_id'))
            ->orderByRaw('province_id is null')
            ->orderBy('sort');
    }
}
