<?php

// Integration test ONLY: creates a random isolated database/user and installs an
// extracted release copy. Never points migrations at the working application's DB.
require __DIR__.'/../vendor/autoload.php';

use Symfony\Component\Process\Process;

$zipPath = $argv[1] ?? '';
if (! is_file($zipPath)) {
    exit("Usage: php tools/smoke-installer.php /absolute/path/release.zip\n");
}
$root = dirname(__DIR__);
$id = bin2hex(random_bytes(4));
$testDatabase = 'floodthai_install_'.$id;
$testUser = 'fti_'.$id;
$dir = $root.'/output/installer-smoke/'.$id;
mkdir($dir, 0700, true);
$zip = new ZipArchive;
$zip->open($zipPath);
for ($i = 0; $i < $zip->numFiles; $i++) {
    $entry = $zip->getNameIndex($i);
    if (! str_starts_with($entry, 'floodthai/') || str_contains($entry, '..') || str_contains($entry, '\\')) {
        throw new RuntimeException('Unsafe release archive entry');
    }
    if (in_array($entry, ['floodthai/.env', 'floodthai/bootstrap/cache/config.php'], true) || str_starts_with($entry, 'floodthai/storage/app/installer/')) {
        throw new RuntimeException('Private installation data included in release');
    }
}
$zip->extractTo($dir);
$zip->close();
$copy = $dir.'/floodthai';
$dbPassword = 'Smoke#"${APP_NAME}\\'.bin2hex(random_bytes(12));
$adminPassword = 'Install!'.bin2hex(random_bytes(12));
$testHost = '127.0.0.1';
$testPort = (int) (getenv('INSTALL_TEST_MYSQL_PORT') ?: 3306);
if ($testPort < 1 || $testPort > 65535) {
    throw new RuntimeException('Invalid local test database port');
}
$adminDb = new PDO('mysql:host='.$testHost.';port='.$testPort.';charset=utf8mb4', getenv('INSTALL_TEST_MYSQL_USER') ?: 'root', getenv('INSTALL_TEST_MYSQL_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$server = null;
$createdDb = false;
$createdUser = false;
$steps = [];
$check = function ($ok, $label) use (&$steps) {
    if (! $ok) {
        throw new RuntimeException($label);
    }
    $steps[] = $label;
    echo "PASS: {$label}\n";
};
try {
    // Targets are generated here and checked again before cleanup.
    $adminDb->exec("CREATE DATABASE `{$testDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $createdDb = true;
    $adminDb->exec("CREATE USER '{$testUser}'@'127.0.0.1' IDENTIFIED BY ".$adminDb->quote($dbPassword));
    $createdUser = true;
    $adminDb->exec("GRANT ALL PRIVILEGES ON `{$testDatabase}`.* TO '{$testUser}'@'127.0.0.1'");
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
    fclose($socket);
    $server = new Process([PHP_BINARY, '-S', '127.0.0.1:'.$port, '-t', $copy.'/public', $copy.'/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'], $copy.'/public', null, null, null);
    $server->start();
    $cookie = $dir.'/cookies.txt';
    $request = function ($path, ?array $post = null) use ($port, $cookie) {
        $curl = curl_init('http://127.0.0.1:'.$port.$path);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $cookie, CURLOPT_COOKIEFILE => $cookie,
            CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_TIMEOUT => 20, CURLOPT_FOLLOWLOCATION => false]);
        if ($post !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));
        }
        $html = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        return [$code, $html ?: ''];
    };
    for ($i = 0; $i < 10; $i++) {
        [$code, $html] = $request('/install.php');
        if ($code) {
            break;
        }
        usleep(100000);
    }
    if ($code !== 200 || ! str_contains($html, 'setup_key')) {
        echo 'SETUP STATUS: '.$code.' '.substr(strip_tags($html), 0, 500)."\n";
    }
    $check($code === 200 && str_contains($html, 'setup_key'), 'fresh release setup opens without an existing .env or database');
    $check(! is_file($copy.'/.env'), 'GET setup does not create .env');
    $tokenData = json_decode(explode("\n", file_get_contents($copy.'/storage/app/installer/setup-key.php'), 2)[1], true);
    $check(! str_contains($html, $tokenData['token']), 'per-host setup token is never present in HTML');
    $csrf = function ($html) {
        preg_match('/name="csrf" value="([a-f0-9]{64})"/', $html, $matches);

        return $matches[1] ?? '';
    };
    $initialCsrf = $csrf($html);
    [$code] = $request('/install.php', ['csrf' => 'wrong', 'action' => 'configure']);
    $check($code === 419 && ! is_file($copy.'/.env'), 'CSRF blocks setup mutations');
    [$code] = $request('/install.php', ['csrf' => $initialCsrf, 'action' => 'configure']);
    $check($code === 403 && ! is_file($copy.'/.env'), 'configuration requires owner authentication');
    [$code, $html] = $request('/install.php', ['csrf' => $initialCsrf, 'action' => 'authenticate', 'setup_key' => $tokenData['token']]);
    $check($code === 200 && str_contains($html, 'name="db_name"'), 'owner token unlocks configuration form');
    $currentCsrf = $csrf($html);
    [$code, $html] = $request('/install.php', ['csrf' => $currentCsrf, 'action' => 'configure',
        'app_name' => 'Installer Smoke Test', 'app_url' => 'http://127.0.0.1:'.$port,
        'db_host' => $testHost, 'db_port' => (string) $testPort, 'db_name' => $testDatabase, 'db_user' => $testUser, 'db_password' => $dbPassword,
        'admin_name' => 'Isolated Test Admin', 'admin_phone' => '0898765432', 'admin_password' => $adminPassword, 'admin_confirmation' => $adminPassword, 'mode' => 'shared']);
    $check($code === 200 && is_file($copy.'/.env') && str_contains($html, 'name="action" value="advance"'), 'empty isolated database is configured');
    $envBefore = file_get_contents($copy.'/.env');
    $parsed = Dotenv\Dotenv::parse($envBefore);
    $check(! str_contains($envBefore, $adminPassword) && $parsed['DB_PASSWORD'] === $dbPassword, 'password quoting survives and raw admin password is not persisted');
    foreach (['migrate', 'seed', 'finish'] as $phase) {
        [$code, $html] = $request('/install.php', ['csrf' => $currentCsrf, 'action' => 'advance']);
        if ($code !== 200 || str_contains($html, 'role="alert"')) {
            $failureFile = $copy.'/storage/app/installer/failure.php';
            if (is_file($failureFile)) {
                echo 'STEP DIAGNOSTIC: '.explode("\n", file_get_contents($failureFile), 2)[1]."\n";
            }
            preg_match('/role="alert">(.*?)<\/div>/s', $html, $alert);
            echo 'STEP STATUS: '.$code.' '.strip_tags($alert[1] ?? '')."\n";
        }
        $check($code === 200 && ! str_contains($html, 'role="alert"'), $phase.' completes using production-only dependencies');
    }
    $check(str_contains($html, 'ตั้งค่าเว็บไซต์สำเร็จ') && str_contains($html, '/admin/setup'), 'completion discloses pending cron readiness and links the setup assistant');
    [$code] = $request('/install.php');
    $check($code === 410, 'installer is permanently locked after success');
    $appDb = new PDO('mysql:host='.$testHost.';port='.$testPort.';dbname='.$testDatabase.';charset=utf8mb4', $testUser, $dbPassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $check((int) $appDb->query('SELECT COUNT(*) FROM provinces')->fetchColumn() === 77 && (int) $appDb->query('SELECT COUNT(*) FROM users')->fetchColumn() === 1, '77 provinces and exactly the chosen admin are seeded');
    $check(password_verify($adminPassword, $appDb->query('SELECT password FROM users')->fetchColumn()), 'chosen admin password is valid');
    $parsedAfter = Dotenv\Dotenv::parse(file_get_contents($copy.'/.env'));
    $check($parsedAfter['APP_KEY'] === $parsed['APP_KEY'] && $parsedAfter['APP_DEBUG'] === 'false', 'encryption key is unchanged and production debug is disabled');
    foreach (['/provinces', '/guide', '/trang/guide', '/trang', '/trang/map', '/trang/map.geojson', '/trang/weather', '/trang/water-map', '/trang/water/context.json', '/trang/water/reports.json', '/login'] as $path) {
        [$code] = $request($path);
        $check($code === 200, 'installed page '.$path.' responds successfully');
    }
    $cron = new Process([PHP_BINARY, 'artisan', 'flood:cron'], $copy, null, null, 60);
    $cron->run();
    $check($cron->isSuccessful() && is_file($copy.'/storage/app/hosting/scheduler.php') && is_file($copy.'/storage/app/hosting/queue.php'), 'shared-host cron runs scheduler and queue with real heartbeats');
    file_put_contents($dir.'/result.json', json_encode(['passed' => $steps, 'release' => basename($zipPath)], JSON_PRETTY_PRINT));
    echo "RESULT: all integration checks passed\n";
} finally {
    $server?->stop(1);
    if (! preg_match('/^floodthai_install_[a-f0-9]{8}$/D', $testDatabase) || ! preg_match('/^fti_[a-f0-9]{8}$/D', $testUser)) {
        throw new RuntimeException('Unsafe cleanup target');
    }
    if ($createdDb) {
        $adminDb->exec("DROP DATABASE `{$testDatabase}`");
    }
    if ($createdUser) {
        $adminDb->exec("DROP USER '{$testUser}'@'127.0.0.1'");
    }
    echo "CLEANUP: isolated test database and user removed; working database untouched\n";
}
