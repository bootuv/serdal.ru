<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Переход приложения с UTC на Europe/Moscow (APP_TIMEZONE).
 *
 * До перехода в базе смешивались два вида времени:
 * - системные отметки (now(), таймстемпы Laravel, время из BBB) хранились в UTC;
 * - время, введённое людьми (расписание, сроки заданий), хранилось как московское «настенное».
 * После перехода Laravel читает все значения как московские, поэтому системные отметки
 * сдвигаем на +3 часа (в Москве нет перехода на летнее время с 2014 года).
 * Время, введённое людьми, НЕ трогаем: room_schedules.scheduled_at/recurrence_time/start_date/end_date,
 * homeworks.deadline, rooms.next_start (считается из расписания), payment_records.due_date (дата).
 *
 * Выполняется только если приложение уже работает в Europe/Moscow — сдвиг имеет смысл только вместе с переключением.
 * Если база уже пишет московское время (свежие записи «в будущем» по UTC), сдвиг пропускается.
 */
return new class extends Migration
{
    private const HOURS = 3;

    /** Таблица => системные поля времени (UTC → Москва). */
    private const COLUMNS = [
        'users' => ['email_verified_at', 'created_at', 'updated_at', 'google_token_expires_at', 'push_reminder_at', 'referral_banner_hidden_until'],
        'password_reset_tokens' => ['created_at'],
        'failed_jobs' => ['failed_at'],
        'subjects' => ['created_at', 'updated_at'],
        'directs' => ['created_at', 'updated_at'],
        'direct_user' => ['created_at', 'updated_at'],
        'settings' => ['created_at', 'updated_at'],
        'rooms' => ['created_at', 'updated_at', 'deleted_at'],
        'recordings' => ['start_time', 'end_time', 'created_at', 'updated_at', 's3_uploaded_at', 'deleted_at'],
        'meeting_sessions' => ['started_at', 'ended_at', 'created_at', 'updated_at', 'deletion_requested_at'],
        'room_schedules' => ['created_at', 'updated_at'],
        'room_user' => ['created_at', 'updated_at'],
        'teacher_student' => ['created_at', 'updated_at'],
        'reviews' => ['created_at', 'updated_at', 'teacher_read_at'],
        'notifications' => ['read_at', 'created_at', 'updated_at'],
        'lesson_types' => ['created_at', 'updated_at'],
        'messages' => ['created_at', 'updated_at', 'read_at'],
        'support_chats' => ['created_at', 'updated_at'],
        'support_messages' => ['read_at', 'created_at', 'updated_at'],
        'homeworks' => ['created_at', 'updated_at'],
        'homework_submissions' => ['submitted_at', 'graded_at', 'created_at', 'updated_at'],
        'push_subscriptions' => ['created_at', 'updated_at'],
        'homework_activities' => ['created_at', 'updated_at'],
        'teacher_materials' => ['created_at', 'updated_at'],
        'material_folders' => ['created_at', 'updated_at'],
        'payment_records' => ['paid_at', 'reminded_at', 'created_at', 'updated_at'],
        'help_categories' => ['created_at', 'updated_at'],
        'help_articles' => ['created_at', 'updated_at'],
        'tariffs' => ['created_at', 'updated_at', 'deleted_at'],
        'subscriptions' => ['starts_at', 'ends_at', 'cancelled_at', 'created_at', 'updated_at', 'expiring_notified_at'],
        'subscription_payments' => ['paid_at', 'created_at', 'updated_at'],
        'teacher_applications' => ['created_at', 'updated_at'],
        'referral_rewards' => ['revoked_at', 'created_at', 'updated_at'],
    ];

    public function up(): void
    {
        if (config('app.timezone') !== 'Europe/Moscow') {
            return;
        }

        if ($this->alreadyMoscow()) {
            // Сдвиг второй раз испортил бы время: база уже пишет московское
            echo "  Системное время в базе уже московское — сдвиг пропущен\n";

            return;
        }

        $this->shift(self::HOURS);
    }

    /**
     * В UTC-данных отметка создания не бывает позже текущего времени UTC. Если свежие записи оказываются
     * «в будущем» больше чем на час — приложение уже работало в Europe/Moscow, и сдвигать нельзя.
     */
    private function alreadyMoscow(): bool
    {
        $limit = now('UTC')->addHour()->format('Y-m-d H:i:s');

        foreach (['notifications', 'messages', 'meeting_sessions', 'homework_activities', 'users'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->where('created_at', '>', $limit)->exists()) {
                return true;
            }
        }

        return false;
    }

    public function down(): void
    {
        if (config('app.timezone') !== 'Europe/Moscow') {
            return;
        }

        $this->shift(-self::HOURS);
    }

    private function shift(int $hours): void
    {
        $driver = DB::connection()->getDriverName();

        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $sets = [];
            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }
                $expr = match ($driver) {
                    'sqlite' => "datetime(\"$column\", '" . ($hours >= 0 ? '+' : '') . "$hours hours')",
                    'pgsql' => "\"$column\" + interval '$hours hours'",
                    default => "DATE_ADD(`$column`, INTERVAL $hours HOUR)",
                };
                $sets[$column] = DB::raw("CASE WHEN " . $this->quote($driver, $column) . " IS NULL THEN NULL ELSE $expr END");
            }

            if ($sets) {
                // updated_at тоже в списке — значения задаём явно, без автоматических таймстемпов
                DB::table($table)->update($sets);
            }
        }
    }

    private function quote(string $driver, string $column): string
    {
        return $driver === 'mysql' || $driver === 'mariadb' ? "`$column`" : "\"$column\"";
    }
};
