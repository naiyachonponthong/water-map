<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\RecoveryOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class DamageClaim extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'code', 'province_id', 'district_id', 'subdistrict_id', 'head_name', 'phone', 'phone_hash', 'address', 'lat', 'lng',
        'members', 'tenure', 'house_damage', 'water_level', 'flood_days', 'losses', 'crop_rai', 'livestock', 'photos', 'note',
        'source', 'status', 'evidence', 'evidence_score', 'help_request_id', 'surveyor_id', 'surveyed_at', 'verified_damage',
        'survey_note', 'survey_photos', 'suggested_amount', 'approved_amount', 'approved_by', 'approved_at', 'paid_amount',
        'payment_ref', 'paid_by', 'paid_at', 'reject_reason', 'device_hash',
    ];

    protected $hidden = ['phone', 'phone_hash', 'device_hash'];

    protected array $auditExcept = ['phone', 'phone_hash', 'evidence', 'photos', 'survey_photos', 'updated_at'];

    protected function casts(): array
    {
        return [
            'phone' => 'encrypted', 'lat' => 'float', 'lng' => 'float', 'losses' => 'array', 'photos' => 'array',
            'survey_photos' => 'array', 'evidence' => 'array', 'crop_rai' => 'float',
            'suggested_amount' => 'float', 'approved_amount' => 'float', 'paid_amount' => 'float',
            'surveyed_at' => 'datetime', 'approved_at' => 'datetime', 'paid_at' => 'datetime',
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

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function surveyor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'surveyor_id');
    }

    public function helpRequest(): BelongsTo
    {
        return $this->belongsTo(HelpRequest::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ClaimEvent::class)->orderBy('id');
    }

    public function scopeInProvince(Builder $q, int $pid): Builder
    {
        return $q->where('province_id', $pid);
    }

    public function statusLabel(): string
    {
        return RecoveryOptions::STATUS[$this->status][0] ?? $this->status;
    }

    public function statusChip(): string
    {
        return RecoveryOptions::STATUS[$this->status][1] ?? '';
    }

    public function houseLabel(?string $level = null): string
    {
        $level ??= $this->verified_damage ?? $this->house_damage;

        return RecoveryOptions::HOUSE[$level][0] ?? $level;
    }

    public function lossLabels(): array
    {
        return collect($this->losses ?? [])->map(fn ($l) => RecoveryOptions::LOSSES[$l][0] ?? $l)->all();
    }

    public function isOpen(): bool
    {
        return in_array($this->status, RecoveryOptions::OPEN, true);
    }

    public function trackUrl(): string
    {
        return URL::signedRoute('public.recovery.track', ['code' => $this->code]);
    }

    public function photoUrls(string $field = 'photos'): array
    {
        return collect($this->{$field} ?? [])->map(fn ($p) => Storage::disk('public')->url($p))->all();
    }

    public function areaLabel(): string
    {
        return collect([$this->subdistrict?->shortName(), $this->district?->shortName()])->filter()->implode(' ') ?: '-';
    }
}
