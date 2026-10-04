<?php

// Isolated visual QA. No application database is read or written.
$root = dirname(__DIR__);
$out = $root.'/output/qa';
if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (in_array($path, ['/insights-preview.html', '/health-preview.html', '/admin-health-preview.html'], true)) {
        header('Content-Type: text/html; charset=utf-8');
        readfile($out.$path);
        return;
    }
    $public = realpath($root.'/public');
    $file = realpath($public.$path);
    $types = ['css' => 'text/css', 'js' => 'application/javascript', 'woff2' => 'font/woff2', 'png' => 'image/png', 'svg' => 'image/svg+xml'];
    if ($file && str_starts_with($file, $public.DIRECTORY_SEPARATOR) && is_file($file) && isset($types[pathinfo($file, PATHINFO_EXTENSION)])) {
        header('Content-Type: '.$types[pathinfo($file, PATHINFO_EXTENSION)]);
        readfile($file);
        return;
    }
    http_response_code(404); exit;
}

require $root.'/vendor/autoload.php';
foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync'] as $key => $value) {
    putenv($key.'='.$value); $_ENV[$key] = $_SERVER[$key] = $value;
}
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array', 'session.driver' => 'array',
    'queue.default' => 'sync', 'app.url' => 'http://127.0.0.1:8997', 'floodthai.two_factor_roles' => []]);
Illuminate\Support\Facades\DB::purge('sqlite');
Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => Database\Seeders\RolePermissionSeeder::class, '--force' => true]);
$province = App\Models\Province::create(['code' => '92', 'slug' => 'trang', 'name_th' => 'ตรัง', 'region' => 'south', 'is_active' => true]);
App\Models\Province::create(['code' => '81', 'slug' => 'krabi', 'name_th' => 'กระบี่', 'region' => 'south', 'is_active' => true]);
$admin = App\Models\User::create(['name' => 'ผู้ดูแลทดสอบ', 'phone' => '0899999999', 'password' => bin2hex(random_bytes(20)), 'status' => 'active']);
$admin->assignRole('super-admin'); auth()->setUser($admin);
$app->instance('currentProvince', $province);
$session = app('session')->driver(); $session->start(); $session->put('admin_province_id', $province->id);
view()->share('currentProvince', $province); view()->share('errors', new Illuminate\Support\ViewErrorBag);
$request = Illuminate\Http\Request::create('http://127.0.0.1:8997/trang/insights');
$request->setLaravelSession($session); $request->setUserResolver(fn () => $admin);
$request->setRouteResolver(fn () => (new Illuminate\Routing\Route('GET', '/trang/insights', []))->name('public.insights'));
$app->instance('request', $request); app('url')->setRequest($request);
if (! is_dir($out)) mkdir($out, 0777, true);
$controller = app(App\Http\Controllers\Public\InsightsController::class);
file_put_contents($out.'/insights-preview.html', $controller->index($province)->render());
file_put_contents($out.'/health-preview.html', $controller->status($province)->render());
file_put_contents($out.'/admin-health-preview.html', app(App\Http\Controllers\Admin\DataHealthController::class)->index()->render());
echo "Rendered 3 isolated previews; SQLite in memory only.\n";
