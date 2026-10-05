<?php

namespace App\Demo\Concerns;

use App\Demo\World;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Расписание демо-учителя «как в жизни» для экранов «Расписание» и «Занятие»: вхождения World::lessons()
 * плюс исключения (одно занятие отменено, одно перенесено, одно прошедшее отменено) и проведённые занятия
 * (кто был, сколько длилось, что не оплачено). Всё считается от «сейчас», поэтому совпадает на всех экранах.
 *
 * Проведённое занятие (session) — номер минуты начала: intdiv(timestamp, 60). По нему экран занятия
 * находит, какое это было занятие, без всякой базы.
 */
trait ScheduleDemoData
{
    /** Отмены и переносы: ключ вхождения => [что, причина или новое время]. */
    protected static function demoExceptions(): array
    {
        static $cache = [];
        $day = Carbon::today()->format('Y-m-d');

        if (isset($cache[$day])) {
            return $cache[$day];
        }

        $tomorrow = Carbon::tomorrow();
        $ahead = World::lessons($tomorrow, $tomorrow->copy()->addDays(13)->endOfDay());
        $behind = World::lessons(Carbon::today()->subDays(10), Carbon::today()->subDay()->endOfDay());
        $result = [];

        // Ближайшее занятие Хавы отменено — едет на олимпиаду
        if ($l = $ahead->firstWhere('roomId', 205)) {
            $result[$l['key']] = ['cancelled', 'Хава на олимпиаде'];
        }

        // Ближайшее занятие Лейлы перенесено на час позже
        if ($l = $ahead->firstWhere('roomId', 204)) {
            $result[$l['key']] = ['moved', $l['start']->copy()->addHour()];
        }

        // На прошлой неделе Адам заболел
        if ($l = $behind->firstWhere('roomId', 203)) {
            $result[$l['key']] = ['cancelled', 'Адам заболел'];
        }

        return $cache[$day] = $result;
    }

    /** Вхождения расписания с отменами и переносами (формат TeacherScheduleService::lessons()). */
    protected static function demoLessons(CarbonInterface $from, CarbonInterface $to, bool $withCancelled = true): Collection
    {
        $exceptions = self::demoExceptions();
        $now = Carbon::now();

        return World::lessons(Carbon::instance($from)->copy()->subDay(), Carbon::instance($to)->copy()->addDay())
            ->map(function (array $l) use ($exceptions, $now) {
                [$kind, $value] = $exceptions[$l['key']] ?? [null, null];

                if ($kind === 'cancelled') {
                    $l['cancelled'] = true;
                    $l['reason'] = $value;
                } elseif ($kind === 'moved') {
                    $l['movedFrom'] = $l['start'];
                    $l['start'] = $value->copy();
                    $l['end'] = $value->copy()->addMinutes($l['duration']);
                    $l['past'] = $l['end']->lt($now);
                }

                return $l;
            })
            ->filter(fn (array $l) => $l['end']->gte($from) && $l['start']->lte($to) && ($withCancelled || ! $l['cancelled']))
            ->sortBy(fn (array $l) => $l['start']->timestamp)
            ->values();
    }

    protected static function demoSessionId(array $lesson): int
    {
        return intdiv($lesson['originalStart']->timestamp, 60);
    }

    /**
     * Проведённое занятие для прошедшего вхождения: длительность, кто был, долг. Null — отменено или ещё не прошло.
     *
     * @return array{id:int, minutes:int, attended:array<int>, total:int, overdue:bool, started:Carbon, ended:Carbon}|null
     */
    protected static function demoHeld(array $lesson): ?array
    {
        if ($lesson['cancelled'] || ! $lesson['past']) {
            return null;
        }

        $ids = $lesson['participants']->pluck('id')->all();
        $day = (int) $lesson['start']->format('j');

        // Лейла однажды не пришла (первое занятие геометрии на прошлой неделе), Тимур в группе пропускает через раз
        $attended = array_values(array_filter($ids, fn (int $id) => ! (
            ($id === 104 && $lesson['start']->between(Carbon::today()->subDays(7), Carbon::today()) && $lesson['start']->dayOfWeek === 2)
            || ($id === 108 && $day % 3 === 0)
        )));

        $minutes = $lesson['duration'] - [0, 2, 4][$day % 3] + ($lesson['group'] ? 3 : 0);
        $started = $lesson['start']->copy()->addMinutes($day % 2 ? 1 : 0);

        return [
            'id' => self::demoSessionId($lesson),
            'minutes' => $minutes,
            'attended' => $attended,
            'total' => count($ids),
            'overdue' => in_array(self::demoSessionId($lesson), self::demoOverdueSessions(), true),
            'started' => $started,
            'ended' => $started->copy()->addMinutes($minutes),
        ];
    }

    /** Просроченная оплата: два последних занятия Адама (как на «Сегодня»: 2 занятия · 3 000 ₽). */
    protected static function demoOverdueSessions(): array
    {
        static $cache = [];
        $day = Carbon::today()->format('Y-m-d');

        return $cache[$day] ??= World::lessons(Carbon::today()->subDays(21), Carbon::today()->subDay()->endOfDay())
            ->where('roomId', 203)
            ->filter(fn (array $l) => ! isset(self::demoExceptions()[$l['key']]))
            ->sortByDesc(fn (array $l) => $l['start']->timestamp)
            ->take(2)
            ->map(fn (array $l) => self::demoSessionId($l))
            ->values()
            ->all();
    }

    /** Вхождение по номеру проведённого занятия (или null, если такого не было). */
    protected static function demoSessionLesson(int $roomId, int $sessionId): ?array
    {
        $at = Carbon::createFromTimestamp($sessionId * 60, config('app.timezone'));

        return self::demoLessons($at->copy()->startOfDay(), $at->copy()->endOfDay())
            ->first(fn (array $l) => $l['roomId'] === $roomId && self::demoSessionId($l) === $sessionId && self::demoHeld($l));
    }
}
