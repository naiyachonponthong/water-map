<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\TeamOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Team extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'province_id', 'name', 'type', 'leader_id', 'phone', 'district_id', 'base_address', 'base_lat', 'base_lng',
        'status', 'last_lat', 'last_lng', 'last_seen_at', 'is_active', 'note',
    ];

    protected array $auditExcept = ['last_lat', 'last_lng', 'last_seen_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'base_lat' => 'float',
            'base_lng' => 'float',
            'last_lat' => 'float',
            'last_lng' => 'float',
            'last_seen_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::updated(function (self $team) {
            if ($team->wasChanged(['status', 'is_active'])) {
                \App\Support\Live::teamChanged($team);
            }
        });
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members')->withPivot('role_in_team')->withTimestamps();
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function activeAssignments(): HasMany
    {
        return $this->hasMany(Assignment::class)->whereIn('status', TeamOptions::ACTIVE);
    }

    public function scopeInProvince(Builder $q, int $provinceId): Builder
    {
        return $q->where('province_id', $provinceId);
    }

    public function statusLabel(): string
    {
        return TeamOptions::STATUSES[$this->status][0] ?? $this->status;
    }

    public function statusChip(): string
    {
        return TeamOptions::STATUSES[$this->status][1] ?? '';
    }

    public function statusColor(): string
    {
        return TeamOptions::STATUSES[$this->status][2] ?? '#94a3b8';
    }

    public function typeLabel(): string
    {
        return TeamOptions::TYPES[$this->type] ?? $this->type;
    }

    /** ตำแหน่งที่ใช้คำนวณระยะ: ล่าสุดถ้าไม่เก่าเกิน 2 ชม. ไม่งั้นใช้ฐาน */
    public function position(): ?array
    {
        if ($this->last_lat && $this->last_seen_at?->gt(now()->subHours(2))) {
            return [$this->last_lat, $this->last_lng];
        }

        return $this->base_lat ? [$this->base_lat, $this->base_lng] : null;
    }

    public function hasVehicle(string ...$types): bool
    {
        $vehicles = $this->relationLoaded('vehicles') ? $this->vehicles : $this->vehicles()->get();

        return $vehicles->where('status', '!=', 'maintenance')->whereIn('type', $types)->isNotEmpty();
    }

    /** ความเร็วของยานพาหนะที่เร็วที่สุดที่ใช้ได้ (กม./ชม.) */
    public function speedKmh(int $waterLevel): int
    {
        $vehicles = ($this->relationLoaded('vehicles') ? $this->vehicles : $this->vehicles()->get())->where('status', '!=', 'maintenance');
        // น้ำตั้งแต่ระดับเอวขึ้นไป รถทั่วไปเข้าไม่ได้
        $usable = $vehicles->filter(fn ($v) => $waterLevel < 3 || in_array($v->type, ['boat', 'high_truck', 'drone'], true));
        $speeds = $usable->map(fn ($v) => TeamOptions::VEHICLES[$v->type][2] ?? 15);

        return (int) ($speeds->max() ?: 5); // เดินลุยน้ำ 5 กม./ชม.
    }
}
