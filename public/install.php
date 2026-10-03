<?php

// Standalone installer; deliberately bypasses Laravel sessions/database middleware.
use FloodThai\Installer\Installer;
use FloodThai\Installer\Runner;

ini_set('display_errors', '0');
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'self'; img-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
if (version_compare(PHP_VERSION, '8.3', '<')) {
    http_response_code(503);
    exit('FloodThai ต้องใช้ PHP 8.3 ขึ้นไป กรุณาเลือกเวอร์ชัน PHP ในแผงควบคุมโฮสต์');
}
require_once __DIR__.'/../installer/Installer.php';
require_once __DIR__.'/../installer/Runner.php';
$installer = new Installer(dirname(__DIR__));
$locked = $installer->locked();
$checks = $installer->checks($_SERVER);
$ready = Installer::ready($checks);
$error = '';
$authenticated = false;
$done = false;
$state = [];
$values = [];
$csrf = '';
try {
    if ($locked) {
        http_response_code(410);
    } elseif ($ready) {
        $sessionDir = $installer->directory().'/sessions';
        if (! is_dir($sessionDir)) {
            mkdir($sessionDir, 0700, true);
        }
        session_name('floodthai_setup');
        session_save_path($sessionDir);
        ini_set('session.use_strict_mode', '1');
        session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => Installer::secure($_SERVER), 'httponly' => true, 'samesite' => 'Strict']);
        if (! session_start()) {
            throw new RuntimeException('สร้าง session ไม่ได้ กรุณาตรวจพื้นที่ว่างและสิทธิ์ storage');
        }
        $installer->token(); // Created independently on each host; NEVER included in ZIP.
        $csrf = $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
        $authenticated = ($_SESSION['authenticated_until'] ?? 0) > time();
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'POST') {
            if (! is_string($_POST['csrf'] ?? null) || ! hash_equals($csrf, $_POST['csrf'])) {
                http_response_code(419);
                throw new RuntimeException('แบบฟอร์มหมดอายุ กรุณาโหลดหน้าใหม่');
            }
            $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
            if ($action === 'authenticate') {
                if (! $installer->authenticate(is_string($_POST['setup_key'] ?? null) ? $_POST['setup_key'] : '')) {
                    http_response_code(403);
                    throw new RuntimeException('รหัสติดตั้งไม่ถูกต้อง กรุณาคัดลอกจาก File Manager ของโฮสต์นี้');
                }
                session_regenerate_id(true);
                $_SESSION['authenticated_until'] = time() + 1800;
                $_SESSION['csrf'] = $csrf = bin2hex(random_bytes(32));
                $authenticated = true;
            } elseif (! $authenticated) {
                http_response_code(403);
                throw new RuntimeException('ยืนยันรหัสติดตั้งก่อนดำเนินการ');
            } elseif ($action === 'configure') {
                foreach ($_POST as $key => $value) {
                    if (is_string($value) && ! in_array($key, ['db_password', 'admin_password', 'admin_confirmation', 'csrf'], true)) {
                        $values[$key] = $value;
                    }
                }
                $installer->prepare($_POST);
            } elseif ($action === 'advance') {
                $done = (new Runner($installer))->advance() === 'done';
                if ($done) {
                    $_SESSION = [];
                    session_destroy();
                }
            } else {
                http_response_code(400);
                throw new RuntimeException('คำสั่งติดตั้งไม่ถูกต้อง');
            }
        } elseif ($method !== 'GET') {
            http_response_code(405);
            throw new RuntimeException('หน้านี้รับเฉพาะ GET และ POST');
        }
        $state = $installer->read('state');
    } else {
        http_response_code(503);
    }
} catch (RuntimeException $e) {
    // Installer-authored errors are safe; framework exceptions get a generic message.
    $error = get_class($e) === RuntimeException::class ? $e->getMessage() : 'ติดตั้งยังไม่สำเร็จ กรุณาตรวจสิทธิ์ฐานข้อมูลและพื้นที่ว่าง แล้วดำเนินขั้นตอนเดิมอีกครั้ง';
} catch (Throwable $e) {
    http_response_code(500);
    $error = 'ติดตั้งยังไม่สำเร็จ กรุณาตรวจสิทธิ์ฐานข้อมูลและพื้นที่ว่าง แล้วดำเนินขั้นตอนเดิมอีกครั้ง หากไม่สำเร็จให้ผู้ดูแลตรวจบันทึกของเซิร์ฟเวอร์';
}
$e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
require __DIR__.'/../installer/view.php';
