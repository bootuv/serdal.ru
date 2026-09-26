<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Update next_start date for expired lessons
Schedule::command('room:update-next-start')->everyMinute();

// Напоминание учителю и ученикам за 15 минут до занятия (кабинет, реалтайм, пуш)
Schedule::command('lessons:remind')->everyMinute()->withoutOverlapping();

// Ученикам: срок сдачи задания через сутки, работа не сдана
Schedule::command('homework:remind-deadlines')->hourly()->withoutOverlapping();

// Счета за месяц ученикам с помесячной оплатой: 1-го — всем, в остальные дни — тем, у кого занятия появились позже
Schedule::command('payments:generate-monthly')->dailyAt('06:00');

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

// Запасная сверка статуса занятий с сервером видеосвязи: если вебхук о завершении потерялся,
// занятие не останется навсегда «идущим» и не заблокирует старт других (раньше это делали страницы старого кабинета)
Schedule::call(function () {
    \App\Models\Room::where('is_running', true)->distinct()->pluck('user_id')
        ->each(fn ($userId) => \App\Jobs\SyncUserBbbStatus::dispatch((int) $userId));
})->everyTwoMinutes()->name('bbb-status-fallback-sync')->withoutOverlapping();
