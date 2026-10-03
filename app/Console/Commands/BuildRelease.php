<?php

namespace App\Console\Commands;

use App\Support\Attribution;
use App\Support\ReleasePackage;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class BuildRelease extends Command
{
    protected $signature = 'flood:package {--composer= : Path to composer.phar; otherwise use composer on PATH}';

    protected $description = 'สร้าง ZIP พร้อมติดตั้งแบบไม่มีข้อมูลส่วนตัวและ dependencies สำหรับพัฒนา';

    public function handle(): int
    {
        try {
            Attribution::verifyRelease(base_path());
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        if (! class_exists(\ZipArchive::class)) {
            $this->error('เปิดส่วนขยาย zip บนเครื่องที่สร้างชุดแจกจ่ายก่อน');

            return self::FAILURE;
        }
        $id = date('Ymd-His').'-'.bin2hex(random_bytes(4));
        $stage = base_path('output/release-builds/'.$id);
        $output = base_path('output/releases');
        mkdir($stage, 0755, true);
        if (! is_dir($output)) {
            mkdir($output, 0755, true);
        }
        ReleasePackage::copySources(base_path(), $stage);
        $composer = $this->option('composer');
        if ($composer && ! is_file($composer)) {
            $this->error('ไม่พบ composer.phar ตาม path ที่ระบุ');

            return self::FAILURE;
        }
        $command = $composer ? [PHP_BINARY, $composer] : ['composer'];
        $composerHome = base_path('output/composer-home');
        if (! is_dir($composerHome)) {
            mkdir($composerHome, 0700, true);
        }
        $process = new Process(array_merge($command, ['install', '--no-dev', '--prefer-dist', '--no-interaction', '--no-plugins', '--no-scripts', '--optimize-autoloader']),
            $stage, ['APP_ENV' => 'production', 'COMPOSER_HOME' => $composerHome, 'COMPOSER_CACHE_DIR' => base_path('output/composer-cache')], null, 600);
        $this->info('กำลังเตรียม dependencies เฉพาะใช้งานจริงในโฟลเดอร์แยก ไม่เปลี่ยนระบบปัจจุบัน');
        $process->run(fn ($type, $buffer) => $this->output->write($buffer));
        if (! $process->isSuccessful()) {
            $this->error('ยังไม่สร้าง ZIP เพราะ dependencies ไม่ครบ ตรวจเครือข่าย/Composer แล้วสร้างใหม่');

            return self::FAILURE;
        }
        $destination = $output.'/floodthai-'.$id.'.zip';
        $count = ReleasePackage::archive($stage, $destination);
        file_put_contents($destination.'.sha256', hash_file('sha256', $destination).'  '.basename($destination)."\n");
        $this->info("พร้อมแจกจ่าย: {$destination} ({$count} files)");
        $this->comment('อ่าน START-HERE.md ใน ZIP; ตัวติดตั้งสร้างรหัสเฉพาะโฮสต์เมื่อเปิดครั้งแรก');

        return self::SUCCESS;
    }
}
