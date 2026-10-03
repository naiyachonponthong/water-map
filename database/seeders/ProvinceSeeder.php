<?php

namespace Database\Seeders;

use App\Models\Province;
use Illuminate\Database\Seeder;

class ProvinceSeeder extends Seeder
{
    /**
     * 77 จังหวัด [รหัส, slug, ชื่อไทย, ชื่ออังกฤษ]
     * พิกัดกลางจังหวัดใส่ไว้เฉพาะจังหวัดนำร่อง ที่เหลือตั้งได้ในหน้า จังหวัดทั้งหมด
     * หรือคำนวณอัตโนมัติจากขอบเขตอำเภอเมื่อนำเข้า GeoJSON
     */
    public const PROVINCES = [
        ['10', 'bangkok', 'กรุงเทพมหานคร', 'Bangkok'],
        ['11', 'samut-prakan', 'สมุทรปราการ', 'Samut Prakan'],
        ['12', 'nonthaburi', 'นนทบุรี', 'Nonthaburi'],
        ['13', 'pathum-thani', 'ปทุมธานี', 'Pathum Thani'],
        ['14', 'ayutthaya', 'พระนครศรีอยุธยา', 'Phra Nakhon Si Ayutthaya'],
        ['15', 'ang-thong', 'อ่างทอง', 'Ang Thong'],
        ['16', 'lopburi', 'ลพบุรี', 'Lopburi'],
        ['17', 'sing-buri', 'สิงห์บุรี', 'Sing Buri'],
        ['18', 'chai-nat', 'ชัยนาท', 'Chai Nat'],
        ['19', 'saraburi', 'สระบุรี', 'Saraburi'],
        ['20', 'chonburi', 'ชลบุรี', 'Chonburi'],
        ['21', 'rayong', 'ระยอง', 'Rayong'],
        ['22', 'chanthaburi', 'จันทบุรี', 'Chanthaburi'],
        ['23', 'trat', 'ตราด', 'Trat'],
        ['24', 'chachoengsao', 'ฉะเชิงเทรา', 'Chachoengsao'],
        ['25', 'prachin-buri', 'ปราจีนบุรี', 'Prachin Buri'],
        ['26', 'nakhon-nayok', 'นครนายก', 'Nakhon Nayok'],
        ['27', 'sa-kaeo', 'สระแก้ว', 'Sa Kaeo'],
        ['30', 'nakhon-ratchasima', 'นครราชสีมา', 'Nakhon Ratchasima'],
        ['31', 'buriram', 'บุรีรัมย์', 'Buriram'],
        ['32', 'surin', 'สุรินทร์', 'Surin'],
        ['33', 'sisaket', 'ศรีสะเกษ', 'Sisaket'],
        ['34', 'ubon-ratchathani', 'อุบลราชธานี', 'Ubon Ratchathani'],
        ['35', 'yasothon', 'ยโสธร', 'Yasothon'],
        ['36', 'chaiyaphum', 'ชัยภูมิ', 'Chaiyaphum'],
        ['37', 'amnat-charoen', 'อำนาจเจริญ', 'Amnat Charoen'],
        ['38', 'bueng-kan', 'บึงกาฬ', 'Bueng Kan'],
        ['39', 'nong-bua-lamphu', 'หนองบัวลำภู', 'Nong Bua Lamphu'],
        ['40', 'khon-kaen', 'ขอนแก่น', 'Khon Kaen'],
        ['41', 'udon-thani', 'อุดรธานี', 'Udon Thani'],
        ['42', 'loei', 'เลย', 'Loei'],
        ['43', 'nong-khai', 'หนองคาย', 'Nong Khai'],
        ['44', 'maha-sarakham', 'มหาสารคาม', 'Maha Sarakham'],
        ['45', 'roi-et', 'ร้อยเอ็ด', 'Roi Et'],
        ['46', 'kalasin', 'กาฬสินธุ์', 'Kalasin'],
        ['47', 'sakon-nakhon', 'สกลนคร', 'Sakon Nakhon'],
        ['48', 'nakhon-phanom', 'นครพนม', 'Nakhon Phanom'],
        ['49', 'mukdahan', 'มุกดาหาร', 'Mukdahan'],
        ['50', 'chiang-mai', 'เชียงใหม่', 'Chiang Mai'],
        ['51', 'lamphun', 'ลำพูน', 'Lamphun'],
        ['52', 'lampang', 'ลำปาง', 'Lampang'],
        ['53', 'uttaradit', 'อุตรดิตถ์', 'Uttaradit'],
        ['54', 'phrae', 'แพร่', 'Phrae'],
        ['55', 'nan', 'น่าน', 'Nan'],
        ['56', 'phayao', 'พะเยา', 'Phayao'],
        ['57', 'chiang-rai', 'เชียงราย', 'Chiang Rai'],
        ['58', 'mae-hong-son', 'แม่ฮ่องสอน', 'Mae Hong Son'],
        ['60', 'nakhon-sawan', 'นครสวรรค์', 'Nakhon Sawan'],
        ['61', 'uthai-thani', 'อุทัยธานี', 'Uthai Thani'],
        ['62', 'kamphaeng-phet', 'กำแพงเพชร', 'Kamphaeng Phet'],
        ['63', 'tak', 'ตาก', 'Tak'],
        ['64', 'sukhothai', 'สุโขทัย', 'Sukhothai'],
        ['65', 'phitsanulok', 'พิษณุโลก', 'Phitsanulok'],
        ['66', 'phichit', 'พิจิตร', 'Phichit'],
        ['67', 'phetchabun', 'เพชรบูรณ์', 'Phetchabun'],
        ['70', 'ratchaburi', 'ราชบุรี', 'Ratchaburi'],
        ['71', 'kanchanaburi', 'กาญจนบุรี', 'Kanchanaburi'],
        ['72', 'suphan-buri', 'สุพรรณบุรี', 'Suphan Buri'],
        ['73', 'nakhon-pathom', 'นครปฐม', 'Nakhon Pathom'],
        ['74', 'samut-sakhon', 'สมุทรสาคร', 'Samut Sakhon'],
        ['75', 'samut-songkhram', 'สมุทรสงคราม', 'Samut Songkhram'],
        ['76', 'phetchaburi', 'เพชรบุรี', 'Phetchaburi'],
        ['77', 'prachuap-khiri-khan', 'ประจวบคีรีขันธ์', 'Prachuap Khiri Khan'],
        ['80', 'nakhon-si-thammarat', 'นครศรีธรรมราช', 'Nakhon Si Thammarat'],
        ['81', 'krabi', 'กระบี่', 'Krabi'],
        ['82', 'phang-nga', 'พังงา', 'Phang Nga'],
        ['83', 'phuket', 'ภูเก็ต', 'Phuket'],
        ['84', 'surat-thani', 'สุราษฎร์ธานี', 'Surat Thani'],
        ['85', 'ranong', 'ระนอง', 'Ranong'],
        ['86', 'chumphon', 'ชุมพร', 'Chumphon'],
        ['90', 'songkhla', 'สงขลา', 'Songkhla'],
        ['91', 'satun', 'สตูล', 'Satun'],
        ['92', 'trang', 'ตรัง', 'Trang'],
        ['93', 'phatthalung', 'พัทลุง', 'Phatthalung'],
        ['94', 'pattani', 'ปัตตานี', 'Pattani'],
        ['95', 'yala', 'ยะลา', 'Yala'],
        ['96', 'narathiwat', 'นราธิวาส', 'Narathiwat'],
    ];

    /** จังหวัดนำร่อง: พิกัดศาลากลางโดยประมาณ */
    public const PILOT_CENTERS = [
        '24' => [13.6904, 101.0780, 10],
        '20' => [13.3611, 100.9847, 10],
    ];

    public static function regionOf(string $code): string
    {
        $n = (int) $code;

        return match (true) {
            $n < 20 => 'ภาคกลาง',
            $n < 30 => 'ภาคตะวันออก',
            $n < 50 => 'ภาคตะวันออกเฉียงเหนือ',
            $n < 70 => 'ภาคเหนือ',
            $n < 80 => 'ภาคกลางและตะวันตก',
            default => 'ภาคใต้',
        };
    }

    public function run(): void
    {
        foreach (self::PROVINCES as [$code, $slug, $th, $en]) {
            $center = self::PILOT_CENTERS[$code] ?? null;
            $province = Province::firstOrNew(['code' => $code]);
            $province->fill([
                'slug' => $slug,
                'name_th' => $th,
                'name_en' => $en,
                'region' => self::regionOf($code),
            ]);
            if ($center && ! $province->hasCenter()) {
                [$province->center_lat, $province->center_lng, $province->default_zoom] = $center;
            }
            $province->save();
        }
    }
}
