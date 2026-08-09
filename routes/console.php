<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('platform:backup:run')
    ->dailyAt('02:30')
    ->name('platform-backup-run')
    ->withoutOverlapping(120);

Schedule::command('backup:clean --disable-notifications --isolated')
    ->dailyAt('03:30')
    ->name('platform-backup-clean')
    ->withoutOverlapping(120);

Schedule::command('backup:monitor --isolated')
    ->hourly()
    ->name('platform-backup-monitor')
    ->withoutOverlapping(30);

if (app()->environment('staging') && (bool) env('STAGING_SCHEDULER_PROBE', false)) {
    Schedule::command('platform:staging-scheduler-probe')
        ->everyMinute()
        ->name('staging-scheduler-probe')
        ->withoutOverlapping(1);
}
