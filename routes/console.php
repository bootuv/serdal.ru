<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Start scheduled meetings every minute
Schedule::command('meetings:start-scheduled')->everyMinute();

// Update next_start date for expired lessons
Schedule::command('room:update-next-start')->everyMinute();

// Monthly payment records for students with monthly billing
Schedule::command('payments:generate-monthly')->monthlyOn(1, '06:00');

// Remind students about overdue payments
Schedule::command('payments:check-overdue')->dailyAt('09:00');
