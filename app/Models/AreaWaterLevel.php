<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** ระดับน้ำรายตำบลที่เจ้าหน้าที่ประกาศ มีวันหมดอายุ */
class AreaWaterLevel extends Model
{
    protected $fillable = ['province_id', 'subdistrict_id', 'level', 'note', 'set_by', 'expires_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public function subdistrict(): BelongsTo
    {
        return $this->belongsTo(Subdistrict::class);
    }

    public function setter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by');
    }

    public function scopeCurrent(Builder $q): Builder
    {
        return $q->where('expires_at', '>', now());
    }
}
