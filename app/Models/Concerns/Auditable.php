<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * บันทึกการสร้าง/แก้ไข/ลบ ลง audit_logs อัตโนมัติ
 * กำหนด protected array $auditExcept ในโมเดลเพื่อตัดฟิลด์ที่ไม่ต้องเก็บ
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (Model $m) => AuditLog::record('created', $m, null, $m->auditFilter($m->getAttributes())));

        static::updated(function (Model $m) {
            $after = $m->auditFilter($m->getChanges());
            if ($after === []) {
                return;
            }
            $before = array_intersect_key($m->getOriginal(), $after);
            AuditLog::record('updated', $m, $before, $after);
        });

        static::deleted(fn (Model $m) => AuditLog::record('deleted', $m, $m->auditFilter($m->getOriginal()), null));
    }

    public function auditFilter(array $attributes): array
    {
        $except = array_merge(['created_at', 'updated_at'], property_exists($this, 'auditExcept') ? $this->auditExcept : []);

        return array_diff_key($attributes, array_flip($except));
    }
}
