<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\ReliefOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Announcement extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'province_id', 'alert_id', 'title', 'body', 'level', 'audience', 'district_ids', 'pinned', 'send_line',
        'line_status', 'line_error', 'line_recipients', 'line_sent_at', 'published_at', 'expires_at', 'created_by',
    ];

    protected array $auditExcept = ['line_status', 'line_error', 'line_recipients', 'line_sent_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'district_ids' => 'array', 'pinned' => 'boolean', 'send_line' => 'boolean',
            'line_sent_at' => 'datetime', 'published_at' => 'datetime', 'expires_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function alert(): BelongsTo
    {
        return $this->belongsTo(Alert::class);
    }

    public function scopeInProvince(Builder $q, int $pid): Builder
    {
        return $q->where('province_id', $pid);
    }

    /** เผยแพร่แล้วและยังไม่หมดอายุ */
    public function scopeLive(Builder $q): Builder
    {
        return $q->whereNotNull('published_at')->where('published_at', '<=', now())
            ->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function levelLabel(): string
    {
        return ReliefOptions::ANNOUNCE_LEVELS[$this->level][0] ?? $this->level;
    }

    public function levelChip(): string
    {
        return ReliefOptions::ANNOUNCE_LEVELS[$this->level][1] ?? '';
    }

    public function color(): string
    {
        return ReliefOptions::ANNOUNCE_LEVELS[$this->level][2] ?? '#2563eb';
    }

    public function icon(): string
    {
        return ReliefOptions::ANNOUNCE_LEVELS[$this->level][3] ?? 'info-circle-fill';
    }

    public function districtNames(): string
    {
        return $this->district_ids ? District::whereIn('id', $this->district_ids)->pluck('name_th')->map(fn ($n) => 'อ.'.$n)->implode(' ') : 'ทั้งจังหวัด';
    }
}
