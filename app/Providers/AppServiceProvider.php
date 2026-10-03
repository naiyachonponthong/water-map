<?php

namespace App\Providers;

use App\Models\User;
use App\Support\HostingStatus;
use App\Support\RiskEngine;
use App\Support\Settings;
use App\Support\StationService;
use App\Support\Theme;
use App\Support\WaterReportService;
use Carbon\Carbon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Queue::looping(fn () => app(HostingStatus::class)->beat('queue'));
        Carbon::setLocale('th');

        // อยู่หลัง reverse proxy: เชื่อ X-Forwarded-* เพื่อให้ได้ IP จริงและรู้ว่าเป็น https
        $proxies = (string) config('floodthai.trusted_proxies');
        TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        Paginator::useBootstrapFive();

        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        // ผู้ดูแลระบบสูงสุดผ่านทุกสิทธิ์
        Gate::before(fn (User $user) => $user->isSuperAdmin() ? true : null);

        // กันส่งคำขอรัว: 6 ครั้ง / 30 นาที ต่ออุปกรณ์และต่อ IP (IP ใช้เกณฑ์หลวมกว่า เพราะคนทั้งตึกอาจใช้เน็ตเดียวกัน)
        RateLimiter::for('help', fn (Request $r) => [
            Limit::perMinutes(30, 6)->by('dev:'.($r->cookie('fdev') ?: $r->ip())),
            Limit::perMinutes(30, (int) config('floodthai.help_ip_limit'))->by('ip:'.$r->ip()),
        ]);

        // รายงานระดับน้ำ: 4 ครั้ง / 10 นาที ต่ออุปกรณ์, 60 ต่อ IP
        RateLimiter::for('report', fn (Request $r) => [
            Limit::perMinutes(10, 4)->by('rdev:'.($r->cookie('fdev') ?: $r->ip())),
            Limit::perMinutes(10, (int) config('floodthai.report_ip_limit'))->by('rip:'.$r->ip()),
        ]);
        RateLimiter::for('vote', fn (Request $r) => Limit::perMinute(20)->by('vote:'.$r->ip()));

        // รายงานระดับน้ำที่ยืนยันแล้วเป็นแหล่งข้อมูลของระบบเตือนจุดเสี่ยง
        RiskEngine::addSource('water_reports', [WaterReportService::class, 'samples']);
        RiskEngine::addSource('stations', [StationService::class, 'samples']);

        // ค่าที่ทุก layout ใช้: สีธีม, ชื่อระบบ (DB-first)
        View::composer(['layouts.*', 'public.*', 'auth.*', 'live.tv', 'field.*'], function ($view) {
            $province = current_province();
            $view->with('themeCss', Theme::css(Settings::get('theme_color', $province?->id)));
            $view->with('appTitle', Settings::get('app_title', null, config('app.name')));
        });
    }
}
