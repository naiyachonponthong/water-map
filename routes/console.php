<?php

use Illuminate\Support\Facades\Schedule;

Schedule::call(fn () => app(\App\Support\HostingStatus::class)->beat('scheduler'))->everyMinute();

Schedule::command('flood:expire-offers')->everyMinute()->withoutOverlapping();
Schedule::command('flood:evaluate-risks')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('flood:recalc-priority')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('model:prune', ['--model' => [\App\Models\AuditLog::class]])->dailyAt('03:00');
Schedule::command('flood:fetch-stations')->everyTenMinutes()->withoutOverlapping();
Schedule::command('flood:sync-public-water')->everyTenMinutes()->withoutOverlapping();
Schedule::command('flood:fetch-forecast')->hourlyAt(7)->withoutOverlapping();
Schedule::command('flood:prune-data')->dailyAt('03:30')->withoutOverlapping();
Schedule::call(function () {
    if (app(\App\Support\PublicWaterHistory::class)->available()) {
        \Illuminate\Support\Facades\DB::table('public_water_readings')->where('measured_at', '<', now()->subDays(14))->delete();
    }
})->name('public-water-history:prune')->dailyAt('03:45')->withoutOverlapping();
