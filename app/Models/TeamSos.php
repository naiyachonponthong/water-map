<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamSos extends Model
{
    protected $table = 'team_sos';

    protected $fillable = [
        'province_id', 'team_id', 'user_id', 'help_request_id', 'kind', 'lat', 'lng', 'note', 'status',
        'acked_by', 'acked_at', 'resolved_by', 'resolved_at', 'resolve_note',
    ];

    protected function casts(): array
    {
        return ['lat' => 'float', 'lng' => 'float', 'acked_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public const KINDS = [
        'backup' => ['ขอกำลังเสริม', 'people-fill'],
        'injured' => ['มีคนในทีมบาดเจ็บ', 'bandaid-fill'],
        'boat_trouble' => ['เรือ/รถเสีย ติดอยู่', 'tools'],
        'other' => ['เหตุอื่น', 'exclamation-triangle-fill'],
    ];

    public const STATUSES = ['open' => 'รอศูนย์รับทราบ', 'ack' => 'ศูนย์รับทราบแล้ว', 'resolved' => 'คลี่คลายแล้ว'];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function helpRequest(): BelongsTo
    {
        return $this->belongsTo(HelpRequest::class);
    }

    public function acker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acked_by');
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind][0] ?? $this->kind;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
