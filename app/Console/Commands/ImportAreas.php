<?php

namespace App\Console\Commands;

use App\Models\Province;
use App\Support\AreaImporter;
use Illuminate\Console\Command;

/**
 * นำเข้าขอบเขตอำเภอ/ตำบลจากไฟล์ GeoJSON ขนาดใหญ่ (ทั้งประเทศ) ผ่าน command line
 *
 * php artisan flood:import-areas storage/app/tha_adm2.geojson --level=district --province=chachoengsao
 * php artisan flood:import-areas storage/app/tha_adm3.geojson --level=subdistrict --province=all
 */
class ImportAreas extends Command
{
    protected $signature = 'flood:import-areas {file} {--level=district : district หรือ subdistrict} {--province=all : slug จังหวัด หรือ all} {--boundary-only : อัปเดตเฉพาะพื้นที่ที่มีอยู่แล้ว}';

    protected $description = 'นำเข้าขอบเขตอำเภอ/ตำบล จากไฟล์ GeoJSON';

    public function handle(): int
    {
        $path = $this->argument('file');
        if (! is_file($path)) {
            $this->error("ไม่พบไฟล์ $path");

            return self::FAILURE;
        }

        $level = $this->option('level');
        if (! in_array($level, ['district', 'subdistrict'], true)) {
            $this->error('--level ต้องเป็น district หรือ subdistrict');

            return self::FAILURE;
        }

        ini_set('memory_limit', '2G');
        $json = json_decode(file_get_contents($path), true);
        if (! is_array($json)) {
            $this->error('อ่าน GeoJSON ไม่ได้');

            return self::FAILURE;
        }

        $provinces = $this->option('province') === 'all'
            ? Province::orderBy('code')->get()
            : Province::where('slug', $this->option('province'))->get();

        if ($provinces->isEmpty()) {
            $this->error('ไม่พบจังหวัด');

            return self::FAILURE;
        }

        foreach ($provinces as $province) {
            $report = (new AreaImporter($province))->import($json, $level, (bool) $this->option('boundary-only'));
            if ($report['created'] + $report['updated'] === 0) {
                continue;
            }
            $this->info(sprintf('%-20s เพิ่ม %d แก้ไข %d ข้าม %d', $province->name_th, $report['created'], $report['updated'], $report['skipped']));
            foreach (array_slice($report['errors'], 0, 5) as $err) {
                $this->warn('  '.$err);
            }
        }

        return self::SUCCESS;
    }
}
