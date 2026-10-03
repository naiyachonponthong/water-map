<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLog extends Model
{
    use Prunable;

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'province_id', 'action', 'subject_type', 'subject_id', 'description',
        'before', 'after', 'ip', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** ชื่อไทยของ action */
    public const ACTIONS = [
        'created' => 'สร้าง',
        'updated' => 'แก้ไข',
        'deleted' => 'ลบ',
        'login' => 'เข้าสู่ระบบ',
        'logout' => 'ออกจากระบบ',
        'login_failed' => 'เข้าสู่ระบบไม่สำเร็จ',
        'approved' => 'อนุมัติบัญชี',
        'suspended' => 'ระงับบัญชี',
        'imported' => 'นำเข้าข้อมูล',
        'switch' => 'เปลี่ยนสวิตช์',
        'exported' => 'ส่งออกข้อมูล',
    ];

    /** ชื่อไทยของโมเดล */
    public const SUBJECTS = [
        User::class => 'ผู้ใช้',
        Province::class => 'จังหวัด',
        District::class => 'อำเภอ',
        Subdistrict::class => 'ตำบล',
        Setting::class => 'ตั้งค่า',
        EmergencyContact::class => 'เบอร์ฉุกเฉิน',
        ExternalLink::class => 'ลิงก์ภายนอก',
        RiskPoint::class => 'จุดเสี่ยง',
        VulnerableHousehold::class => 'ครัวเรือนเปราะบาง',
        Team::class => 'ทีม',
        WaterReport::class => 'รายงานระดับน้ำ',
    ];

    public static function record(string $action, ?Model $subject = null, ?array $before = null, ?array $after = null, ?string $description = null, ?int $provinceId = null): ?self
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests() && ! Auth::check()) {
            // ไม่บันทึกตอน seed / migrate
            return null;
        }

        $provinceId ??= $subject?->getAttribute('province_id')
            ?? ($subject instanceof Province ? $subject->getKey() : null)
            ?? Auth::user()?->province_id;

        return static::create([
            'user_id' => Auth::id(),
            'province_id' => $provinceId,
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'before' => $before ?: null,
            'after' => $after ?: null,
            'ip' => Request::ip(),
            'user_agent' => mb_substr((string) Request::userAgent(), 0, 255),
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function actionLabel(): string
    {
        return self::ACTIONS[$this->action] ?? $this->action;
    }

    public function subjectLabel(): string
    {
        return $this->subject_type ? (self::SUBJECTS[$this->subject_type] ?? class_basename($this->subject_type)) : '';
    }

    /** เก็บ log 365 วัน */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(365));
    }
}
