<?php

namespace App\Models;

use App\Support\CaseOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpRequestEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['help_request_id', 'user_id', 'actor', 'type', 'from_status', 'to_status', 'note', 'data', 'public', 'created_at'];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'public' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public const TYPES = [
        'created' => ['รับคำขอ', 'plus-circle'],
        'status' => ['เปลี่ยนสถานะ', 'arrow-right-circle'],
        'note' => ['บันทึก', 'chat-left-text'],
        'update' => ['ผู้แจ้งอัปเดต', 'arrow-repeat'],
        'merged' => ['รวมเคส', 'intersect'],
        'priority' => ['ปรับความเร่งด่วน', 'sort-down'],
        'call' => ['โทรติดต่อ', 'telephone'],
        'edit' => ['แก้ไขข้อมูล', 'pencil'],
    ];

    /** ทุกเหตุการณ์ของเคส = แจ้งหน้าจอศูนย์และทีมให้โหลดข้อมูลใหม่ */
    protected static function booted(): void
    {
        static::created(function (self $e) {
            $case = $e->helpRequest;
            if ($case) {
                \App\Support\Live::caseChanged($case, $e->type, $e->summary());
            }
        });
    }

    public function helpRequest(): BelongsTo
    {
        return $this->belongsTo(HelpRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type][0] ?? $this->type;
    }

    public function icon(): string
    {
        return $this->type === 'status' && $this->to_status
            ? CaseOptions::status($this->to_status, 3)
            : (self::TYPES[$this->type][1] ?? 'dot');
    }

    /** ข้อความสำหรับแสดงใน timeline */
    public function summary(bool $forRequester = false): string
    {
        return match ($this->type) {
            'created' => $forRequester ? 'ศูนย์ได้รับคำขอของคุณแล้ว' : 'รับคำขอ'.($this->data['source_label'] ?? ''),
            'status' => $forRequester
                ? CaseOptions::status($this->to_status, 1)
                : CaseOptions::status((string) $this->from_status).' → '.CaseOptions::status((string) $this->to_status),
            default => $this->typeLabel(),
        };
    }
}
