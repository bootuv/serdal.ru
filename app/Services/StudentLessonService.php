<?php

namespace App\Services;

use App\Models\MeetingSession;
use App\Models\PaymentRecord;
use App\Models\Recording;
use App\Models\Room;
use App\Models\RoomSchedule;
use App\Models\RoomScheduleException;
use App\Models\User;
use App\Support\HumanDate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Занятие глазами ученика: доступ, ближайшее вхождение (с отменой или переносом), правила расписания
 * и прошедшие занятия. Используется страницей занятия ученика (App\Livewire\Cabinet\Student\Lesson).
 */
class StudentLessonService
{
    /** На сколько дней вперёд ищем ближайшее занятие с учётом отмен (дальше — по Room::nextOccurrenceDetails). */
    private const HORIZON_DAYS = 60;

    public function __construct(private StudentScheduleService $schedule) {}

    /** Занятие, в котором ученик — участник (в том числе архивное), иначе null. Как Filament Student/RoomResource. */
    public function room(User $student, int $roomId): ?Room
    {
        return Room::withTrashed()
            ->whereKey($roomId)
            ->whereHas('participants', fn ($q) => $q->where('users.id', $student->id))
            ->with('user')
            ->first();
    }

    /**
     * Ближайшее занятие, которое ещё не закончилось: обычное, перенесённое или отменённое (с причиной).
     * У отменённого following — начало следующего неотменённого.
     *
     * @return array{start:Carbon, end:Carbon, duration:int, cancelled:bool, reason:?string, moved_from:?Carbon, following:?array}|null
     */
    public function nearest(Room $room): ?array
    {
        if ($room->trashed()) {
            return null;
        }

        $schedules = $room->schedules()->where('is_active', true)->with('exceptions')->get()
            ->each(fn (RoomSchedule $s) => $s->setRelation('room', $room));

        $events = $this->schedule->occurrences($schedules, today(), now()->addDays(self::HORIZON_DAYS), withCancelled: true)
            ->filter(fn (array $e) => $e['end']->isFuture())
            ->values();

        $first = $events->first();

        if (! $first) {
            // Правило со следующим занятием дальше горизонта (например, раз в месяц после каникул)
            $next = $room->nextOccurrenceDetails();

            return $next ? [
                'start' => $next['start'],
                'end' => $next['start']->copy()->addMinutes($next['duration']),
                'duration' => $next['duration'],
                'cancelled' => false,
                'reason' => null,
                'moved_from' => $next['exception']?->isMoved() ? $next['original'] : null,
                'following' => null,
            ] : null;
        }

        $following = $first['cancelled'] ? $events->first(fn (array $e) => ! $e['cancelled']) : null;

        return [
            'start' => $first['start'],
            'end' => $first['end'],
            'duration' => $first['duration'],
            'cancelled' => $first['cancelled'],
            'reason' => $first['reason'],
            'moved_from' => $first['moved_from'],
            'following' => $following ? ['start' => $following['start'], 'end' => $following['end']] : null,
        ];
    }

    /**
     * Правила расписания («Каждый четверг в 16:00 · 60 минут») и ближайшие изменения (отмены и переносы).
     *
     * @return Collection<int, array{key:string, title:string, sub:string, cancelled:bool}>
     */
    public function rules(Room $room): Collection
    {
        if ($room->trashed()) {
            return collect();
        }

        $schedules = $room->schedules()->where('is_active', true)->with('exceptions')->get();
        $now = now();

        $rules = $schedules
            ->filter(fn (RoomSchedule $s) => $s->type !== 'once'
                || ($s->scheduled_at && $s->scheduled_at->copy()->addMinutes($s->minutes())->gt($now)))
            ->sortBy(fn (RoomSchedule $s) => $s->type === 'once' ? '1' . $s->scheduled_at->timestamp : '0' . $s->recurrence_time)
            ->map(fn (RoomSchedule $s) => $s->type === 'once'
                ? [
                    'key' => 'r' . $s->id,
                    'title' => Str::ucfirst(HumanDate::at($s->scheduled_at)),
                    'sub' => 'Разовое · ' . self::minutes($s->minutes()),
                    'cancelled' => false,
                ]
                : [
                    'key' => 'r' . $s->id,
                    'title' => Str::ucfirst((string) TeacherScheduleService::repeatLabel($s)) . ' в ' . substr((string) $s->recurrence_time, 0, 5),
                    'sub' => implode(' · ', array_filter([
                        self::minutes($s->minutes()),
                        $s->start_date && $s->start_date->isFuture() ? 'с ' . HumanDate::date($s->start_date) : null,
                        $s->end_date ? 'до ' . HumanDate::date($s->end_date) : null,
                    ])),
                    'cancelled' => false,
                ]);

        // Ближайшие отмены и переносы
        $changes = $schedules->flatMap(fn (RoomSchedule $s) => $s->exceptions->map(fn (RoomScheduleException $e) => [$s, $e]))
            ->filter(fn (array $p) => $p[1]->isCancelled()
                ? $p[1]->original_starts_at->gt($now)
                : $p[1]->starts_at && $p[1]->starts_at->copy()->addMinutes($p[1]->duration_minutes ?: $p[0]->minutes())->gt($now))
            ->sortBy(fn (array $p) => ($p[1]->isCancelled() ? $p[1]->original_starts_at : $p[1]->starts_at)->timestamp)
            ->take(3)
            ->map(fn (array $p) => $p[1]->isCancelled()
                ? [
                    'key' => 'e' . $p[1]->id,
                    'title' => Str::ucfirst(HumanDate::at($p[1]->original_starts_at)),
                    'sub' => 'Отменено' . ($p[1]->reason ? ': ' . $p[1]->reason : ''),
                    'cancelled' => true,
                ]
                : [
                    'key' => 'e' . $p[1]->id,
                    'title' => Str::ucfirst(HumanDate::at($p[1]->starts_at)),
                    'sub' => 'Перенесено ' . TeacherScheduleService::movedFromLabel($p[1]->original_starts_at),
                    'cancelled' => false,
                ]);

        return $rules->concat($changes)->values();
    }

    /**
     * Прошедшие занятия: проведённые (было ли у ученика, длительность, долг, запись) и отменённые учителем — с причиной.
     * Новые сверху, не больше $limit.
     *
     * @return array{items: Collection<int, array>, hasMore: bool}
     */
    public function past(User $student, Room $room, int $limit): array
    {
        $sessions = MeetingSession::query()
            ->where('room_id', $room->id)
            ->where('status', 'completed')
            ->whereNotNull('started_at')
            ->orderByDesc('started_at')
            ->limit($limit + 1)
            ->get();

        $cancelled = RoomScheduleException::query()
            ->where('room_id', $room->id)
            ->where('status', RoomScheduleException::STATUS_CANCELLED)
            ->where('original_starts_at', '<=', now())
            ->orderByDesc('original_starts_at')
            ->limit($limit + 1)
            ->get();

        $overdue = PaymentRecord::overdue()
            ->where('student_id', $student->id)
            ->whereIn('meeting_session_id', $sessions->pluck('id'))
            ->pluck('meeting_session_id')
            ->all();

        // Записи с видео, которые ученик может открыть (только занятия, где он участник)
        $recordings = $sessions->isEmpty() ? collect() : Recording::forStudent($student)
            ->whereNotNull('s3_url')
            ->where('meeting_id', $room->meeting_id)
            ->where('start_time', '>=', $sessions->min('started_at')->copy()->subDay())
            ->get();

        $held = $sessions->map(function (MeetingSession $s) use ($student, $overdue, $recordings) {
            $attended = $s->attendedBy($student->id);
            $recording = $recordings->first(fn (Recording $r) => $r->belongsToSession($s));

            return [
                'key' => 's' . $s->id,
                'at' => $s->started_at,
                'title' => Str::ucfirst(HumanDate::at($s->started_at)),
                'sub' => $attended ? self::minutes(TeacherScheduleService::sessionMinutes($s)) : 'Вы не были на занятии',
                'muted' => ! $attended,
                'cancelled' => false,
                'unpaid' => in_array($s->id, $overdue, true),
                'recordingId' => $recording?->id,
            ];
        });

        $off = $cancelled->map(fn (RoomScheduleException $e) => [
            'key' => 'c' . $e->id,
            'at' => $e->original_starts_at,
            'title' => Str::ucfirst(HumanDate::at($e->original_starts_at)),
            'sub' => 'Отменено' . ($e->reason ? ': ' . $e->reason : ''),
            'muted' => true,
            'cancelled' => true,
            'unpaid' => false,
            'recordingId' => null,
        ]);

        $all = $held->concat($off)->sortByDesc(fn (array $r) => $r['at']->timestamp)->values();

        return [
            'items' => $all->take($limit)->values(),
            'hasMore' => $all->count() > $limit,
        ];
    }

    /** «60 минут», «1 минута». */
    public static function minutes(int $minutes): string
    {
        return plural_ru($minutes, 'минута', 'минуты', 'минут');
    }
}
