<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\StationOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WaterStation extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'province_id', 'district_id', 'subdistrict_id', 'code', 'name', 'river', 'lat', 'lng', 'unit',
        'bank_level', 'watch_level', 'warning_level', 'critical_level', 'influence_radius_m',
        'fetch_mode', 'fetch_url', 'value_path', 'time_path', 'value_offset', 'link_url', 'is_public', 'is_active',
        'last_value', 'last_at', 'status', 'trend_per_hour', 'fetch_error', 'fetched_at',
    ];

    protected array $auditExcept = ['last_value', 'last_at', 'status', 'trend_per_hour', 'fetch_error', 'fetched_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'lat' => 'float', 'lng' => 'float', 'is_public' => 'boolean', 'is_active' => 'boolean',
            'bank_level' => 'float', 'watch_level' => 'float', 'warning_level' => 'float', 'critical_level' => 'float',
            'value_offset' => 'float', 'last_value' => 'float', 'trend_per_hour' => 'float',
            'last_at' => 'datetime', 'fetched_at' => 'datetime',
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

    public function subdistrict(): BelongsTo
    {
        return $this->belongsTo(Subdistrict::class);
    }

    public function readings(): HasMany
    {
        return $this->hasMany(StationReading::class);
    }

    public function cameras(): HasMany
    {
        return $this->hasMany(Camera::class);
    }

    public function scopeInProvince(Builder $q, int $pid): Builder
    {
        return $q->where('province_id', $pid);
    }

    /** สถานะจากค่า (ยังไม่ดูว่าขาดการติดต่อ) */
    public function statusFor(?float $value): string
    {
        if ($value === null) {
            return 'unknown';
        }
        foreach (['critical' => $this->critical_level, 'warning' => $this->warning_level, 'watch' => $this->watch_level] as $s => $limit) {
            if ($limit !== null && $value >= $limit) {
                return $s;
            }
        }

        return 'normal';
    }

    public function statusLabel(): string
    {
        return StationOptions::STATUS[$this->status][0] ?? $this->status;
    }

    public function statusChip(): string
    {
        return StationOptions::STATUS[$this->status][1] ?? '';
    }

    public function color(): string
    {
        return StationOptions::STATUS[$this->status][2] ?? '#94a3b8';
    }

    /** ห่างจากตลิ่ง (ติดลบ = ต่ำกว่าตลิ่ง) */
    public function toBank(): ?float
    {
        return $this->bank_level !== null && $this->last_value !== null ? round($this->last_value - $this->bank_level, 2) : null;
    }

    /** % ความจุลำน้ำ เทียบตลิ่ง (ใช้วาดหลอด) */
    public function fillPercent(): ?int
    {
        if ($this->bank_level === null || $this->last_value === null) {
            return null;
        }
        $base = min($this->bank_level - 5, $this->last_value);

        return (int) max(0, min(120, round(($this->last_value - $base) / max(0.01, $this->bank_level - $base) * 100)));
    }

    public function trendLabel(): ?string
    {
        if ($this->trend_per_hour === null) {
            return null;
        }
        $cm = round($this->trend_per_hour * 100);

        return $cm > 0 ? "ขึ้น {$cm} ซม./ชม." : ($cm < 0 ? 'ลง '.abs($cm).' ซม./ชม.' : 'ทรงตัว');
    }
}
