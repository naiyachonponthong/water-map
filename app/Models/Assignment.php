<?php

namespace App\Models;

use App\Support\TeamOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Assignment extends Model
{
    protected $fillable = [
        'help_request_id', 'team_id', 'assigned_by', 'responded_by', 'status', 'via', 'distance_m', 'eta_minutes',
        'decline_reason', 'note', 'people_rescued', 'offered_at', 'responded_at', 'en_route_at', 'arrived_at', 'done_at',
    ];

    protected function casts(): array
    {
        return [
            'offered_at' => 'datetime',
            'responded_at' => 'datetime',
            'en_route_at' => 'datetime',
            'arrived_at' => 'datetime',
            'done_at' => 'datetime',
        ];
    }

    public function helpRequest(): BelongsTo
    {
        return $this->belongsTo(HelpRequest::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class)->withTrashed();
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function isActive(): bool
    {
        return in_array($this->status, TeamOptions::ACTIVE, true);
    }

    public function statusLabel(): string
    {
        return TeamOptions::ASSIGNMENT[$this->status][0] ?? $this->status;
    }

    public function statusChip(): string
    {
        return TeamOptions::ASSIGNMENT[$this->status][1] ?? '';
    }

    /** เวลาตอบสนอง: เสนองานจนถึงที่เกิดเหตุ (นาที) */
    public function responseMinutes(): ?int
    {
        return $this->arrived_at && $this->offered_at ? (int) $this->offered_at->diffInMinutes($this->arrived_at, true) : null;
    }
}
