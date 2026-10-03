<?php

declare(strict_types=1);

namespace FloodThai\Installer;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Dotenv\Dotenv;
use Illuminate\Contracts\Console\Kernel;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

final class Runner
{
    public function __construct(private readonly Installer $installer) {}

    /** One short, repeatable step per POST. Never migrate:fresh or reset APP_KEY. */
    public function advance(): string
    {
        $lock = $this->installer->mutex();
        try {
            $state = $this->installer->read('state');
            if ($this->installer->locked() || ! $state) {
                throw new RuntimeException('ไม่มีการติดตั้งที่ดำเนินต่อได้');
            }
            if ($state['phase'] === 'configure') {
                $draft = $this->installer->read('draft')['env'] ?? '';
                if ($draft === '' || ! hash_equals($state['env_hash'], hash('sha256', $draft))) {
                    throw new RuntimeException('ไฟล์ตั้งค่าระหว่างติดตั้งไม่ครบ กรุณาให้ผู้ดูแลตรวจไฟล์ติดตั้ง');
                }
                $this->installer->atomic($this->installer->root.'/.env', $draft);
                $state['phase'] = 'migrate';
                $this->installer->write('state', $state);

                return 'migrate';
            }
            if (! is_file($this->installer->root.'/.env') || ! hash_equals($state['env_hash'], hash_file('sha256', $this->installer->root.'/.env'))) {
                throw new RuntimeException('ไฟล์ตั้งค่าถูกเปลี่ยนระหว่างติดตั้ง ไม่ดำเนินการกับฐานข้อมูลที่ไม่ตรงกับต้นฉบับ');
            }
            require_once $this->installer->root.'/vendor/autoload.php';
            // Hosting-level environment variables must not redirect migrations to
            // a different DB than the empty database the owner just verified.
            $envValues = Dotenv::parse(file_get_contents($this->installer->root.'/.env'));
            // Laravel treats only configured prefixes as absolute cache paths;
            // relative paths work on both Windows drive letters and Linux roots.
            $envValues['APP_CONFIG_CACHE'] = 'bootstrap/cache/config.php';
            $envValues['APP_SERVICES_CACHE'] = 'bootstrap/cache/services.php';
            $envValues['APP_PACKAGES_CACHE'] = 'bootstrap/cache/packages.php';
            $envValues['LARAVEL_STORAGE_PATH'] = $this->installer->root.'/storage';
            foreach ($envValues as $name => $value) {
                $_ENV[$name] = $_SERVER[$name] = $value;
            }
            $app = require $this->installer->root.'/bootstrap/app.php';
            $kernel = $app->make(Kernel::class);
            $kernel->bootstrap();
            $actualDb = $app['config']->get('database.connections.mysql');
            foreach (['host' => 'DB_HOST', 'port' => 'DB_PORT', 'database' => 'DB_DATABASE', 'username' => 'DB_USERNAME', 'password' => 'DB_PASSWORD'] as $field => $name) {
                if ((string) ($actualDb[$field] ?? '') !== $envValues[$name]) {
                    throw new RuntimeException('ค่าฐานข้อมูลที่โฮสต์โหลดไม่ตรงกับตัวติดตั้ง กรุณาให้ผู้ดูแลตรวจการตั้งค่าเซิร์ฟเวอร์');
                }
            }
            $output = new BufferedOutput;
            $phase = $state['phase'];
            if ($phase === 'migrate') {
                if (empty($state['migration_started'])) {
                    Installer::assertEmpty($app['db']->connection()->getPdo());
                    $state['migration_started'] = true;
                    $this->installer->write('state', $state);
                }
                if ($kernel->call('migrate', ['--force' => true], $output) !== 0) {
                    throw new RuntimeException('สร้างตารางไม่สำเร็จ');
                }
                $state['phase'] = 'seed';
            } elseif ($phase === 'seed') {
                $app['config']->set('floodthai.superadmin.phone', $state['admin_phone']);
                $app['config']->set('floodthai.superadmin.password', $state['admin_hash']);
                if ($kernel->call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true], $output) !== 0) {
                    throw new RuntimeException('สร้างข้อมูลตั้งต้นไม่สำเร็จ');
                }
                User::where('phone', $state['admin_phone'])->firstOrFail()->update(['name' => $state['admin_name']]);
                $state['phase'] = 'finish';
            } elseif ($phase === 'finish') {
                // Changing the state hash fails closed even if writing the marker fails.
                $env = file_get_contents($this->installer->root.'/.env');
                $env = str_replace('APP_INSTALLING="true"', 'APP_INSTALLING="false"', $env);
                $this->installer->atomic($this->installer->root.'/.env', $env);
                $this->installer->write('installed', ['at' => date(DATE_ATOM), 'mode' => $state['mode']]);
                $this->installer->write('state', ['phase' => 'done']);
                $this->installer->write('setup-key', ['revoked' => true]);
                $this->installer->write('draft', ['revoked' => true]);

                return 'done';
            } else {
                throw new RuntimeException('ขั้นตอนติดตั้งไม่ถูกต้อง');
            }
            $this->installer->write('state', $state);

            return $state['phase'];
        } catch (Throwable $e) {
            $details = $e->getMessage().' '.(isset($output) ? $output->fetch() : '');
            foreach ([$envValues['DB_PASSWORD'] ?? '', $state['admin_hash'] ?? ''] as $secret) {
                if ($secret !== '') {
                    $details = str_replace($secret, '[REDACTED]', $details);
                }
            }
            $this->installer->write('failure', ['at' => date(DATE_ATOM), 'phase' => $state['phase'] ?? 'unknown',
                'type' => get_class($e), 'details' => $details]);
            throw $e;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
