<?php

namespace App\Models;

use App\Support\ReliefOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** ข้อมูลส่วนบุคคล: ไม่เก็บเลขบัตรประชาชน เบอร์เข้ารหัส ค้นหาด้วย hash */
class Evacuee extends Model
{
    protected $fillable = [
        'province_id', 'shelter_id', 'code', 'family_code', 'name', 'phone', 'phone_hash', 'age_group', 'gender', 'needs',
        'address', 'note', 'allow_lookup', 'status', 'help_request_id', 'vulnerable_household_id', 'registered_by',
        'checked_in_at', 'checked_out_at', 'out_reason',
    ];

    protected $hidden = ['phone', 'phone_hash'];

    protected function casts(): array
    {
        return [
            'phone' => 'encrypted', 'needs' => 'array', 'allow_lookup' => 'boolean',
            'checked_in_at' => 'datetime', 'checked_out_at' => 'datetime',
        ];
    }

    public function shelter(): BelongsTo
    {
        return $this->belongsTo(Shelter::class);
    }

    public function helpRequest(): BelongsTo
    {
        return $this->belongsTo(HelpRequest::class);
    }

    public function registrar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function scopeInProvince(Builder $q, int $pid): Builder
    {
        return $q->where('province_id', $pid);
    }

    public function needLabels(): array
    {
        return collect($this->needs ?? [])->map(fn ($n) => ReliefOptions::EVACUEE_NEEDS[$n][0] ?? $n)->all();
    }

    public function ageLabel(): ?string
    {
        return $this->age_group ? (ReliefOptions::AGE_GROUPS[$this->age_group] ?? null) : null;
    }

    /** ชื่อแบบปิดบังสำหรับหน้าค้นหาญาติ: สมชาย ใ*** */
    public function maskedName(): string
    {
        $parts = preg_split('/\s+/u', trim($this->name));
        $first = array_shift($parts);
        $last = $parts ? mb_substr(end($parts), 0, 1).'***' : '';

        return trim($first.' '.$last);
    }
}
