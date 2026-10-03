<?php

use FloodThai\Installer\Installer;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Fresh distribution: direct visitors to setup before DB-backed middleware boots.
// Existing configured sites stay untouched; in-progress setup fails closed.
if (is_file(__DIR__.'/../installer/Installer.php')) {
    require_once __DIR__.'/../installer/Installer.php';
    $setup = new Installer(dirname(__DIR__));
    if ($setup->needsSetup()) {
        header('Location: /install.php', true, 302);
        exit;
    }
    if (is_file(__DIR__.'/../.env') && preg_match('/^APP_INSTALLING[ \t]*=[ \t]*["\']?true/m', file_get_contents(__DIR__.'/../.env'))) {
        http_response_code(503);
        header('Retry-After: 60');
        exit('FloodThai กำลังติดตั้ง กรุณาให้เจ้าของโฮสต์ดำเนินการติดตั้งให้เสร็จ');
    }
}

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
