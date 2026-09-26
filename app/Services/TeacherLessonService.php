<?php

namespace App\Services;

use App\Models\Room;
use App\Models\RoomSchedule;
use App\Models\User;
use App\Notifications\TeacherAssignedLesson;
use App\Notifications\TeacherUpdatedSchedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Планирование занятий учителя: создание занятия с расписанием, изменение и удаление правила расписания,
 * архивирование занятия. Логика — как в старом кабинете (RoomResource, CreateRoom, EditRoom), уведомления ученикам те же.
 */
class TeacherLessonService
{
    /** Ученики, которых учитель может добавить в занятие: связь teacher_student (как в RoomResource). */
    public function studentsQuery(User $teacher): Builder
    {
        return User::query()->whereHas('teachers', fn ($q) => $q->where('teacher_student.teacher_id', $teacher->id));
    }

    /**
     * Создать занятие с расписанием (CreateRoom): пароли и meeting_id, тип по числу участников, уведомление ученикам.
     *
     * @param  array<int>  $participantIds
     * @param  array<int, array>  $schedules  атрибуты RoomSchedule (см. scheduleAttributes())
     */
    public function create(User $teacher, string $name, array $participantIds, array $schedules): Room
    {
        $allowed = $this->studentsQuery($teacher)->whereIn('users.id', $participantIds)->pluck('users.id')->all();

        $room = DB::transaction(function () use ($teacher, $name, $allowed, $schedules) {
            $room = Room::create([
                'user_id' => $teacher->id,
                'name' => $name,
                'type' => 'pending',
                'meeting_id' => (string) Str::uuid(),
                'moderator_pw' => Str::random(8),
                'attendee_pw' => Str::random(8),
            ]);

            $room->participants()->sync($allowed);

            foreach ($schedules as $attributes) {
                $room->schedules()->create($attributes);
            }

            return $room;
        });

        app(TeacherStudentsService::class)->refreshRoomType($room);
        $room->refresh();

        foreach ($room->participants as $student) {
            $student->notify(new TeacherAssignedLesson($room, $teacher));
        }

        return $room;
    }

    /** Изменить правило расписания (EditRoom): если что-то поменялось — ученики получают «Расписание обновлено». */
    public function updateSchedule(RoomSchedule $schedule, array $attributes, User $teacher): bool
    {
        $schedule->fill($attributes);

        if (! $schedule->isDirty()) {
            return false;
        }

        $schedule->save();
        $this->notifyScheduleChanged($schedule->room, $teacher);

        return true;
    }

    /** Добавить правило расписания занятию (время ещё не назначено). */
    public function addSchedule(Room $room, array $attributes, User $teacher): RoomSchedule
    {
        $schedule = $room->schedules()->create($attributes);
        $this->notifyScheduleChanged($room, $teacher);

        return $schedule;
    }

    /** Удалить правило расписания (удаление пункта расписания в RoomResource) с уведомлением ученикам. */
    public function deleteSchedule(RoomSchedule $schedule, User $teacher): void
    {
        $room = $schedule->room;
        $participants = $room ? $room->participants : collect();

        $schedule->delete();

        foreach ($participants as $student) {
            $student->notify(new TeacherUpdatedSchedule($teacher));
        }
    }

    /** Убрать занятие в архив (DeleteAction в EditRoom): расписания удаляются, история и записи остаются. */
    public function archive(Room $room, User $teacher): void
    {
        $participants = $room->participants;

        $room->delete();

        foreach ($participants as $student) {
            $student->notify(new TeacherUpdatedSchedule($teacher));
        }
    }

    public function notifyScheduleChanged(?Room $room, User $teacher): void
    {
        foreach ($room?->participants ?? [] as $student) {
            $student->notify(new TeacherUpdatedSchedule($teacher));
        }
    }

    /**
     * Атрибуты RoomSchedule из формы нового кабинета.
     * once: date (Y-m-d), time (H:i); weekly: days (0–6, 0 — вс), time, date (начиная с), until (необязательно).
     */
    public static function scheduleAttributes(string $repeat, string $date, string $time, int $duration, array $days = [], ?string $until = null): array
    {
        if ($repeat === 'once') {
            $at = Carbon::parse($date . ' ' . $time);

            return [
                'type' => 'once',
                'scheduled_at' => $at,
                'start_date' => $at->format('Y-m-d'),
                'recurrence_type' => null,
                'recurrence_days' => null,
                'recurrence_time' => null,
                'end_date' => null,
                'duration_minutes' => $duration,
                'is_active' => true,
            ];
        }

        return [
            'type' => 'recurring',
            'scheduled_at' => null,
            'recurrence_type' => 'weekly',
            'recurrence_days' => array_values(array_map('intval', $days)),
            'recurrence_time' => $time,
            'start_date' => $date,
            'end_date' => $until ?: null,
            'duration_minutes' => $duration,
            'is_active' => true,
        ];
    }

    /** Первое занятие по правилу: для разового — дата и время, для еженедельного — ближайший выбранный день с даты начала. */
    public static function firstOccurrence(string $repeat, ?string $date, ?string $time, array $days = []): ?Carbon
    {
        if (! $date || ! $time || ! preg_match('/^\d{1,2}:\d{2}$/', $time)) {
            return null;
        }

        try {
            $start = Carbon::parse($date . ' ' . $time);
        } catch (\Throwable) {
            return null;
        }

        if ($repeat === 'once') {
            return $start;
        }

        $days = array_map('intval', $days);
        for ($i = 0; $i < 7; $i++) {
            $candidate = $start->copy()->addDays($i);
            if (in_array($candidate->dayOfWeek, $days, true)) {
                return $candidate;
            }
        }

        return null;
    }

    /** Варианты длительности занятия: стандартные и текущая, если она нестандартная. */
    public static function durationOptions(int $current): array
    {
        return collect([30, 45, 60, 75, 90, 120, 150, 180])->push($current)->filter(fn ($m) => $m > 0)->unique()->sort()->values()
            ->mapWithKeys(fn ($m) => [$m => plural_ru($m, 'минута', 'минуты', 'минут')])->all();
    }
}
