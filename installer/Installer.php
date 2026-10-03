<?php

declare(strict_types=1);

namespace FloodThai\Installer;

use PDO;
use RuntimeException;

/** Standalone: usable before Laravel has an APP_KEY or database connection. */
final class Installer
{
    public function __construct(public readonly string $root) {}

    public function directory(): string
    {
        return $this->root.'/storage/app/installer';
    }

    public function read(string $name): array
    {
        $path = $this->directory().'/'.$name.'.php';
        if (! is_file($path)) {
            return [];
        }

        $data = json_decode(explode("\n", file_get_contents($path), 2)[1] ?? '', true);

        return is_array($data) ? $data : [];
    }

    public function write(string $name, array $data): void
    {
        if (! is_dir($this->directory()) && ! mkdir($this->directory(), 0700, true) && ! is_dir($this->directory())) {
            throw new RuntimeException('สร้างพื้นที่ติดตั้งไม่ได้ กรุณาตรวจสิทธิ์ storage');
        }
        $this->atomic($this->directory().'/'.$name.'.php', "<?php http_response_code(404); exit; ?>\n".json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function locked(): bool
    {
        if ($this->read('installed') || is_file($this->root.'/bootstrap/cache/config.php')) {
            return true;
        }
        $env = $this->root.'/.env';
        if (! is_file($env)) {
            return false;
        }
        $content = file_get_contents($env);
        // Only a matching in-progress state may resume an installer-written .env.
        $state = $this->read('state');
        if (! empty($state['env_hash']) && hash_equals($state['env_hash'], hash('sha256', $content))) {
            return false;
        }

        // A pre-existing configured installation is NEVER an installer target.
        return preg_match('/^APP_KEY[ \t]*=[ \t]*["\']?[^\s"\']+/m', $content) === 1;
    }

    public function needsSetup(): bool
    {
        return ! $this->locked();
    }

    public function checks(array $server): array
    {
        $checks = [];
        $add = function (string $label, bool $ok, string $help, bool $required = true) use (&$checks) {
            $checks[] = compact('label', 'ok', 'help', 'required');
        };
        $add('PHP 8.3 ขึ้นไป', version_compare(PHP_VERSION, '8.3', '>='), 'เลือกเวอร์ชัน PHP ในแผงควบคุมโฮสต์');
        foreach (['ctype', 'curl', 'dom', 'fileinfo', 'filter', 'hash', 'mbstring', 'openssl', 'pcre', 'PDO', 'pdo_mysql', 'session', 'tokenizer', 'xml'] as $extension) {
            $add('ส่วนขยาย '.$extension, extension_loaded($extension), 'เปิดส่วนขยาย PHP นี้ หรือติดต่อผู้ให้บริการ');
        }
        $add('ไฟล์ระบบพร้อม', is_file($this->root.'/vendor/autoload.php'), 'ใช้ ZIP รุ่นพร้อมติดตั้ง หรือให้ผู้ดูแลติดตั้ง Composer dependencies');
        $publicRoot = realpath($this->root.'/public');
        $add('โฟลเดอร์เว็บไซต์เป็น public', $publicRoot !== false && realpath($server['DOCUMENT_ROOT'] ?? '') === $publicRoot, 'ตั้ง Document Root ของโดเมน/ซับโดเมนให้ชี้ไปที่ floodthai/public ห้ามเปิดทั้งโฟลเดอร์ระบบ');
        $add('HTTPS', self::secure($server) || self::local($server), 'เปิด SSL ก่อนกรอกข้อมูลสำคัญ หากอยู่หลัง proxy ให้ผู้ดูแลตั้ง HTTPS ให้ PHP อย่างถูกต้อง');
        foreach (['storage', 'storage/app', 'storage/framework/cache', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache'] as $dir) {
            $add('เขียน '.$dir.' ได้', is_dir($this->root.'/'.$dir) && is_writable($this->root.'/'.$dir), 'ให้ PHP เขียนโฟลเดอร์นี้ได้ ไม่ต้องตั้งสิทธิ์ 777 ทั้งระบบ');
        }
        $add('เขียนไฟล์ตั้งค่าได้', is_writable($this->root) && (! is_file($this->root.'/.env') || is_writable($this->root.'/.env')), 'ให้เจ้าของไฟล์/PHP เขียน .env ได้ระหว่างติดตั้ง แล้วลดสิทธิ์หลังติดตั้ง');
        $add('ไม่มีแคชตั้งค่าเก่า', ! is_file($this->root.'/bootstrap/cache/config.php'), 'ชุดติดตั้งใหม่ต้องไม่มี bootstrap/cache/config.php จากเครื่องเดิม');
        $memory = ini_get('memory_limit');
        $add('หน่วยความจำอย่างน้อย 128 MB', $memory === '-1' || self::bytes($memory) >= 128 * 1024 * 1024, 'เพิ่ม memory_limit เป็น 128M หรือมากกว่า');
        $seconds = (int) ini_get('max_execution_time');
        $add('เวลาติดตั้งอย่างน้อย 30 วินาที', $seconds === 0 || $seconds >= 30, 'เพิ่ม max_execution_time หรือให้ผู้ดูแลใช้การติดตั้งผ่าน terminal');
        $add('สร้างลิงก์พื้นที่รูปได้', function_exists('symlink'), 'หากไม่มีฟังก์ชันนี้ ระบบมีทางเลือกส่งรูปผ่านแอป', false);

        return $checks;
    }

    public static function ready(array $checks): bool
    {
        return ! array_filter($checks, fn ($c) => $c['required'] && ! $c['ok']);
    }

    public static function secure(array $server): bool
    {
        // Never trust an arbitrary X-Forwarded-Proto header during setup.
        return ! empty($server['HTTPS']) && strtolower((string) $server['HTTPS']) !== 'off';
    }

    public static function local(array $server): bool
    {
        return PHP_SAPI === 'cli-server' && in_array($server['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
            && in_array(strtolower(explode(':', trim($server['HTTP_HOST'] ?? '', '[]'))[0]), ['localhost', '127.0.0.1'], true);
    }

    private static function bytes(string $value): int
    {
        return (int) $value * match (strtolower(substr($value, -1))) {
            'g' => 1073741824, 'm' => 1048576, 'k' => 1024, default => 1
        };
    }

    public function token(): string
    {
        $lock = $this->mutex();
        try {
            $data = $this->read('setup-key');
            if (! $data) {
                $data = ['token' => bin2hex(random_bytes(32))];
                $this->write('setup-key', $data);
            }

            return $data['token'];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function authenticate(string $token): bool
    {
        $lock = $this->mutex();
        try {
            $attempts = array_values(array_filter($this->read('attempts')['times'] ?? [], fn ($t) => $t > time() - 60));
            if (count($attempts) >= 10) {
                throw new RuntimeException('ลองรหัสมากเกินไป กรุณารอ 1 นาที');
            }
            $attempts[] = time();
            $this->write('attempts', ['times' => $attempts]);
            $stored = $this->read('setup-key')['token'] ?? '';

            return strlen($stored) === 64 && hash_equals($stored, trim($token));
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** Exclusive file lock serializes setup, writes and migration requests. */
    public function mutex()
    {
        if (! is_dir($this->directory())) {
            $this->write('created', ['at' => date(DATE_ATOM)]);
        }
        $lock = fopen($this->directory().'/mutex.php', 'c+');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('มีการติดตั้งกำลังทำงานอยู่ กรุณารอสักครู่');
        }

        return $lock;
    }

    public static function validate(array $input): array
    {
        $values = [];
        foreach (['app_name', 'app_url', 'db_host', 'db_port', 'db_name', 'db_user', 'db_password', 'admin_name', 'admin_phone', 'admin_password', 'admin_confirmation', 'mode'] as $key) {
            $values[$key] = is_string($input[$key] ?? null) ? $input[$key] : '';
            if (strlen($values[$key]) > 512 || preg_match('/[\x00-\x1f\x7f]/', $values[$key])) {
                throw new RuntimeException('ข้อมูลยาวเกินไปหรือมีอักขระที่ไม่อนุญาต');
            }
        }
        $values['app_url'] = rtrim(trim($values['app_url']), '/');
        $url = parse_url($values['app_url']);
        if (! filter_var($values['app_url'], FILTER_VALIDATE_URL) || ! in_array($url['scheme'] ?? '', ['https', 'http'], true)
            || isset($url['user'], $url['pass']) || isset($url['user']) || isset($url['query']) || isset($url['fragment'])
            || ! empty($url['path'])) {
            throw new RuntimeException('กรอก URL ของโดเมน เช่น https://flood.example.org โดยไม่ใส่ path');
        }
        if ($url['scheme'] !== 'https' && ! in_array($url['host'], ['localhost', '127.0.0.1', '[::1]'], true)) {
            throw new RuntimeException('เว็บไซต์จริงต้องใช้ HTTPS');
        }
        if (! preg_match('/^[a-zA-Z0-9._:\[\]-]{1,253}$/D', $values['db_host'])
            || ! ctype_digit($values['db_port']) || (int) $values['db_port'] < 1 || (int) $values['db_port'] > 65535
            || ! preg_match('/^[a-zA-Z0-9_$-]{1,64}$/D', $values['db_name']) || trim($values['db_user']) === '') {
            throw new RuntimeException('ตรวจชื่อฐานข้อมูล ผู้ใช้ฐานข้อมูล โฮสต์ และพอร์ตให้ถูกต้อง');
        }
        if (! preg_match('/^0[0-9]{8,9}$/D', $values['admin_phone'])) {
            throw new RuntimeException('กรอกเบอร์ผู้ดูแลเป็นตัวเลข 9–10 หลัก เริ่มด้วย 0');
        }
        if (strlen($values['admin_password']) < 12 || strlen($values['admin_password']) > 72
            || ! preg_match('/[a-zA-Z]/', $values['admin_password']) || ! preg_match('/\d/', $values['admin_password'])
            || $values['admin_password'] !== $values['admin_confirmation'] || $values['admin_password'] === 'floodthai@2026') {
            throw new RuntimeException('ตั้งรหัสผู้ดูแลใหม่ 12–72 ตัว มีตัวอักษรและตัวเลข และยืนยันให้ตรงกัน');
        }
        if (trim($values['app_name']) === '' || trim($values['admin_name']) === '' || ! in_array($values['mode'], ['shared', 'vps'], true)) {
            throw new RuntimeException('กรอกชื่อระบบ ชื่อผู้ดูแล และรูปแบบโฮสต์');
        }

        return $values;
    }

    public function connect(array $v): PDO
    {
        try {
            return new PDO('mysql:host='.$v['db_host'].';port='.$v['db_port'].';dbname='.$v['db_name'].';charset=utf8mb4', $v['db_user'], $v['db_password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
        } catch (\Throwable $e) {
            // Do not expose a DSN, password, stack trace or provider hostname.
            throw new RuntimeException('เชื่อมต่อฐานข้อมูลไม่ได้ ตรวจข้อมูลและสิทธิ์ผู้ใช้ฐานข้อมูล หรือสอบถามโฮสต์');
        }
    }

    public static function assertEmpty(PDO $db): void
    {
        if ($db->query('SHOW TABLES')->fetchColumn() !== false) {
            throw new RuntimeException('ฐานข้อมูลนี้มีตารางอยู่แล้ว กรุณาสร้างฐานข้อมูลว่างแยกต่างหาก ตัวติดตั้งจะไม่ล้างข้อมูลเดิม');
        }
    }

    /** Quote for phpdotenv, including literal dollar signs in real DB passwords. */
    public static function quote(string $value): string
    {
        if (preg_match('/[\x00-\x1f\x7f]/', $value)) {
            throw new RuntimeException('ไม่อนุญาตอักขระควบคุมในค่าตั้งค่า');
        }

        return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
    }

    public static function environment(array $v, string $key): string
    {
        $values = [
            'APP_NAME' => $v['app_name'], 'APP_ENV' => 'production', 'APP_KEY' => $key,
            'APP_DEBUG' => 'false', 'APP_INSTALLING' => 'true', 'APP_URL' => $v['app_url'],
            'APP_TIMEZONE' => 'Asia/Bangkok', 'APP_LOCALE' => 'th', 'APP_FALLBACK_LOCALE' => 'en',
            'LOG_CHANNEL' => 'stack', 'LOG_LEVEL' => 'warning', 'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $v['db_host'], 'DB_PORT' => $v['db_port'], 'DB_DATABASE' => $v['db_name'],
            'DB_URL' => '', 'DB_SOCKET' => '',
            'DB_USERNAME' => $v['db_user'], 'DB_PASSWORD' => $v['db_password'],
            'SESSION_DRIVER' => 'database', 'SESSION_SECURE_COOKIE' => str_starts_with($v['app_url'], 'https://') ? 'true' : 'false',
            'SESSION_LIFETIME' => '120', 'CACHE_STORE' => 'database', 'QUEUE_CONNECTION' => 'database',
            'FILESYSTEM_DISK' => 'public', 'PUBLIC_UPLOADS_THROUGH_APP' => 'true',
            'BROADCAST_CONNECTION' => 'null', 'FLOOD_HOSTING_MODE' => $v['mode'],
            'TRUSTED_PROXIES' => '127.0.0.1', 'PII_RETENTION_DAYS' => '180',
            'REQUIRE_2FA_ROLES' => 'super-admin,province-admin,dispatcher',
            // Admin password exists only as a one-way hash in private setup state.
            'SUPERADMIN_PHONE' => '', 'SUPERADMIN_PASSWORD' => '',
        ];

        return implode("\n", array_map(fn ($k, $v) => $k.'='.self::quote($v), array_keys($values), $values))."\n";
    }

    public function prepare(array $input): void
    {
        $lock = $this->mutex();
        try {
            if ($this->locked() || $this->read('state')) {
                throw new RuntimeException('ระบบนี้ตั้งค่าแล้ว ไม่อนุญาตเขียนทับข้อมูลติดตั้ง');
            }
            $v = self::validate($input);
            self::assertEmpty($this->connect($v));
            $env = self::environment($v, 'base64:'.base64_encode(random_bytes(32)));
            // Persist recovery ownership BEFORE .env; no raw administrator password.
            $this->write('draft', ['env' => $env]);
            $state = ['phase' => 'configure', 'env_hash' => hash('sha256', $env),
                'admin_name' => $v['admin_name'], 'admin_phone' => $v['admin_phone'],
                'admin_hash' => password_hash($v['admin_password'], PASSWORD_BCRYPT), 'mode' => $v['mode']];
            $this->write('state', $state);
            $this->atomic($this->root.'/.env', $env);
            $state['phase'] = 'migrate';
            $this->write('state', $state);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function atomic(string $path, string $content): void
    {
        $temp = $path.'.'.bin2hex(random_bytes(6)).'.tmp';
        if (file_put_contents($temp, $content, LOCK_EX) !== strlen($content)) {
            throw new RuntimeException('บันทึกไฟล์ตั้งค่าไม่ได้ ตรวจพื้นที่ว่างและสิทธิ์เขียนไฟล์');
        }
        chmod($temp, 0600);
        if (! rename($temp, $path)) {
            throw new RuntimeException('เปลี่ยนไฟล์ตั้งค่าไม่ได้ กรุณาตรวจสิทธิ์ไฟล์');
        }
    }
}
