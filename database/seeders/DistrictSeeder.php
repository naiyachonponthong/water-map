<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\Province;
use Illuminate\Database\Seeder;

/**
 * รายชื่ออำเภอของจังหวัดนำร่อง (ขอบเขตแผนที่นำเข้าภายหลังด้วย GeoJSON)
 */
class DistrictSeeder extends Seeder
{
    public const DISTRICTS = [
        '24' => [
            '2401' => 'เมืองฉะเชิงเทรา', '2402' => 'บางคล้า', '2403' => 'บางน้ำเปรี้ยว', '2404' => 'บางปะกง',
            '2405' => 'บ้านโพธิ์', '2406' => 'พนมสารคาม', '2407' => 'ราชสาส์น', '2408' => 'สนามชัยเขต',
            '2409' => 'แปลงยาว', '2410' => 'ท่าตะเกียบ', '2411' => 'คลองเขื่อน',
        ],
        '20' => [
            '2001' => 'เมืองชลบุรี', '2002' => 'บ้านบึง', '2003' => 'หนองใหญ่', '2004' => 'บางละมุง',
            '2005' => 'พานทอง', '2006' => 'พนัสนิคม', '2007' => 'ศรีราชา', '2008' => 'เกาะสีชัง',
            '2009' => 'สัตหีบ', '2010' => 'บ่อทอง', '2011' => 'เกาะจันทร์',
        ],
    ];

    public function run(): void
    {
        foreach (self::DISTRICTS as $provinceCode => $districts) {
            $province = Province::where('code', $provinceCode)->firstOrFail();
            $sort = 0;
            foreach ($districts as $code => $name) {
                District::updateOrCreate(
                    ['code' => $code],
                    ['province_id' => $province->id, 'name_th' => $name, 'sort' => ++$sort],
                );
            }
        }
    }
}
