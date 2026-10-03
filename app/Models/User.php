<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use Auditable, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'phone', 'email', 'password', 'line_user_id', 'line_display_name', 'avatar_url',
        'province_id', 'organization', 'position', 'requested_role', 'status',
        'approved_by', 'approved_at', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    /** ฟิลด์ที่ไม่ต้องบันทึกลง audit log */
    protected array $auditExcept = ['password', 'remember_token', 'last_login_at', 'updated_at', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_last_step'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'approved_at' => 'datetime',
            'last_login_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** ทีมที่ผู้ใช้สังกัด (หนึ่งคนหนึ่งทีม) */
    public function team(): \Illuminate\Database\Eloquent\Relations\HasOneThrough
    {
        return $this->hasOneThrough(Team::class, TeamMember::class, 'user_id', 'id', 'id', 'team_id');
    }

    public function teamMembership(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(TeamMember::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super-admin');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** ชื่อบทบาทหลักเป็นภาษาไทย */
    public function roleLabel(): string
    {
        $role = $this->getRoleNames()->first();

        return $role ? (config("floodthai.roles.$role") ?? $role) : 'ยังไม่กำหนดบทบาท';
    }

    public function initials(): string
    {
        return mb_substr(trim($this->name), 0, 1);
    }

    /** ผู้ใช้คนนี้จัดการข้อมูลของจังหวัดนี้ได้หรือไม่ */
    public function canManageProvince(?int $provinceId): bool
    {
        return $this->isSuperAdmin() || ($provinceId !== null && $this->province_id === $provinceId);
    }

    public static function normalizePhone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (str_starts_with($digits, '66') && strlen($digits) === 11) {
            $digits = '0'.substr($digits, 2);
        }

        return $digits;
    }

    /* ---------------- ยืนยันตัวตน 2 ชั้น ---------------- */

    public function hasTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null && filled($this->two_factor_secret);
    }

    /** บทบาทที่ระบบบังคับให้ใช้ 2 ชั้น (ตั้งใน REQUIRE_2FA_ROLES) */
    public function mustUseTwoFactor(): bool
    {
        $roles = array_filter(array_map('trim', explode(',', (string) config('floodthai.require_2fa_roles'))));

        return $roles && $this->hasAnyRole($roles);
    }

    /** ตรวจรหัสจากแอป หรือรหัสสำรอง (รหัสสำรองใช้ได้ครั้งเดียว) */
    public function verifyTwoFactor(string $input): bool
    {
        if (! $this->hasTwoFactor()) {
            return false;
        }
        $input = trim($input);
        if (preg_match('/^\d{6}$/', preg_replace('/\s+/', '', $input))) {
            $step = \App\Support\Totp::verify($this->two_factor_secret, $input, $this->two_factor_last_step);
            if ($step === null) {
                return false;
            }
            $this->forceFill(['two_factor_last_step' => $step])->saveQuietly();

            return true;
        }
        $codes = $this->two_factor_recovery_codes ?? [];
        $i = array_search(strtolower($input), $codes, true);
        if ($i === false) {
            return false;
        }
        unset($codes[$i]);
        $this->forceFill(['two_factor_recovery_codes' => array_values($codes)])->saveQuietly();

        return true;
    }
}
