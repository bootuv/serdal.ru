<?php

namespace App\Services;

use App\Models\Room;
use App\Models\RoomSchedule;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Расписание ученика: вхождения разовых и повторяющихся занятий в заданном интервале.
 * Единый расчёт для старого календаря (Filament) и нового кабинета.
 */
class StudentScheduleService
{
    /** Активные расписания занятий, в которых ученик — участник. */
    public function schedules(int $studentId): Collection
    {
        return RoomSchedule::with(['room.user'])
            ->whereHas('room', fn ($q) => $q->whereHas('participants', fn ($p) => $p->where('users.id', $studentId)))
            ->where('is_active', true)
            ->get();
    }

    /**
     * Занятия ученика в интервале [from, to], по времени начала.
     * Идущие сейчас занятия без вхождения на сегодня добавляются отдельным событием (type = running).
     *
     * @param  Collection<int, RoomSchedule>|null  $schedules  уже загруженные расписания ученика
     * @return Collection<int, array{id:int|string, room_id:int, teacher_id:int, title:string, start:\Illuminate\Support\Carbon,
     *     end:\Illuminate\Support\Carbon, owner:?string, type:string, room_type:?string, duration:int, is_running:bool}>
     */
    public function events(int $studentId, CarbonInterface $from, CarbonInterface $to, ?Collection $schedules = null): Collection
    {
        $events = $this->occurrences($schedules ?? $this->schedules($studentId), $from, $to);
        $now = now();

        $runningRooms = Room::where('is_running', true)
            ->whereHas('participants', fn ($q) => $q->where('users.id', $studentId))
            ->with('user')
            ->get();

        foreach ($runningRooms as $room) {
            $alreadyInEvents = $events->contains(fn ($event) => $event['room_id'] === $room->id && $event['start']->isSameDay($now));

            if (! $alreadyInEvents) {
                $events->push([
                    'id' => 'running-' . $room->id,
                    'room_id' => $room->id,
                    'teacher_id' => $room->user_id,
                    'title' => $room->name,
                    'start' => $now->copy()->startOfHour(),
                    'end' => $now->copy()->addHour(),
                    'owner' => $room->user?->name,
                    'type' => 'running',
                    'room_type' => $room->type,
                    'duration' => 60,
                    'is_running' => true,
                ]);
            }
        }

        return $events->sortBy('start')->values();
    }

    /** Вхождения расписаний в интервал [from, to]: разовые — по дате, повторяющиеся — по дням (RoomSchedule::isActiveAt). */
    public function occurrences(iterable $schedules, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $events = collect();

        foreach ($schedules as $schedule) {
            $room = $schedule->room;

            if (! $room) {
                continue;
            }

            if ($schedule->type === 'once') {
                if ($schedule->scheduled_at && $schedule->scheduled_at->between($from, $to)) {
                    $events->push($this->event($schedule, $schedule->scheduled_at->copy(), 'once'));
                }

                continue;
            }

            $current = $from->copy()->startOfDay();
            while ($current->lte($to)) {
                if ($schedule->isActiveAt($current->copy()->setTimeFromTimeString($schedule->recurrence_time ?? '00:00'))) {
                    $events->push($this->event($schedule, $current->copy()->setTimeFromTimeString($schedule->recurrence_time), $schedule->recurrence_type));
                }
                $current->addDay();
            }
        }

        return $events->sortBy('start')->values();
    }

    private function event(RoomSchedule $schedule, CarbonInterface $start, ?string $type): array
    {
        $room = $schedule->room;

        return [
            'id' => $schedule->id,
            'room_id' => $schedule->room_id,
            'teacher_id' => $room->user_id,
            'title' => $room->name,
            'start' => $start,
            'end' => $start->copy()->addMinutes($schedule->duration_minutes),
            'owner' => $room->user?->name,
            'type' => $type,
            'room_type' => $room->type,
            'duration' => $schedule->duration_minutes,
            'is_running' => (bool) $room->is_running,
        ];
    }
}
