<?php

namespace App\Services;

use App\Models\Room;
use App\Models\RoomSchedule;
use App\Models\RoomScheduleException;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Расписание ученика: вхождения разовых и повторяющихся занятий в заданном интервале.
 */
class StudentScheduleService
{
    /** За сколько минут до начала ученику открывается «Войти в класс» (главная, расписание, страница занятия). */
    public const JOIN_OPENS_MINUTES = 15;

    /**
     * Открыт ли ученику вход в класс: занятие идёт или начнётся не позже чем через JOIN_OPENS_MINUTES
     * (и по расписанию ещё не закончилось). Блокировку за долг проверяют отдельно (PaymentRecordService).
     */
    public static function canJoin(bool $running, ?CarbonInterface $start, ?CarbonInterface $end = null): bool
    {
        if ($running) {
            return true;
        }

        return $start !== null
            && $start->lte(now()->addMinutes(self::JOIN_OPENS_MINUTES))
            && ($end === null || $end->isFuture());
    }

    /** Подпись вместо кнопки: «Вход откроется в 15:45» (сегодня) или «Вход откроется за 15 минут до начала». */
    public static function joinOpensLabel(?CarbonInterface $start): string
    {
        $opens = $start?->copy()->subMinutes(self::JOIN_OPENS_MINUTES);

        return $opens && $opens->isToday() && $opens->isFuture()
            ? 'Вход откроется в ' . $opens->format('H:i')
            : 'Вход откроется за ' . plural_ru(self::JOIN_OPENS_MINUTES, 'минуту', 'минуты', 'минут') . ' до начала';
    }

    /** Активные расписания занятий, в которых ученик — участник. */
    public function schedules(int $studentId): Collection
    {
        return RoomSchedule::with(['room.user', 'exceptions'])
            ->whereHas('room', fn ($q) => $q->whereHas('participants', fn ($p) => $p->where('users.id', $studentId)))
            ->where('is_active', true)
            ->get();
    }

    /**
     * Занятия ученика в интервале [from, to], по времени начала.
     * Идущие сейчас занятия без вхождения на сегодня добавляются отдельным событием (type = running).
     *
     * Отменённые занятия приходят, только если $withCancelled (cancelled = true, reason — причина).
     *
     * @param  Collection<int, RoomSchedule>|null  $schedules  уже загруженные расписания ученика
     * @return Collection<int, array{id:int|string, room_id:int, teacher_id:int, title:string, start:\Illuminate\Support\Carbon,
     *     end:\Illuminate\Support\Carbon, owner:?string, type:string, room_type:?string, duration:int, is_running:bool}>
     */
    public function events(int $studentId, CarbonInterface $from, CarbonInterface $to, ?Collection $schedules = null, bool $withCancelled = false): Collection
    {
        $events = $this->occurrences($schedules ?? $this->schedules($studentId), $from, $to, $withCancelled);
        $now = now();

        $runningRooms = Room::where('is_running', true)
            ->whereHas('participants', fn ($q) => $q->where('users.id', $studentId))
            ->with('user')
            ->get();

        foreach ($runningRooms as $room) {
            $alreadyInEvents = $events->contains(fn ($event) => $event['room_id'] === $room->id && ! $event['cancelled'] && $event['start']->isSameDay($now));

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
                ] + self::exceptionKeys(null, $now->copy()->startOfHour()));
            }
        }

        return $events->sortBy('start')->values();
    }

    /**
     * Вхождения расписаний в интервал [from, to] с учётом исключений (RoomScheduleException):
     * отменённые пропускаются (или приходят с cancelled = true, если $withCancelled), перенесённые — в новое время.
     */
    public function occurrences(iterable $schedules, CarbonInterface $from, CarbonInterface $to, bool $withCancelled = false): Collection
    {
        $schedules = collect($schedules)->filter(fn (RoomSchedule $s) => $s->room !== null)->values();
        $exceptions = self::exceptionsFor($schedules);
        $events = collect();

        foreach ($schedules as $schedule) {
            $own = $exceptions->get($schedule->id, collect())->keyBy(fn (RoomScheduleException $e) => $e->original_date->format('Y-m-d'));
            $type = $schedule->type === 'once' ? 'once' : $schedule->recurrence_type;

            foreach ($schedule->rawOccurrences($from, $to) as $start) {
                $exception = $own->get($start->format('Y-m-d'));

                if (! $exception) {
                    $events->push($this->event($schedule, $start, $type));
                } elseif ($exception->isCancelled() && $withCancelled) {
                    $events->push($this->event($schedule, $start, $type, $exception));
                }
            }

            // Перенесённые занятия: новое время внутри интервала (исходное может быть и вне его)
            foreach ($own as $exception) {
                if ($exception->isMoved() && $exception->starts_at->between($from, $to)) {
                    $events->push($this->event($schedule, $exception->starts_at->copy(), $type, $exception));
                }
            }
        }

        return $events->sortBy('start')->values();
    }

    /**
     * Исключения правил одним запросом (или из загруженного отношения).
     *
     * @return Collection<int, Collection<int, RoomScheduleException>> id правила => исключения
     */
    public static function exceptionsFor(Collection $schedules): Collection
    {
        if ($schedules->isEmpty()) {
            return collect();
        }

        if ($schedules->every(fn (RoomSchedule $s) => $s->relationLoaded('exceptions'))) {
            return $schedules->mapWithKeys(fn (RoomSchedule $s) => [$s->id => $s->exceptions]);
        }

        return RoomScheduleException::whereIn('room_schedule_id', $schedules->pluck('id'))->get()->groupBy('room_schedule_id');
    }

    /**
     * Вхождение занятия: ключи id (правило), room_id, start/end, type и сведения об исключении —
     * original_start (время по правилу), status (null | cancelled | moved), cancelled, reason, moved_from.
     */
    private function event(RoomSchedule $schedule, CarbonInterface $start, ?string $type, ?RoomScheduleException $exception = null): array
    {
        $room = $schedule->room;
        $moved = $exception?->isMoved() ?? false;
        $duration = $moved && $exception->duration_minutes ? (int) $exception->duration_minutes : $schedule->minutes();

        return [
            'id' => $schedule->id,
            'room_id' => $schedule->room_id,
            'teacher_id' => $room->user_id,
            'title' => $room->name,
            'start' => $start,
            'end' => $start->copy()->addMinutes($duration),
            'owner' => $room->user?->name,
            'type' => $type,
            'room_type' => $room->type,
            'duration' => $duration,
            'is_running' => (bool) $room->is_running,
        ] + self::exceptionKeys($exception, $moved ? $exception->original_starts_at->copy() : $start->copy(), $schedule->type);
    }

    /** Сведения об исключении для вхождения (у обычного — пустые). */
    public static function exceptionKeys(?RoomScheduleException $exception, CarbonInterface $original, ?string $scheduleType = null): array
    {
        return [
            'schedule_type' => $scheduleType,
            'original_start' => $original,
            'status' => $exception?->status,
            'cancelled' => (bool) $exception?->isCancelled(),
            'reason' => $exception?->reason,
            'moved_from' => $exception?->isMoved() ? $exception->original_starts_at->copy() : null,
            'exception_id' => $exception?->id,
        ];
    }
}
