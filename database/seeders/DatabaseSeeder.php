<?php

namespace Database\Seeders;

use App\Models\EmergencyContact;
use App\Models\ExternalLink;
use App\Models\Province;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction() && (! preg_match('/^0[0-9]{8,9}$/D', (string) config('floodthai.superadmin.phone'))
            || strlen((string) config('floodthai.superadmin.password')) < 12
            || config('floodthai.superadmin.password') === 'floodthai@2026')) {
            throw new \RuntimeException('Production seeding requires a unique administrator phone and password. Use the installer or set SUPERADMIN_PHONE/SUPERADMIN_PASSWORD explicitly.');
        }
        $this->call([
            RolePermissionSeeder::class,
            ProvinceSeeder::class,
            DistrictSeeder::class,
        ]);

        // ลิงก์ข้อมูลน้ำภายนอก แสดงทุกจังหวัด
        $links = [
            ['ระดับน้ำล้นตลิ่ง', 'สถานีวัดน้ำในแม่น้ำและคลอง เทียบกับระดับตลิ่ง', 'https://bigdata-swoc.rid.go.th/pier/all', 'กรมชลประทาน'],
            ['ระดับน้ำทั้งประเทศ', 'สถานีโทรมาตรรายลุ่มน้ำ อัปเดตต่อเนื่อง', 'https://telemetry.dwr.go.th/home?layerMapType=BASIN', 'กรมทรัพยากรน้ำ'],
            ['คลังข้อมูลน้ำแห่งชาติ', 'ข้อมูลฝน ระดับน้ำ เขื่อน และภาพถ่ายดาวเทียม', 'https://www.thaiwater.net/', 'สสน.'],
        ];
        foreach ($links as $i => [$title, $desc, $url, $source]) {
            ExternalLink::updateOrCreate(
                ['province_id' => null, 'url' => $url],
                ['title' => $title, 'description' => $desc, 'source_name' => $source, 'sort' => $i + 1, 'is_active' => true],
            );
        }

        // เบอร์ฉุกเฉินตั้งต้นของจังหวัดนำร่อง (แก้ได้ในหน้า ตั้งค่า)
        foreach (['24', '20'] as $code) {
            $province = Province::where('code', $code)->first();
            if ($province && $province->emergencyContacts()->doesntExist()) {
                foreach (config('floodthai.national_contacts') as $i => $c) {
                    EmergencyContact::create(['province_id' => $province->id, 'label' => $c['label'], 'phone' => $c['phone'], 'sort' => $i + 1]);
                }
            }
        }

        // ผู้ดูแลระบบสูงสุด
        $admin = User::withTrashed()->firstOrNew(['phone' => config('floodthai.superadmin.phone')]);
        if (! $admin->exists) {
            $admin->fill([
                'name' => 'ผู้ดูแลระบบ',
                'password' => config('floodthai.superadmin.password'),
                'status' => 'active',
                'approved_at' => now(),
                'organization' => 'ศูนย์ช่วยเหลือน้ำท่วม',
            ])->save();
        }
        $admin->syncRoles(['super-admin']);
    }
}
