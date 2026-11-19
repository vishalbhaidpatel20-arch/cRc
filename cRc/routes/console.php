<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Calendar reminders: the bell also triggers this for the person who is in the app; the scheduler covers everyone else.
Schedule::command('crc:send-reminders')->everyMinute()->withoutOverlapping();

// Daily database backup on the server at 1:00 PM India time (07:30 UTC; the app itself runs in UTC). A dev PC runs it from
// its own Task Scheduler task instead, so it is limited to production here (otherwise `schedule:work` on the PC would back up twice).
Schedule::command('crc:backup-db')->dailyAt('13:00')->timezone('Asia/Kolkata')
    ->environments('production')->withoutOverlapping()->onOneServer();

// Horizon dashboard metrics (production only: Horizon needs Redis and pcntl, so it does not run on the Windows dev PC).
if (app()->environment('production')) {
    Schedule::command('horizon:snapshot')->everyFiveMinutes();
}
