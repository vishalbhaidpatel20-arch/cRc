<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Calendar reminders: the bell also triggers this for the person who is in the app; the scheduler covers everyone else.
Schedule::command('crc:send-reminders')->everyMinute()->withoutOverlapping();
