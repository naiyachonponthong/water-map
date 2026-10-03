<?php

use Illuminate\Support\Facades\Schedule;

Schedule::call(fn () => app(\App\Support\HostingStatus::class)->beat('scheduler'))->everyMinute();

Schedule::command('flood:expire-offers')->everyMinute()->withoutOverlapping();
Schedule::command('flood:evaluate-risks')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('flood:recalc-priority')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('model:prune', ['--model' => [\App\Models\AuditLog::class]])->dailyAt('03:00');
Schedule::command('flood:fetch-stations')->everyTenMinutes()->withoutOverlapping();
Schedule::command('flood:fetch-forecast')->hourlyAt(7)->withoutOverlapping();
Schedule::command('flood:prune-data')->dailyAt('03:30')->withoutOverlapping();
