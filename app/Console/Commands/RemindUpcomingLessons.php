<?php

namespace App\Console\Commands;

use App\Models\Room;
use App\Models\RoomSchedule;
use App\Models\User;
use App\Notifications\LessonStartingSoon;
use App\Services\StudentScheduleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Напоминания о занятиях: учителю и ученикам — за 15 минут до начала по расписанию (в кабинет, реалтайм и пуш).
 * Перенесённые занятия — по новому времени, отменённые и уже идущие — без напоминания.
 * Запускается планировщиком каждую минуту; каждое вхождение напоминается один раз (отметка в кеше).
 */
class RemindUpcomingLessons extends Command
{
    public const MINUTES_BEFORE = 15;

    protected $signature = 'lessons:remind';

    protected $description = 'Напомнить учителю и ученикам о занятии за 15 минут до начала';

    public function handle(StudentScheduleService $occurrences): int
    {
        $from = now();
        $to = now()->addMinutes(self::MINUTES_BEFORE);

        $schedules = RoomSchedule::with(['room.user', 'room.participants', 'exceptions'])
            ->where('is_active', true)
            ->whereHas('room')
            ->get();

        $sent = 0;
        foreach ($occurrences->occurrences($schedules, $from, $to) as $event) {
            // Повторяющиеся занятия расписание отдаёт на весь день — берём только начало в ближайшие 15 минут
            if ($event['start']->lte($from) || $event['start']->gt($to)) {
                continue;
            }

            $room = $schedules->firstWhere('room_id', $event['room_id'])?->room;
            if (! $room instanceof Room || $room->is_running) {
                continue;
            }

            // Одно напоминание на вхождение, даже если команда пересеклась с прошлым запуском
            if (! Cache::add('lesson-reminder:' . $room->id . ':' . $event['start']->timestamp, true, now()->addDay())) {
                continue;
            }

            // Админ не ведёт занятий и не учится — напоминания только учителю и ученикам
            $recipients = collect([$room->user])->merge($room->participants)->filter()->unique('id')
                ->reject(fn (User $user) => $user->role === User::ROLE_ADMIN);

            foreach ($recipients as $user) {
                // Отметка в кеше пропадает при деплое (optimize:clear) — сверяемся и с уже сохранёнными уведомлениями
                if (LessonStartingSoon::alreadySent($user, $room, $event['start'])) {
                    continue;
                }

                try {
                    $user->notify(new LessonStartingSoon($room, $event['start']->copy()));
                    $sent++;
                } catch (\Throwable $e) {
                    // Сбой пуша или трансляции одному человеку не должен останавливать остальных
                    Log::warning('Напоминание о занятии не отправлено', ['room' => $room->id, 'user' => $user->id, 'error' => $e->getMessage()]);
                }
            }
        }

        $this->info("Напоминаний отправлено: {$sent}");

        return self::SUCCESS;
    }
}
