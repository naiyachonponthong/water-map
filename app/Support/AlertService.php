<?php

namespace App\Support;

use App\Models\Alert;
use App\Models\User;

/**
 * ประกาศเตือนภัยล่วงหน้า: เกิดจากระบบ (สถานี ฝน จุดเสี่ยง) หรือศูนย์ประกาศเอง
 * ระบบใช้ key กันเตือนซ้ำ ถ้าสถานการณ์เดิมรุนแรงขึ้นจะอัปเดตประกาศเดิมและแจ้งหน้าจอใหม่
 */
class AlertService
{
    public const RANK = ['watch' => 1, 'warning' => 2, 'critical' => 3];

    /**
     * @return array{0: Alert, 1: bool} [ประกาศ, true ถ้าใหม่หรือรุนแรงขึ้น]
     */
    public function raise(int $provinceId, string $key, string $kind, string $level, string $title, ?string $body = null, array $extra = []): array
    {
        $alert = Alert::inProvince($provinceId)->active()->where('key', $key)->latest('id')->first();

        if ($alert) {
            $escalated = (self::RANK[$level] ?? 0) > (self::RANK[$alert->level] ?? 0);
            $alert->fill(['level' => $level, 'title' => $title, 'body' => $body] + $extra);
            if ($escalated) {
                // รุนแรงขึ้น ต้องให้ศูนย์รับทราบใหม่
                $alert->acknowledged_by = null;
                $alert->acknowledged_at = null;
            }
            if ($alert->isDirty()) {
                $alert->save();
                $this->signal($alert, $escalated ? 'escalated' : 'updated');
            }

            return [$alert, $escalated];
        }

        $alert = Alert::create([
            'province_id' => $provinceId, 'key' => $key, 'kind' => $kind, 'level' => $level,
            'title' => $title, 'body' => $body, 'status' => 'active',
        ] + $extra);
        $this->signal($alert, 'created');

        return [$alert, true];
    }

    public function resolveKey(int $provinceId, string $key, ?User $user = null): int
    {
        $n = 0;
        Alert::inProvince($provinceId)->where('status', 'active')->where('key', $key)->get()->each(function (Alert $a) use ($user, &$n) {
            $this->resolve($a, $user);
            $n++;
        });

        return $n;
    }

    public function resolve(Alert $alert, ?User $user = null): void
    {
        if ($alert->status === 'resolved') {
            return;
        }
        $alert->update(['status' => 'resolved', 'resolved_by' => $user?->id, 'resolved_at' => now()]);
        $this->signal($alert, 'resolved');
    }

    public function acknowledge(Alert $alert, User $user): void
    {
        $alert->update(['acknowledged_by' => $user->id, 'acknowledged_at' => now()]);
        $this->signal($alert, 'acknowledged');
    }

    protected function signal(Alert $alert, string $type): void
    {
        Live::signal($alert->province_id, 'alert.changed', [
            'id' => $alert->id, 'type' => $type, 'level' => $alert->level, 'title' => $alert->title,
        ]);
    }
}
