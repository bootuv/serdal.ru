<?php

namespace App\Services;

use App\Models\MeetingSession;
use App\Models\PaymentRecord;
use App\Models\Room;
use App\Models\RoomSchedule;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Расписание учителя: вхождения разовых и повторяющихся занятий в заданном интервале.
 * Единый расчёт для старого календаря (Filament, ScheduleCalendar) и нового кабинета учителя
 * (Сегодня, Расписание, Занятие). Сам расчёт вхождений общий с расписанием ученика (StudentScheduleService::occurrences).
 */
class TeacherScheduleService
{
    /** Длительность занятия по умолчанию, если в расписании её нет (как в Room::updateNextStart). */
    public const DEFAULT_DURATION = 45;

    public function __construct(private StudentScheduleService $occurrences) {}

    /** Активные расписания занятий учителя (архивные занятия не входят). */
    public function schedules(int $teacherId): Collection
    {
        return RoomSchedule::with(['room.user', 'room.participants:id,name,avatar'])
            ->whereHas('room', fn ($q) => $q->where('user_id', $teacherId))
            ->where('is_active', true)
            ->get();
    }

    /**
     * Занятия учителя в интервале [from, to], по времени начала.
     * Идущие сейчас занятия без вхождения на сегодня добавляются отдельным событием (type = running) — как в старом календаре.
     *
     * @param  Collection<int, RoomSchedule>|null  $schedules  уже загруженные расписания учителя
     * @return Collection<int, array{id:int|string, room_id:int, teacher_id:int, title:string, start:Carbon, end:Carbon,
     *     owner:?string, type:string, room_type:?string, duration:int, is_running:bool}>
     */
    public function events(int $teacherId, CarbonInterface $from, CarbonInterface $to, ?Collection $schedules = null): Collection
    {
        $events = $this->occurrences->occurrences($schedules ?? $this->schedules($teacherId), $from, $to);
        $now = now();

        $runningRooms = Room::where('is_running', true)
            ->where('user_id', $teacherId)
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

    /**
     * Занятия учителя для нового кабинета: вхождения + участники, повтор, «идёт сейчас», прошло ли.
     * У запущенного занятия «идёт» только одно вхождение — сегодняшнее, ближайшее к текущему времени.
     *
     * @return Collection<int, array> см. describe()
     */
    public function lessons(int $teacherId, CarbonInterface $from, CarbonInterface $to, ?Collection $schedules = null): Collection
    {
        $schedules ??= $this->schedules($teacherId);
        $byId = $schedules->keyBy('id');
        $now = now();

        $events = $this->events($teacherId, $from, $to, $schedules)
            ->map(fn (array $e) => $e + ['live' => $e['is_running'] && ($e['type'] === 'running' || $e['start']->isToday())]);

        $events = $events->groupBy('room_id')->flatMap(function (Collection $roomEvents) use ($now) {
            $live = $roomEvents->where('live', true)->sortBy(fn ($e) => abs($e['start']->diffInMinutes($now)))->keys()->first();

            return $roomEvents->map(fn ($e, $k) => ['live' => $k === $live] + $e);
        })->sortBy(fn ($e) => $e['start']->timestamp)->values();

        $rooms = Room::whereIn('id', $events->pluck('room_id')->unique())
            ->with('participants:id,name,avatar')
            ->get()
            ->keyBy('id');

        return $events
            ->filter(fn ($e) => $rooms->has($e['room_id']))
            ->map(fn (array $e) => $this->describe($e, $rooms[$e['room_id']], $byId->get($e['id'])))
            ->values();
    }

    /** Вхождение занятия в виде, удобном для экранов учителя. */
    public function describe(array $e, Room $room, ?RoomSchedule $schedule): array
    {
        $participants = $room->participants;
        $group = $participants->count() > 1 || $room->type === 'group';
        $student = $group ? null : $participants->first();

        return [
            'key' => $e['room_id'] . '-' . $e['start']->timestamp,
            'roomId' => $room->id,
            'scheduleId' => $schedule?->id,
            'title' => $room->name,
            'heading' => $student ? $room->name . ' · ' . $student->name : $room->name,
            'group' => $group,
            'student' => $student,
            'participants' => $participants,
            'count' => $participants->count(),
            'start' => $e['start'],
            'end' => $e['end'],
            'duration' => (int) $e['duration'],
            'repeat' => self::repeatLabel($schedule),
            'running' => (bool) $e['live'],
            'past' => ! $e['live'] && $e['end']->isPast(),
        ];
    }

    /** «каждый четверг», «по вт и чт», «каждый день», «раз в месяц», «разовое занятие». */
    public static function repeatLabel(?RoomSchedule $schedule): ?string
    {
        if (! $schedule) {
            return null;
        }

        if ($schedule->type === 'once') {
            return 'разовое занятие';
        }

        $days = collect($schedule->recurrence_days ?? [])->map(fn ($d) => (int) $d)
            ->sortBy(fn ($d) => $d === 0 ? 7 : $d)->values();

        return match ($schedule->recurrence_type) {
            'daily' => 'каждый день',
            'monthly' => 'раз в месяц',
            'weekly' => match (true) {
                $days->count() === 1 => ['каждое воскресенье', 'каждый понедельник', 'каждый вторник', 'каждую среду',
                    'каждый четверг', 'каждую пятницу', 'каждую субботу'][$days->first()],
                $days->count() === 7 => 'каждый день',
                $days->isEmpty() => null,
                default => 'по ' . self::joinAnd($days->map(fn ($d) => ['вс', 'пн', 'вт', 'ср', 'чт', 'пт', 'сб'][$d])->all()),
            },
            default => null,
        };
    }

    /** «вт и чт», «пн, ср и пт». */
    public static function joinAnd(array $items): string
    {
        $last = array_pop($items);

        return $items ? implode(', ', $items) . ' и ' . $last : (string) $last;
    }

    /**
     * Ближайшее вхождение занятия: идущее сейчас или следующее по расписанию (в пределах года).
     *
     * @return array{start:Carbon, end:Carbon, schedule:?RoomSchedule}|null
     */
    public function nextOccurrence(Room $room): ?array
    {
        $best = null;

        foreach ($room->schedules()->where('is_active', true)->get() as $schedule) {
            $start = $schedule->getNextOccurrence();

            if ($start && (! $best || $start->lt($best['start']))) {
                $best = [
                    'start' => $start->copy(),
                    'end' => $start->copy()->addMinutes($schedule->duration_minutes ?: self::DEFAULT_DURATION),
                    'schedule' => $schedule,
                ];
            }
        }

        return $best;
    }

    /** Завершённые занятия учителя в интервале — для вкладки «Прошедшие» и отчётов. */
    public function sessions(int $teacherId, CarbonInterface $from, ?CarbonInterface $to = null, ?int $roomId = null): Collection
    {
        return MeetingSession::query()
            ->where('status', 'completed')
            ->whereNotNull('started_at')
            ->where('started_at', '>=', $from)
            ->when($to, fn ($q) => $q->where('started_at', '<=', $to))
            ->whereHas('room', fn ($q) => $q->withTrashed()->where('user_id', $teacherId))
            ->when($roomId, fn ($q) => $q->where('room_id', $roomId))
            ->with(['room' => fn ($q) => $q->withTrashed()->with('participants:id,name,avatar')])
            ->orderByDesc('started_at')
            ->get();
    }

    /** ID занятий (MeetingSession), по которым есть просроченная оплата. */
    public static function overdueSessionIds(int $teacherId, Collection $sessionIds): array
    {
        return PaymentRecord::overdue()
            ->where('teacher_id', $teacherId)
            ->whereIn('meeting_session_id', $sessionIds)
            ->pluck('meeting_session_id')
            ->unique()
            ->all();
    }

    /** Фактическая длительность проведённого занятия, минут. */
    public static function sessionMinutes(MeetingSession $session): int
    {
        return $session->started_at && $session->ended_at
            ? max(1, (int) round($session->started_at->diffInSeconds($session->ended_at) / 60))
            : 0;
    }

    /**
     * Посещаемость проведённого занятия: кто из учеников был (снимок на момент завершения).
     *
     * @return array{attended:int, total:int, students:Collection<int, array{id:int, name:string, attended:bool, price:?int}>}
     */
    public static function attendance(MeetingSession $session): array
    {
        $snapshot = $session->pricing_snapshot['participants'] ?? null;

        if (is_array($snapshot)) {
            $students = collect($snapshot)->map(fn ($p) => [
                'id' => (int) ($p['user_id'] ?? 0),
                'name' => (string) ($p['name'] ?? ''),
                'attended' => (bool) ($p['attended'] ?? false),
                'price' => isset($p['price']) ? (int) $p['price'] : null,
            ]);
        } else {
            $students = ($session->room?->participants ?? collect())->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'attended' => $session->attendedBy($u->id),
                'price' => null,
            ]);
        }

        return [
            'attended' => $students->where('attended', true)->count(),
            'total' => $students->count(),
            'students' => $students->values(),
        ];
    }
}
