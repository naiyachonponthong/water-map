<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\ReliefOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shelter extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'province_id', 'district_id', 'subdistrict_id', 'name', 'type', 'address', 'lat', 'lng', 'capacity', 'occupancy',
        'status', 'facilities', 'contact_name', 'contact_phone', 'note', 'is_public', 'opened_at', 'closed_at',
    ];

    protected array $auditExcept = ['occupancy', 'updated_at'];

    protected function casts(): array
    {
        return [
            'lat' => 'float', 'lng' => 'float', 'facilities' => 'array', 'is_public' => 'boolean',
            'opened_at' => 'datetime', 'closed_at' => 'datetime',
        ];
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function subdistrict(): BelongsTo
    {
        return $this->belongsTo(Subdistrict::class);
    }

    public function evacuees(): HasMany
    {
        return $this->hasMany(Evacuee::class);
    }

    public function needs(): HasMany
    {
        return $this->hasMany(ShelterNeed::class);
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'shelter_staff');
    }

    public function scopeInProvince(Builder $q, int $pid): Builder
    {
        return $q->where('province_id', $pid);
    }

    public function scopeAccepting(Builder $q): Builder
    {
        return $q->whereIn('status', ['open', 'full']);
    }

    /** นับผู้อพยพที่ยังอยู่ และเปลี่ยนเป็น "เต็ม" / กลับเป็น "เปิด" อัตโนมัติ */
    public function recount(): void
    {
        $this->occupancy = $this->evacuees()->where('status', 'in')->count();
        if ($this->capacity > 0 && in_array($this->status, ['open', 'full'], true)) {
            $this->status = $this->occupancy >= $this->capacity ? 'full' : 'open';
        }
        $this->saveQuietly();
    }

    public function percent(): ?int
    {
        return $this->capacity > 0 ? (int) min(100, round($this->occupancy / $this->capacity * 100)) : null;
    }

    public function available(): ?int
    {
        return $this->capacity > 0 ? max(0, $this->capacity - $this->occupancy) : null;
    }

    public function typeLabel(): string
    {
        return ReliefOptions::SHELTER_TYPES[$this->type][0] ?? $this->type;
    }

    public function icon(): string
    {
        return ReliefOptions::SHELTER_TYPES[$this->type][1] ?? 'house-heart';
    }

    public function statusLabel(): string
    {
        return ReliefOptions::SHELTER_STATUS[$this->status][0] ?? $this->status;
    }

    public function statusChip(): string
    {
        return ReliefOptions::SHELTER_STATUS[$this->status][1] ?? '';
    }

    public function color(): string
    {
        return ReliefOptions::SHELTER_STATUS[$this->status][2] ?? '#94a3b8';
    }

    public function areaLabel(): string
    {
        return collect([$this->subdistrict?->shortName(), $this->district?->shortName()])->filter()->implode(' ') ?: '-';
    }

    /** ผู้ใช้นี้ดูแลศูนย์นี้ได้ไหม (เจ้าหน้าที่ศูนย์เห็นเฉพาะศูนย์ที่ถูกมอบหมาย ถ้ามีการมอบหมาย) */
    public static function forUser(User $user, int $pid): Builder
    {
        $q = static::inProvince($pid);
        if ($user->hasRole('shelter-staff') && ! $user->can('settings.manage')) {
            $ids = \Illuminate\Support\Facades\DB::table('shelter_staff')->where('user_id', $user->id)->pluck('shelter_id');
            if ($ids->isNotEmpty()) {
                $q->whereIn('id', $ids);
            }
        }

        return $q;
    }
}
