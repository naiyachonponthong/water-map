<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\RiskOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ข้อมูลนี้เห็นเฉพาะเจ้าหน้าที่ ไม่แสดงบนเว็บประชาชน เบอร์โทรเก็บแบบเข้ารหัส
 */
class VulnerableHousehold extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'province_id', 'district_id', 'subdistrict_id', 'lat', 'lng', 'address', 'head_name', 'phone', 'members',
        'conditions', 'note', 'caretaker_name', 'caretaker_phone', 'consent_at', 'consent_by', 'is_active',
        'open_case_id', 'last_checked_at', 'last_check_status', 'last_checked_by', 'created_by',
    ];

    protected $hidden = ['phone', 'caretaker_phone'];

    /** ไม่เก็บเบอร์และอาการลง audit log */
    protected array $auditExcept = ['phone', 'caretaker_phone', 'conditions', 'note', 'updated_at', 'last_checked_at'];

    protected function casts(): array
    {
        return [
            'lat' => 'float', 'lng' => 'float', 'conditions' => 'array', 'is_active' => 'boolean',
            'phone' => 'encrypted', 'caretaker_phone' => 'encrypted',
            'consent_at' => 'datetime', 'last_checked_at' => 'datetime',
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

    public function openCase(): BelongsTo
    {
        return $this->belongsTo(HelpRequest::class, 'open_case_id');
    }

    public function checks(): HasMany
    {
        return $this->hasMany(HouseholdCheck::class)->latest('created_at');
    }

    public function scopeInProvince(Builder $q, int $pid): Builder
    {
        return $q->where('province_id', $pid);
    }

    public function conditionLabels(): array
    {
        return collect($this->conditions ?? [])->map(fn ($c) => RiskOptions::CONDITIONS[$c][0] ?? $c)->all();
    }

    /** แปลงภาวะเป็นกลุ่มเปราะบางของเคส */
    public function caseVulnerable(): array
    {
        return collect($this->conditions ?? [])->map(fn ($c) => RiskOptions::CONDITIONS[$c][2] ?? null)->filter()->unique()->values()->all();
    }

    public function areaLabel(): string
    {
        return collect([$this->subdistrict?->shortName(), $this->district?->shortName()])->filter()->implode(' ') ?: '-';
    }

    public function maskedPhone(): string
    {
        return $this->phone ? '0xx-xxx-'.substr($this->phone, -4) : '-';
    }
}
