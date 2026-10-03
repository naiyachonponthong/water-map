<?php

namespace App\Support;

use App\Models\Alert;
use App\Models\DamageClaim;
use App\Models\HelpRequest;
use App\Models\RiskPoint;
use App\Models\User;
use App\Models\WaterReport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/**
 * เมนูทั้งระบบขับด้วย array เดียว ใช้ทั้ง rail ซ้าย, กริดเมนูทั้งหมด และหน้าแรกมือถือ
 * route = null หมายถึงยังไม่เปิดใช้ (ซ่อนจากทุกที่)
 */
class Menu
{
    public static function groups(): array
    {
        return [
            'command' => [
                'label' => 'สั่งการ',
                'items' => [
                    ['key' => 'dashboard', 'label' => 'ศูนย์สั่งการ', 'short' => 'สั่งการ', 'icon' => 'broadcast-pin', 'tone' => 'primary', 'route' => 'dashboard', 'active' => 'dashboard', 'can' => 'dashboard.view', 'rail' => true],
                    ['key' => 'cases', 'label' => 'เคสขอความช่วยเหลือ', 'short' => 'เคส', 'icon' => 'life-preserver', 'tone' => 'danger', 'route' => 'cases.index', 'active' => 'cases.*', 'can' => 'cases.view', 'rail' => true, 'badge' => 'triage_cases'],
                    ['key' => 'dispatch', 'label' => 'สั่งการทีม', 'short' => 'สั่งทีม', 'icon' => 'kanban', 'tone' => 'primary', 'route' => 'dispatch.index', 'active' => 'dispatch.*', 'can' => 'dispatch.manage', 'rail' => true],
                    ['key' => 'myteam', 'label' => 'แอปภาคสนาม', 'short' => 'ภาคสนาม', 'icon' => 'phone', 'tone' => 'danger', 'route' => 'field.app', 'active' => 'field.*', 'can' => 'field.use', 'unless' => 'dispatch.manage', 'rail' => true],
                    ['key' => 'teams', 'label' => 'ทีมกู้ภัยและทรัพยากร', 'short' => 'ทีม', 'icon' => 'people', 'tone' => 'teal', 'route' => 'teams.index', 'active' => 'teams.*', 'can' => 'teams.manage', 'rail' => true],
                ],
            ],
            'prepare' => [
                'label' => 'เตรียมพร้อม',
                'items' => [
                    ['key' => 'risks', 'label' => 'จุดเสี่ยง', 'short' => 'จุดเสี่ยง', 'icon' => 'exclamation-triangle', 'tone' => 'warning', 'route' => 'risks.index', 'active' => 'risks.*', 'can' => 'risks.manage', 'rail' => true, 'badge' => 'risk_attention'],
                    ['key' => 'vulnerable', 'label' => 'ครัวเรือนกลุ่มเปราะบาง', 'short' => 'เปราะบาง', 'icon' => 'heart-pulse', 'tone' => 'danger', 'route' => 'vulnerable.index', 'active' => 'vulnerable.*', 'can' => 'vulnerable.manage', 'rail' => false],
                ],
            ],
            'water' => [
                'label' => 'ข้อมูลน้ำ',
                'items' => [
                    ['key' => 'alerts', 'label' => 'เตือนภัยล่วงหน้า', 'short' => 'เตือนภัย', 'icon' => 'bell', 'tone' => 'danger', 'route' => 'alerts.index', 'active' => 'alerts.*', 'can' => 'dashboard.view', 'rail' => true, 'badge' => 'alerts_unack'],
                    ['key' => 'reports', 'label' => 'รายงานระดับน้ำ', 'short' => 'รายงานน้ำ', 'icon' => 'droplet-half', 'tone' => 'blue', 'route' => 'reports.index', 'active' => 'reports.*', 'can' => 'reports.moderate', 'rail' => true, 'badge' => 'report_review'],
                    ['key' => 'stations', 'label' => 'สถานีวัดน้ำ', 'short' => 'สถานี', 'icon' => 'water', 'tone' => 'blue', 'route' => 'stations.index', 'active' => 'stations.*', 'can' => 'stations.manage', 'rail' => false],
                    ['key' => 'cctv', 'label' => 'กล้อง CCTV', 'short' => 'CCTV', 'icon' => 'camera-video', 'tone' => 'slate', 'route' => 'cctv.index', 'active' => 'cctv.*', 'can' => 'cctv.manage', 'rail' => false],
                ],
            ],
            'relief' => [
                'label' => 'ช่วยเหลือ',
                'items' => [
                    ['key' => 'shelters', 'label' => 'ศูนย์พักพิง', 'short' => 'ศูนย์พักพิง', 'icon' => 'house-heart', 'tone' => 'teal', 'route' => 'shelters.index', 'active' => 'shelters.*', 'can' => 'shelters.manage|evacuees.manage', 'rail' => true],
                    ['key' => 'evacuees', 'label' => 'ผู้อพยพ', 'short' => 'ผู้อพยพ', 'icon' => 'person-vcard', 'tone' => 'teal', 'route' => null, 'active' => 'evacuees.*', 'can' => 'evacuees.manage', 'rail' => false],
                    ['key' => 'supplies', 'label' => 'ของบริจาคและคลัง', 'short' => 'คลัง', 'icon' => 'box-seam', 'tone' => 'slate', 'route' => 'supplies.index', 'active' => 'supplies.*', 'can' => 'supplies.manage', 'rail' => false],
                ],
            ],
            'recovery' => [
                'label' => 'ฟื้นฟู',
                'items' => [
                    ['key' => 'recovery', 'label' => 'ฟื้นฟูและเยียวยา', 'short' => 'เยียวยา', 'icon' => 'hammer', 'tone' => 'teal', 'route' => 'recovery.index', 'active' => 'recovery.*', 'can' => 'recovery.manage|recovery.survey', 'rail' => false, 'badge' => 'recovery_pending'],
                ],
            ],
            'comms' => [
                'label' => 'สื่อสาร',
                'items' => [
                    ['key' => 'announcements', 'label' => 'ประกาศและแจ้งเตือน', 'short' => 'ประกาศ', 'icon' => 'megaphone', 'tone' => 'warning', 'route' => 'announcements.index', 'active' => 'announcements.*', 'can' => 'announcements.manage', 'rail' => false],
                    ['key' => 'exports', 'label' => 'รายงานและส่งออก', 'short' => 'รายงาน', 'icon' => 'bar-chart-line', 'tone' => 'purple', 'route' => 'exports.index', 'active' => 'exports.*', 'can' => 'exports.view', 'rail' => false],
                ],
            ],
            'system' => [
                'label' => 'ระบบ',
                'items' => [
                    ['key' => 'guide', 'label' => 'คู่มือการใช้งาน', 'short' => 'คู่มือ', 'icon' => 'book', 'tone' => 'teal', 'route' => 'admin.guide', 'active' => 'admin.guide', 'rail' => false],
                    ['key' => 'users', 'label' => 'ผู้ใช้และสิทธิ์', 'short' => 'ผู้ใช้', 'icon' => 'person-gear', 'tone' => 'slate', 'route' => 'admin.users.index', 'active' => 'admin.users.*', 'can' => 'users.manage', 'rail' => true, 'badge' => 'pending_users'],
                    ['key' => 'areas', 'label' => 'อำเภอและตำบล', 'short' => 'พื้นที่', 'icon' => 'map', 'tone' => 'teal', 'route' => 'admin.areas.index', 'active' => 'admin.areas.*', 'can' => 'areas.manage', 'rail' => false],
                    ['key' => 'provinces', 'label' => 'จังหวัดทั้งหมด', 'short' => 'จังหวัด', 'icon' => 'globe-asia-australia', 'tone' => 'blue', 'route' => 'admin.provinces.index', 'active' => 'admin.provinces.*', 'can' => 'provinces.manage', 'rail' => false],
                    ['key' => 'hosting', 'label' => 'ความพร้อมของโฮสต์', 'short' => 'โฮสต์', 'icon' => 'server', 'tone' => 'teal', 'route' => 'admin.hosting', 'active' => 'admin.hosting', 'role' => 'super-admin', 'rail' => false],
                    ['key' => 'audit', 'label' => 'ประวัติการใช้งาน', 'short' => 'ประวัติ', 'icon' => 'clock-history', 'tone' => 'slate', 'route' => 'admin.audit.index', 'active' => 'admin.audit.*', 'can' => 'audit.view', 'rail' => false],
                ],
            ],
        ];
    }

    /** เมนูที่ผู้ใช้ปัจจุบันเห็น (เปิดใช้แล้ว + มีสิทธิ์) จัดเป็นกลุ่ม */
    public static function visibleGroups(): array
    {
        $user = Auth::user();
        $out = [];
        foreach (static::groups() as $key => $group) {
            $items = array_values(array_filter($group['items'], fn ($i) => static::visible($i, $user)));
            if ($items) {
                $out[$key] = ['label' => $group['label'], 'items' => $items];
            }
        }

        return $out;
    }

    /** รายการบน rail ซ้าย (ไม่รวม ตั้งค่า ที่อยู่ล่างสุด) */
    public static function rail(): array
    {
        $items = [];
        foreach (static::visibleGroups() as $group) {
            foreach ($group['items'] as $item) {
                if ($item['rail'] ?? false) {
                    $items[] = $item;
                }
            }
        }

        return $items;
    }

    /** รายการทั้งหมดแบบแบน ใช้กับค้นหา Ctrl K */
    public static function flat(): array
    {
        $items = [];
        foreach (static::visibleGroups() as $group) {
            foreach ($group['items'] as $item) {
                $items[] = $item + ['group' => $group['label'], 'url' => route($item['route'])];
            }
        }
        if (Auth::user()?->can('settings.manage')) {
            $items[] = ['key' => 'settings', 'label' => 'ตั้งค่า', 'icon' => 'gear', 'group' => 'ระบบ', 'url' => route('admin.settings.index')];
        }

        return $items;
    }

    public static function isActive(array|string $item): bool
    {
        $pattern = is_array($item) ? ($item['active'] ?? $item['route']) : $item;

        return $pattern && Route::is($pattern);
    }

    public static function badge(array $item): int
    {
        return match ($item['badge'] ?? null) {
            'pending_users' => static::pendingUsersCount(),
            'triage_cases' => static::triageCount(),
            'risk_attention' => static::riskAttentionCount(),
            'report_review' => static::reportReviewCount(),
            'alerts_unack' => static::alertsUnackCount(),
            'recovery_pending' => static::recoveryPendingCount(),
            default => 0,
        };
    }

    protected static function visible(array $item, $user): bool
    {
        return $item['route'] !== null
            && Route::has($item['route'])
            && $user
            && (! isset($item['role']) || $user->hasRole($item['role']))
            && (! isset($item['can']) || collect(explode('|', $item['can']))->contains(fn ($p) => $user->can($p)))
            && (! isset($item['unless']) || ! $user->can($item['unless']));
    }

    /** เคสที่รอคัดกรองในจังหวัดปัจจุบัน */
    protected static function triageCount(): int
    {
        $province = current_province();
        if (! $province || ! Auth::user()?->can('cases.view')) {
            return 0;
        }

        return once(fn () => HelpRequest::inProvince($province->id)->whereIn('status', ['new', 'screening'])->count());
    }

    /** จุดเสี่ยงที่ประชาชนเสนอรอตรวจ + จุดที่กำลังเตือน */
    protected static function riskAttentionCount(): int
    {
        $province = current_province();
        if (! $province || ! Auth::user()?->can('risks.manage')) {
            return 0;
        }

        return once(fn () => RiskPoint::inProvince($province->id)
            ->where(fn ($q) => $q->where('review', 'pending')->orWhere(fn ($w) => $w->where('review', 'approved')->where('status', 'threatened')))
            ->count());
    }

    /** รายงานระดับน้ำที่รอตรวจ (รวมที่ถูกโหวตว่าไม่ถูกต้อง) */
    protected static function reportReviewCount(): int
    {
        $province = current_province();
        if (! $province || ! Auth::user()?->can('reports.moderate')) {
            return 0;
        }

        return once(fn () => WaterReport::inProvince($province->id)->where('status', 'pending')->count());
    }

    /** ประกาศเตือนภัยที่ยังไม่มีใครรับทราบ */
    protected static function alertsUnackCount(): int
    {
        $province = current_province();
        if (! $province || ! Auth::user()?->can('dashboard.view')) {
            return 0;
        }

        return once(fn () => Alert::inProvince($province->id)->active()->whereNull('acknowledged_at')->count());
    }

    /** คำร้องเยียวยาที่รอสำรวจ (ของฉันถ้าเป็นผู้สำรวจ) / รอพิจารณา */
    protected static function recoveryPendingCount(): int
    {
        $province = current_province();
        $user = Auth::user();
        if (! $province || ! $user) {
            return 0;
        }

        return once(function () use ($province, $user) {
            $q = DamageClaim::inProvince($province->id);
            if ($user->can('recovery.manage')) {
                return $q->whereIn('status', ['submitted', 'surveyed'])->count();
            }

            return $user->can('recovery.survey') ? $q->where('status', 'surveying')->where('surveyor_id', $user->id)->count() : 0;
        });
    }

    protected static function pendingUsersCount(): int
    {
        $user = Auth::user();
        if (! $user?->can('users.manage')) {
            return 0;
        }

        return once(fn () => User::query()
            ->where('status', 'pending')
            ->when(! $user->isSuperAdmin(), fn ($q) => $q->where('province_id', $user->province_id))
            ->count());
    }
}
