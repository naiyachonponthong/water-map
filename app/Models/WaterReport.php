<?php

namespace App\Models;

use App\Support\ReportOptions;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class WaterReport extends Model
{
    protected $fillable = [
        'province_id', 'district_id', 'subdistrict_id', 'lat', 'lng', 'accuracy_m', 'level', 'trend', 'place_type', 'note', 'photos',
        'reporter_name', 'reporter_phone', 'device_hash', 'ip_hash', 'source', 'user_id', 'team_id', 'status', 'verified',
        'confirm_count', 'recede_count', 'wrong_count', 'update_count', 'outside_province', 'hide_reason',
        'reviewed_by', 'reviewed_at', 'expires_at',
    ];

    protected $hidden = ['reporter_phone', 'device_hash', 'ip_hash'];

    protected function casts(): array
    {
        return [
            'lat' => 'float', 'lng' => 'float', 'photos' => 'array', 'verified' => 'boolean', 'outside_province' => 'boolean',
            'reporter_phone' => 'encrypted', 'reviewed_at' => 'datetime', 'expires_at' => 'datetime',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(WaterReportVote::class);
    }

    public function scopeInProvince(Builder $q, int $pid): Builder
    {
        return $q->where('province_id', $pid);
    }

    /** แสดงบนแผนที่อยู่ตอนนี้ */
    public function scopeCurrent(Builder $q): Builder
    {
        return $q->where('status', 'published')->where('expires_at', '>', now());
    }

    /** น่าเชื่อถือพอจะใช้เตือนอัตโนมัติ: เจ้าหน้าที่/ทีมยืนยัน หรือมีคนในพื้นที่ยืนยันอย่างน้อย 1 คน */
    public function scopeTrusted(Builder $q): Builder
    {
        return $q->where(fn ($w) => $w->where('verified', true)->orWhere('confirm_count', '>', 0));
    }

    public function isTrusted(): bool
    {
        return $this->verified || $this->confirm_count > 0;
    }

    public function levelInfo(): array
    {
        return config('floodthai.water_levels.'.$this->level) ?? ['label' => '-', 'short' => '-', 'color' => '#999'];
    }

    public function levelLabel(): string
    {
        return $this->levelInfo()['label'];
    }

    public function color(): string
    {
        return $this->levelInfo()['color'];
    }

    public function trendLabel(): ?string
    {
        return $this->trend ? (ReportOptions::TRENDS[$this->trend][0] ?? null) : null;
    }

    public function placeLabel(): ?string
    {
        return $this->place_type ? (ReportOptions::PLACES[$this->place_type][0] ?? null) : null;
    }

    public function statusLabel(): string
    {
        return ReportOptions::STATUSES[$this->status][0] ?? $this->status;
    }

    public function statusChip(): string
    {
        return ReportOptions::STATUSES[$this->status][1] ?? '';
    }

    public function sourceLabel(): string
    {
        return ReportOptions::SOURCES[$this->source][0] ?? $this->source;
    }

    public function photoUrls(): array
    {
        return collect($this->photos ?? [])->map(fn ($p) => Storage::disk('public')->url($p))->all();
    }

    public function areaLabel(): string
    {
        return collect([$this->subdistrict?->shortName(), $this->district?->shortName()])->filter()->implode(' ') ?: '-';
    }

    /** ความจาง 0-1 ตามอายุรายงาน (ใช้บนแผนที่) */
    public function freshness(): float
    {
        [$fade1, $fade2] = (array) Settings::get('report_fade_hours', $this->province_id) + [12, 24];
        $age = $this->updated_at ? $this->updated_at->diffInMinutes(now()) / 60 : 0;

        return $age < $fade1 ? 1.0 : ($age < $fade2 ? 0.7 : 0.45);
    }

    public function maskedPhone(): ?string
    {
        return $this->reporter_phone ? '0xx-xxx-'.substr($this->reporter_phone, -4) : null;
    }
}
