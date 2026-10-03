<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /** สิทธิ์ทั้งระบบ (รวมของเฟสถัดไป เพื่อกำหนดบทบาทได้ครั้งเดียว) */
    public const PERMISSIONS = [
        'ศูนย์สั่งการ' => [
            'dashboard.view' => 'ดูแดชบอร์ดศูนย์สั่งการ',
            'cases.view' => 'ดูเคสขอความช่วยเหลือ',
            'cases.manage' => 'รับ คัดกรอง แก้ไขเคส',
            'dispatch.manage' => 'มอบหมายทีม',
            'teams.manage' => 'จัดการทีมกู้ภัยและยานพาหนะ',
            'field.use' => 'ใช้แอปภาคสนาม',
        ],
        'เตรียมพร้อม' => [
            'risks.manage' => 'จัดการจุดเสี่ยง',
            'vulnerable.manage' => 'จัดการครัวเรือนกลุ่มเปราะบาง',
        ],
        'ข้อมูลน้ำ' => [
            'reports.moderate' => 'ตรวจรายงานระดับน้ำ',
            'stations.manage' => 'จัดการสถานีวัดน้ำ',
            'cctv.manage' => 'จัดการกล้อง CCTV',
        ],
        'ช่วยเหลือ' => [
            'shelters.manage' => 'จัดการศูนย์พักพิง',
            'evacuees.manage' => 'ลงทะเบียนผู้อพยพ',
            'supplies.manage' => 'จัดการของบริจาคและคลัง',
        ],
        'ฟื้นฟู' => [
            'recovery.manage' => 'จัดการคำร้องเยียวยา อนุมัติ จ่าย',
            'recovery.survey' => 'สำรวจความเสียหาย',
        ],
        'สื่อสาร' => [
            'announcements.manage' => 'ประกาศและแจ้งเตือน',
            'exports.view' => 'ดูรายงานและส่งออก',
        ],
        'ระบบ' => [
            'users.manage' => 'จัดการผู้ใช้และสิทธิ์',
            'areas.manage' => 'จัดการอำเภอและตำบล',
            'settings.manage' => 'ตั้งค่าจังหวัด',
            'audit.view' => 'ดูประวัติการใช้งาน',
            'provinces.manage' => 'จัดการจังหวัดทั้งหมด (ผู้ดูแลระบบสูงสุด)',
        ],
    ];

    public const ROLE_PERMISSIONS = [
        'province-admin' => '*', // ทุกสิทธิ์ ยกเว้น provinces.manage
        'dispatcher' => [
            'dashboard.view', 'cases.view', 'cases.manage', 'dispatch.manage', 'teams.manage',
            'risks.manage', 'vulnerable.manage', 'reports.moderate', 'shelters.manage',
            'announcements.manage', 'exports.view', 'recovery.survey',
        ],
        'moderator' => ['dashboard.view', 'cases.view', 'reports.moderate', 'risks.manage'],
        'team-leader' => ['field.use', 'cases.view', 'recovery.survey'],
        'team-member' => ['field.use'],
        'shelter-staff' => ['shelters.manage', 'evacuees.manage', 'supplies.manage'],
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $group => $perms) {
            foreach ($perms as $name => $label) {
                Permission::updateOrCreate(['name' => $name, 'guard_name' => 'web'], ['label' => $label, 'group' => $group]);
            }
        }

        foreach (config('floodthai.roles') as $name => $label) {
            Role::updateOrCreate(['name' => $name, 'guard_name' => 'web'], ['label' => $label]);
        }

        $all = Permission::pluck('name');
        foreach (self::ROLE_PERMISSIONS as $role => $perms) {
            $list = $perms === '*' ? $all->reject(fn ($p) => $p === 'provinces.manage')->values() : $perms;
            Role::findByName($role, 'web')->syncPermissions($list);
        }

        // super-admin ผ่านทุกสิทธิ์ด้วย Gate::before แต่ผูกไว้ด้วยเพื่อแสดงผลในหน้าจัดการ
        Role::findByName('super-admin', 'web')->syncPermissions($all);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
