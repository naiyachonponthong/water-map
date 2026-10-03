<?php

namespace App\Models;

use App\Support\StationOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    protected $fillable = [
        'province_id', 'key', 'kind', 'level', 'title', 'body', 'district_ids', 'lat', 'lng', 'is_public', 'status',
        'created_by', 'acknowledged_by', 'acknowledged_at', 'resolved_by', 'resolved_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'district_ids' => 'array', 'is_public' => 'boolean', 'lat' => 'float', 'lng' => 'float',
            'acknowledged_at' => 'datetime', 'resolved_at' => 'datetime', 'expires_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function acker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function scopeInProvince(Builder $q, int $pid): Builder
    {
        return $q->where('province_id', $pid);
    }

    /** ยังมีผลอยู่ */
    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', 'active')->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /** วิกฤตก่อน แล้วล่าสุดก่อน */
    public function scopeSevereFirst(Builder $q): Builder
    {
        return $q->orderByRaw("case level when 'critical' then 0 when 'warning' then 1 else 2 end")->latest('updated_at');
    }

    public function levelLabel(): string
    {
        return StationOptions::ALERT_LEVELS[$this->level][0] ?? $this->level;
    }

    public function levelChip(): string
    {
        return StationOptions::ALERT_LEVELS[$this->level][1] ?? '';
    }

    public function color(): string
    {
        return StationOptions::ALERT_LEVELS[$this->level][2] ?? '#64748b';
    }

    public function icon(): string
    {
        return StationOptions::ALERT_LEVELS[$this->level][3] ?? 'bell';
    }

    public function kindLabel(): string
    {
        return StationOptions::ALERT_KINDS[$this->kind][0] ?? $this->kind;
    }

    public function kindIcon(): string
    {
        return StationOptions::ALERT_KINDS[$this->kind][1] ?? 'bell';
    }
}
