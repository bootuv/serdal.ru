<?php

namespace App\Demo;

use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Выдуманный мир демо-кабинета на странице «О платформе»: учитель, ученики, группа и расписание.
 * Всё в памяти, без базы: модели создаются через forceFill и никогда не сохраняются.
 *
 * Время в демо «заморожено» (DemoSandbox → Carbon::setTestNow) за 12 минут до ближайшего
 * сегодняшнего занятия после обеда — в любой день и час кабинет выглядит живым.
 * Экраны собирают данные из этого класса, чтобы ученики, занятия и даты совпадали на всех экранах.
 */
final class World
{
    public const TEACHER_ID = 1;

    /** Ученики: id => [фамилия, имя, класс, предмет]. Имена — как в App\Support\IngushNames. */
    public const STUDENTS = [
        101 => ['Мальсагов', 'Рустам', '9 класс', 'Алгебра · ОГЭ'],
        102 => ['Аушева', 'Мадина', '11 класс', 'ЕГЭ, профиль'],
        103 => ['Котиев', 'Адам', '10 класс', 'Физика'],
        104 => ['Оздоева', 'Лейла', '8 класс', 'Геометрия'],
        105 => ['Плиева', 'Хава', '7 класс', 'Алгебра'],
        106 => ['Цечоев', 'Мурад', '11 класс', 'ЕГЭ, профиль'],
        107 => ['Дзейтова', 'Аминат', '11 класс', 'ЕГЭ, профиль'],
        108 => ['Гагиев', 'Тимур', '11 класс', 'ЕГЭ, профиль'],
    ];

    /**
     * Занятия (комнаты): id => [название, тип, ученики, слоты [день недели 0–6, время, минуты], цена].
     * День недели — как Carbon::dayOfWeek: 0 — воскресенье.
     */
    public const ROOMS = [
        201 => ['Алгебра · ОГЭ', 'individual', [101], [[1, '16:00', 60], [3, '16:00', 60], [5, '16:00', 60], [0, '17:00', 60]], 1500],
        202 => ['Подготовка к ЕГЭ', 'individual', [102], [[2, '17:30', 90], [4, '17:30', 90], [6, '16:00', 90]], 2000],
        203 => ['Физика', 'individual', [103], [[2, '19:30', 60], [5, '19:30', 60]], 1500],
        204 => ['Геометрия', 'individual', [104], [[2, '14:00', 60], [4, '14:00', 60]], 1500],
        205 => ['Алгебра', 'individual', [105], [[3, '11:00', 45], [6, '11:00', 45]], 1200],
        206 => ['ЕГЭ по профильной математике', 'group', [106, 107, 108], [[1, '18:00', 90], [3, '18:00', 90], [5, '18:00', 90], [0, '12:00', 120]], 5000],
    ];

    /** Демо-«сейчас»: за 12 минут до первого сегодняшнего занятия после 14:00. */
    public static function now(): Carbon
    {
        $today = Carbon::today(config('app.timezone'));
        $starts = collect(self::ROOMS)
            ->flatMap(fn (array $room) => $room[3])
            ->filter(fn (array $slot) => $slot[0] === $today->dayOfWeek && $slot[1] > '14:00')
            ->map(fn (array $slot) => $slot[1])
            ->sort();

        return $today->copy()->setTimeFromTimeString($starts->first() ?? '15:00')->subMinutes(12);
    }

    public static function teacher(): User
    {
        return self::user(self::TEACHER_ID, 'Мальсагова', 'Зарема', User::ROLE_TUTOR, [
            'email' => 'zarema.malsagova@example.com',
            'username' => null,
            'is_active' => true,
            'is_profile_completed' => true,
        ]);
    }

    /** Ученик моделью (для x-ui.avatar, $lesson['student'] и т. п.). */
    public static function student(int $id): User
    {
        [$last, $first] = self::STUDENTS[$id];

        return self::user($id, $last, $first, User::ROLE_STUDENT, ['email' => self::email($id)]);
    }

    /** @return Collection<int, User> */
    public static function students(): Collection
    {
        return collect(array_keys(self::STUDENTS))->mapWithKeys(fn (int $id) => [$id => self::student($id)]);
    }

    public static function email(int $studentId): string
    {
        $map = [101 => 'islam.evloev', 102 => 'madina.ausheva', 103 => 'adam.kotiev', 104 => 'leyla.ozdoeva',
            105 => 'khava.plieva', 106 => 'murad.tsechoev', 107 => 'aminat.dzeytova', 108 => 'timur.gagiev'];

        return ($map[$studentId] ?? 'student' . $studentId) . '@example.com';
    }

    /** Комната ученика (индивидуальная) или null. */
    public static function roomOf(int $studentId): ?int
    {
        foreach (self::ROOMS as $id => $room) {
            if (in_array($studentId, $room[2], true)) {
                return $id;
            }
        }

        return null;
    }

    /**
     * Вхождения расписания в формате TeacherScheduleService::lessons() (см. describe()) —
     * экраны могут отдавать их в те же трейты, что и настоящие компоненты (Concerns\LessonRows).
     *
     * @return Collection<int, array>
     */
    public static function lessons(CarbonInterface $from, CarbonInterface $to): Collection
    {
        $now = Carbon::now();
        $result = collect();

        for ($day = Carbon::instance($from)->startOfDay(); $day->lte($to); $day->addDay()) {
            foreach (self::ROOMS as $roomId => [$title, $type, $studentIds, $slots]) {
                foreach ($slots as [$dow, $time, $minutes]) {
                    if ($dow !== $day->dayOfWeek) {
                        continue;
                    }

                    $start = $day->copy()->setTimeFromTimeString($time);
                    $end = $start->copy()->addMinutes($minutes);
                    if ($end->lt($from) || $start->gt($to)) {
                        continue;
                    }

                    $participants = collect($studentIds)->map(fn (int $id) => self::student($id));
                    $group = $type === 'group';
                    $student = $group ? null : $participants->first();

                    $result->push([
                        'key' => $roomId . '-' . $start->timestamp,
                        'roomId' => $roomId,
                        'scheduleId' => $roomId,
                        'scheduleType' => 'weekly',
                        'title' => $title,
                        'heading' => $student ? $title . ' · ' . $student->name : $title,
                        'group' => $group,
                        'student' => $student,
                        'participants' => $participants,
                        'count' => $participants->count(),
                        'start' => $start,
                        'end' => $end,
                        'duration' => $minutes,
                        'repeat' => self::repeatLabel($roomId),
                        'running' => false,
                        'past' => $end->lt($now),
                        'cancelled' => false,
                        'reason' => null,
                        'movedFrom' => null,
                        'originalStart' => $start,
                        'extra' => false,
                        'trial' => false,
                    ]);
                }
            }
        }

        return $result->sortBy(fn (array $l) => $l['start']->timestamp)->values();
    }

    /** «по пн, ср и пт» — как TeacherScheduleService::repeatLabel. */
    public static function repeatLabel(int $roomId): string
    {
        $short = ['вс', 'пн', 'вт', 'ср', 'чт', 'пт', 'сб'];
        $days = collect(self::ROOMS[$roomId][3])->map(fn (array $s) => $s[0])->unique()
            ->sortBy(fn (int $d) => $d === 0 ? 7 : $d)->map(fn (int $d) => $short[$d])->values();

        if ($days->count() === 1) {
            return 'каждый ' . ['воскресенье', 'понедельник', 'вторник', 'среду', 'четверг', 'пятницу', 'субботу'][self::ROOMS[$roomId][3][0][0]];
        }

        return 'по ' . $days->slice(0, -1)->implode(', ') . ' и ' . $days->last();
    }

    private static function user(int $id, string $last, string $first, string $role, array $extra = []): User
    {
        $user = new User;
        $user->forceFill([
            'id' => $id,
            'name' => $last . ' ' . $first,
            'last_name' => $last,
            'first_name' => $first,
            'role' => $role,
            'avatar' => null,
            'created_at' => Carbon::now()->subMonths(5),
        ] + $extra);
        $user->exists = true;
        $user->syncOriginal();

        return $user;
    }
}
