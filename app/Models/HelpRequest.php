<?php

namespace App\Models;

use App\Support\CaseOptions;
use App\Support\ThaiDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class HelpRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code', 'province_id', 'district_id', 'subdistrict_id', 'team_id', 'assignment_id', 'household_id',
        'lat', 'lng', 'location_source', 'location_raw', 'accuracy_m', 'outside_province', 'address_text', 'landmark', 'floor_level',
        'water_level', 'people_count', 'vulnerable', 'needs', 'needs_note', 'photos',
        'requester_name', 'requester_phone', 'phone_hash', 'phone_last4', 'on_behalf', 'contact_name', 'contact_phone',
        'source', 'status', 'priority_score', 'priority', 'priority_locked',
        'duplicate_of_id', 'possible_duplicate_of_id', 'report_count', 'outcome', 'people_rescued', 'close_note',
        'created_by', 'screened_by', 'screened_at', 'closed_at', 'requester_updated_at', 'device_hash', 'ip_hash',
    ];

    protected $hidden = ['requester_phone', 'contact_phone', 'phone_hash', 'device_hash', 'ip_hash'];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'outside_province' => 'boolean',
            'on_behalf' => 'boolean',
            'priority_locked' => 'boolean',
            'vulnerable' => 'array',
            'needs' => 'array',
            'photos' => 'array',
            'requester_phone' => 'encrypted',
            'contact_phone' => 'encrypted',
            'screened_at' => 'datetime',
            'closed_at' => 'datetime',
            'requester_updated_at' => 'datetime',
        ];
    }

    /* ---------------- ความสัมพันธ์ ---------------- */

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

    public function events(): HasMany
    {
        return $this->hasMany(HelpRequestEvent::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of_id');
    }

    public function possibleDuplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'possible_duplicate_of_id');
    }

    public function merged(): HasMany
    {
        return $this->hasMany(self::class, 'duplicate_of_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class)->withTrashed();
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class)->latest('id');
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(VulnerableHousehold::class, 'household_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function screener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'screened_by');
    }

    /* ---------------- scope ---------------- */

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereIn('status', CaseOptions::OPEN);
    }

    public function scopeInProvince(Builder $q, int $provinceId): Builder
    {
        return $q->where('province_id', $provinceId);
    }

    /** เรียงตามความเร่งด่วน: คะแนนสูงก่อน แล้วเคสเก่าก่อน */
    public function scopeUrgentFirst(Builder $q): Builder
    {
        return $q->orderByDesc('priority_score')->orderBy('created_at');
    }

    /* ---------------- ตัวช่วย ---------------- */

    public function isOpen(): bool
    {
        return in_array($this->status, CaseOptions::OPEN, true);
    }

    public function statusLabel(bool $forRequester = false): string
    {
        return CaseOptions::status($this->status, $forRequester ? 1 : 0);
    }

    public function statusChip(): string
    {
        return CaseOptions::status($this->status, 2);
    }

    public function priorityLabel(): string
    {
        return CaseOptions::priority($this->priority);
    }

    public function priorityChip(): string
    {
        return CaseOptions::priority($this->priority, 1);
    }

    public function priorityColor(): string
    {
        return CaseOptions::priority($this->priority, 2);
    }

    public function waterLabel(): string
    {
        return CaseOptions::waterLevel((int) $this->water_level);
    }

    /** ป้ายกลุ่มเปราะบางที่ติ๊กไว้ */
    public function vulnerableLabels(): array
    {
        return collect($this->vulnerable ?? [])->map(fn ($k) => CaseOptions::VULNERABLE[$k][0] ?? $k)->all();
    }

    public function needLabels(): array
    {
        return collect($this->needs ?? [])->map(fn ($k) => CaseOptions::NEEDS[$k][0] ?? $k)->all();
    }

    public function hasVulnerable(string ...$keys): bool
    {
        return (bool) array_intersect($keys, $this->vulnerable ?? []);
    }

    /** เบอร์แบบซ่อน 081-xxx-5678 */
    public function maskedPhone(): string
    {
        return '0xx-xxx-'.$this->phone_last4;
    }

    public function photoUrls(): array
    {
        return collect($this->photos ?? [])->map(fn ($p) => Storage::disk('public')->url($p))->all();
    }

    public function areaLabel(): string
    {
        return collect([$this->subdistrict?->shortName(), $this->district?->shortName()])->filter()->implode(' ') ?: 'ไม่ทราบตำบล';
    }

    public function mapsUrl(): string
    {
        return "https://www.google.com/maps/dir/?api=1&destination={$this->lat},{$this->lng}";
    }

    /** ลิงก์ติดตามแบบเซ็นชื่อ (ไม่หมดอายุ ส่งต่อให้ญาติได้) */
    public function trackUrl(): string
    {
        return URL::signedRoute('public.track.show', ['code' => $this->code]);
    }

    public function signedAction(string $route): string
    {
        return URL::signedRoute($route, ['code' => $this->code]);
    }

    /** นาทีที่รอตั้งแต่แจ้ง */
    public function waitingMinutes(): int
    {
        $end = $this->isOpen() ? now() : ($this->closed_at ?? $this->updated_at);

        return (int) $this->created_at->diffInMinutes($end, true);
    }

    public function waitingLabel(): string
    {
        $m = $this->waitingMinutes();

        return $m < 60 ? "$m นาที" : intdiv($m, 60).' ชม. '.($m % 60).' นาที';
    }

    public function createdLabel(): string
    {
        return ThaiDate::ago($this->created_at);
    }
}
