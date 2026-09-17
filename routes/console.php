<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Update next_start date for expired lessons
Schedule::command('room:update-next-start')->everyMinute();

// Monthly payment records for students with monthly billing
Schedule::command('payments:generate-monthly')->monthlyOn(1, '06:00');

// Remind students about overdue payments
Schedule::command('payments:check-overdue')->dailyAt('09:00');

// Mark expired teacher subscriptions and notify about expiring ones
Schedule::command('subscriptions:check')->hourly();

// Delete lesson recordings older than the tariff retention period
Schedule::command('recordings:cleanup')->dailyAt('04:00')->runInBackground();

// Re-queue S3 uploads for recordings stuck in «Загрузка», clean up killed uploads' leftovers.
Schedule::command('recordings:retry-uploads')->hourly()->withoutOverlapping();

// Database backup to S3 (private), daily; on Sundays also user-uploaded files.
// runInBackground: долгие команды не должны задерживать ежеминутные задачи.
Schedule::command('backup:run')->dailyAt('03:30')->days([1, 2, 3, 4, 5, 6])->runInBackground()->withoutOverlapping(120);
Schedule::command('backup:run --files')->weeklyOn(0, '03:30')->runInBackground()->withoutOverlapping(240);
