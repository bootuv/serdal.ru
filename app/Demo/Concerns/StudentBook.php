<?php

namespace App\Demo\Concerns;

use App\Demo\World;
use App\Models\User;
use App\Services\PaymentRecordService;
use App\Services\TeacherStudentsService;
use App\Support\HumanDate;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Записи о каждом ученике демо-мира (101–108) для экранов «Ученики» и «Ученик»:
 * с какого дня в списке, контакты, прошедшие занятия с посещением, начисления, задания, отзыв.
 *
 * Всё считается от World::lessons() и Carbon::now(), поэтому даты совпадают с «Сегодня» и «Расписанием».
 * Долги — как на «Сегодня»: у Адама (103) два неоплаченных занятия, одно уже просрочено,
 * у Лейлы (104) одно неоплаченное в срок; остальные всё оплатили.
 */
trait StudentBook
{
    /** Срок оплаты занятия, дней (PaymentRecordService::PER_LESSON_DUE_DAYS). */
    private const DUE_DAYS = 3;

    /**
     * id => [дней в списке, телефон, Telegram, задания в срок [сдано в срок, всего], качество знаний %, отзыв].
     * Отзыв: ['rating', дней назад] | ['asked', дней назад] | 'can'.
     */
    private const BOOK = [
        101 => [150, '+7 928 091-44-17', 'islam_evloev', [17, 19], 84, ['rating', 5, 40]],
        102 => [140, null, 'madina_au', [21, 22], 88, ['rating', 5, 25]],
        103 => [95, '+7 938 015-62-30', null, [9, 13], 71, 'can'],
        104 => [60, null, 'leyla_oz', [8, 9], 86, ['asked', 6]],
        105 => [40, '+7 928 733-08-51', null, [5, 5], 93, 'can'],
        106 => [120, null, 'murad_ts', [12, 15], 74, ['rating', 5, 52]],
        107 => [120, '+7 962 640-21-19', 'aminat_dz', [15, 15], 91, ['rating', 4, 18]],
        108 => [110, null, null, [10, 14], 68, 'can'],
    ];

    /** Ученики, которых можно найти на Serdal во вкладке «Уже на Serdal» (их ещё нет в списке). */
    private const AVAILABLE = [
        111 => ['Мартазанов', 'Ибрагим', 'ibragim.martazanov@example.com'],
        112 => ['Хамхоева', 'Зарина', 'zarina.khamkhoeva@example.com'],
        113 => ['Торшхоева', 'Петимат', 'petimat.torshkhoeva@example.com'],
        114 => ['Албогачиева', 'Луиза', 'luiza.albogachieva@example.com'],
    ];

    /** Модель ученика с контактами (для x-ui.avatar и блока «Контакты»). */
    protected function pupil(int $id): User
    {
        $user = World::student($id);
        [, $phone, $telegram] = self::BOOK[$id];
        $user->forceFill(['phone' => $phone, 'telegram' => $telegram, 'whatsup' => null, 'username' => null]);
        $user->syncOriginal();

        return $user;
    }

    protected function since(int $id): Carbon
    {
        return Carbon::today()->subDays(self::BOOK[$id][0]);
    }

    protected function firstName(int $id): string
    {
        return World::STUDENTS[$id][1];
    }

    protected function roomIds(int $id): array
    {
        return collect(World::ROOMS)->filter(fn (array $r) => in_array($id, $r[2], true))->keys()->all();
    }

    protected function isGroupRoom(int $roomId): bool
    {
        return World::ROOMS[$roomId][1] === 'group';
    }

    protected function price(int $id): int
    {
        return World::ROOMS[World::roomOf($id)][4];
    }

    protected function monthly(int $id): bool
    {
        return $this->isGroupRoom(World::roomOf($id));
    }

    /** Псевдослучайное, но постоянное число 0..$mod-1 для занятия. */
    private function dice(string $key, int $mod): int
    {
        return crc32($key) % $mod;
    }

    /**
     * Прошедшие занятия ученика с первого дня в списке, новые сверху: вхождение World::lessons + attended, activity, minutes.
     *
     * @return Collection<int, array>
     */
    protected function pastLessons(int $id): Collection
    {
        static $cache = [];

        return $cache[$id . '-' . Carbon::now()->timestamp] ??= World::lessons($this->since($id), Carbon::now())
            ->filter(fn (array $l) => $l['past'] && $l['participants']->contains('id', $id))
            ->sortByDesc(fn (array $l) => $l['start']->timestamp)
            ->values()
            ->map(function (array $l, int $i) use ($id) {
                $key = $l['key'] . '-' . $id;
                // Пропуски — редко и не в последних двух занятиях (по ним начисления)
                $attended = $i < 2 || $this->dice($key, 11) !== 0;

                return $l + [
                    'attended' => $attended,
                    'activity' => $attended ? 5 + $this->dice($key, 5) : 0,
                    'minutes' => $l['duration'] - $this->dice($key . 'm', 4),
                ];
            });
    }

    /**
     * Неоплаченные начисления ученика, старые сверху: id, title, start, due, overdue, amount.
     *
     * @return Collection<int, array>
     */
    protected function unpaid(int $id): Collection
    {
        $count = ['103' => 2, '104' => 1][(string) $id] ?? 0;

        return $this->pastLessons($id)->where('attended', true)->take($count)->reverse()->values()
            ->map(function (array $l) use ($id) {
                $due = $l['start']->copy()->startOfDay()->addDays(self::DUE_DAYS);
                // У Лейлы долг всегда в срок — как на «Сегодня»
                if ($id === 104 && $due->lt(Carbon::today())) {
                    $due = Carbon::today()->addDay();
                }

                return [
                    'id' => $l['roomId'] * 1000 + $l['start']->dayOfYear,
                    'title' => $l['title'] . ' · ' . HumanDate::day($l['end']),
                    'start' => $l['start'],
                    'due' => $due,
                    'overdue' => $due->lt(Carbon::today()),
                    'amount' => $this->price($id),
                ];
            });
    }

    /** Состояние оплаты, как TeacherStudentsService::paymentState: paid | unpaid | overdue. */
    protected function payState(int $id): string
    {
        $unpaid = $this->unpaid($id);

        return match (true) {
            $unpaid->isEmpty() => 'paid',
            $unpaid->contains('overdue', true) => 'overdue',
            default => 'unpaid',
        };
    }

    /** «2 занятия · 3 000 ₽» (TeacherStudentsService::countLabel + amountLabel). */
    protected function debtLabel(Collection $records): string
    {
        return implode(' · ', array_filter([
            plural_ru($records->count(), 'занятие', 'занятия', 'занятий'),
            $this->sumLabel($records),
        ]));
    }

    protected function sumLabel(Collection $records): ?string
    {
        $sum = (int) $records->sum('amount');

        return $sum ? TeacherStudentsService::rub($sum) : null;
    }

    /** PaymentRecordService::debtStatus: сколько ещё занятий с долгом до закрытия входа. */
    protected function debtStatus(int $id): ?array
    {
        $first = $this->unpaid($id)->firstWhere('overdue', true);
        if (! $first) {
            return null;
        }

        $after = $this->pastLessons($id)->filter(fn (array $l) => $l['attended'] && $l['start']->gt($first['due']))->count();

        return ['blocked' => false, 'lessons_left' => max(1, PaymentRecordService::BLOCK_AFTER_LESSONS - $after)];
    }

    /**
     * История оплат, новые сверху: оплаченные занятия (или месяцы у группы) и «оплата не требуется».
     *
     * @return Collection<int, array>
     */
    protected function payHistory(int $id): Collection
    {
        if ($this->monthly($id)) {
            $months = collect(range(1, 4))->map(fn (int $i) => Carbon::today()->startOfMonth()->subMonthsNoOverflow($i));
            if (Carbon::today()->day >= 4) {
                $months->prepend(Carbon::today()->startOfMonth());
            }

            return $months->filter(fn (Carbon $m) => $m->copy()->endOfMonth()->gte($this->since($id)))
                ->values()
                ->map(fn (Carbon $m, int $i) => [
                    'id' => 9000 + $m->month,
                    'title' => 'Оплата за ' . HumanDate::month($m),
                    'waived' => false,
                    'sub' => 'Оплачено ' . HumanDate::date($m->copy()->addDays(1 + $this->dice($id . $m->month, 3))),
                    'amount' => TeacherStudentsService::rub($this->price($id)),
                    'canUndo' => true,
                ]);
        }

        $unpaid = $this->unpaid($id)->pluck('start')->map->timestamp->all();

        return $this->pastLessons($id)
            ->where('attended', true)
            ->reject(fn (array $l) => in_array($l['start']->timestamp, $unpaid, true))
            ->take(12)
            ->values()
            ->map(function (array $l, int $i) use ($id) {
                // Первое занятие Рустама — пробное, без оплаты; у Адама одно оплачено позже срока
                $waived = $id === 101 && $l['start']->lte($this->since($id)->copy()->addDays(7));
                $late = $id === 103 && $i === 2;
                $paid = $l['start']->copy()->addDays($late ? 5 : $this->dice($l['key'], 3));

                return [
                    'id' => $l['roomId'] * 1000 + $l['start']->dayOfYear,
                    'title' => $l['title'] . ' · ' . HumanDate::day($l['end']),
                    'waived' => $waived,
                    'sub' => $waived ? null : 'Оплачено ' . HumanDate::date($paid) . ($late ? ', позже срока' : ''),
                    'amount' => $waived ? null : TeacherStudentsService::rub($this->price($id)),
                    'canUndo' => ! $waived,
                ];
            });
    }

    /** Условия оплаты (TeacherStudentsService::paymentTerms). */
    protected function terms(int $id): array
    {
        $perLesson = 'оплата в течение ' . plural_ru(self::DUE_DAYS, 'дня', 'дней', 'дней') . ' после занятия';
        $monthly = 'счёт за месяц, если в нём есть занятия, оплатить до ' . PaymentRecordService::MONTHLY_DUE_DAY . '-го';
        $isMonthly = $this->monthly($id);

        return [
            'line' => $isMonthly
                ? 'За месяц · ' . TeacherStudentsService::rub($this->price($id)) . ' · ' . $monthly
                : 'За каждое занятие · ' . TeacherStudentsService::rub($this->price($id)) . ' · ' . $perLesson,
            'note' => 'Как в ваших базовых ценах. ' . TeacherStudentsService::blockRule(),
            'options' => [
                'default' => ['title' => 'Как в ваших ценах', 'sub' => $isMonthly ? 'Сейчас — за месяц' : 'Сейчас — за каждое занятие'],
                'per_lesson' => ['title' => 'За каждое занятие', 'sub' => 'Счёт после занятия, оплатить за ' . plural_ru(self::DUE_DAYS, 'день', 'дня', 'дней')],
                'monthly' => ['title' => 'За месяц', 'sub' => 'Один счёт за месяц, оплатить до ' . PaymentRecordService::MONTHLY_DUE_DAY . '-го'],
            ],
        ];
    }

    /** Успеваемость (StudentPerformanceService::metrics) и подпись «за всё время · 34 занятия». */
    protected function performance(int $id): array
    {
        $past = $this->pastLessons($id);
        $total = $past->count();
        $attended = $past->where('attended', true)->count();
        [, , , [$onTime, $hwTotal], $knowledge] = self::BOOK[$id];

        return [
            'metrics' => [
                ['value' => $total ? (int) round($attended / $total * 100) : 0, 'label' => 'Посещаемость', 'empty' => ! $total,
                    'sub' => $attended . ' из ' . plural_ru($total, 'занятия', 'занятий', 'занятий')],
                ['value' => (int) round($onTime / $hwTotal * 100), 'label' => 'Задания в срок', 'empty' => false,
                    'sub' => $onTime . ' из ' . plural_ru($hwTotal, 'задания', 'заданий', 'заданий')],
                ['value' => $knowledge, 'label' => 'Качество знаний', 'empty' => false,
                    'sub' => 'средний балл за ' . plural_ru(max(1, $hwTotal - 2), 'работу', 'работы', 'работ')],
            ],
            'perfSub' => $total ? 'за всё время · ' . plural_ru($total, 'занятие', 'занятия', 'занятий') : null,
        ];
    }

    /** Блок «Отзыв» карточки (ReviewPromptService::requestState). */
    protected function reviewAsk(int $id): array
    {
        $review = self::BOOK[$id][5];

        return [
            'rating' => is_array($review) && $review[0] === 'rating' ? $review[1] : null,
            'reviewsUrl' => route('cabinet.teacher.reviews'),
            'can' => $review === 'can',
            'text' => match (true) {
                $review === 'can' => 'Отзыва пока нет',
                $review[0] === 'rating' => HumanDate::date(Carbon::today()->subDays($review[2])),
                default => 'Попросили ' . HumanDate::date(Carbon::today()->subDays($review[1])) . ' — ждём отзыв',
            },
        ];
    }

    /**
     * Задания ученика в формате Student::homework(): сначала на проверку, затем доработка, просрочка, ждут сдачи, проверенные.
     * Работы на проверку — те же, что на «Сегодня» (401–403).
     */
    protected function homework(int $id): Collection
    {
        $now = Carbon::now();
        $d = fn (int $days, string $time = '20:00') => Carbon::today()->addDays($days)->setTimeFromTimeString($time);

        // [id задания, название, состояние, сдано, срок, проверено, оценка, id работы]
        $items = match ($id) {
            101 => [
                [601, 'Квадратные уравнения, вариант 3', 'review', $now->copy()->subHours(3), $d(1), null, null, 401],
                [602, 'Неравенства с модулем', 'todo', null, $d(2), null, null, null],
                [603, 'Системы уравнений', 'graded', $d(-6), $d(-5), $d(-5), '5/5', 411],
                [604, 'ОГЭ, вариант 12', 'graded', $d(-12), $d(-11), $d(-10), '27/31', 412],
            ],
            102 => [
                [611, 'Производная: задачи 1–12', 'review', $now->copy()->subDay()->setTime(21, 15), $d(0), null, null, 402],
                [612, 'Параметры, задача 18', 'revision', $d(-2), $d(3), null, null, 421],
                [613, 'Пробный ЕГЭ, вариант 7', 'graded', $d(-8), $d(-7), $d(-6), '82/100', 422],
                [614, 'Логарифмические уравнения', 'graded', $d(-15), $d(-14), $d(-13), '9/10', 423],
            ],
            103 => [
                [621, 'Законы Ньютона, задачи 1–8', 'review', $now->copy()->subDays(3)->setTime(18, 2), $d(-2), null, null, 403],
                [622, 'Кинематика: графики движения', 'overdue', null, $d(-2), null, null, null],
                [623, 'Равноускоренное движение', 'graded', $d(-10), $d(-9), $d(-8), '7/10', 431],
            ],
            104 => [
                [631, 'Подобные треугольники', 'todo', null, $d(1, '18:00'), null, null, null],
                [632, 'Теорема Пифагора', 'graded', $d(-5), $d(-4), $d(-4), '5/5', 441],
                [633, 'Площади фигур', 'graded', $d(-12), $d(-11), $d(-10), '4/5', 442],
            ],
            105 => [
                [641, 'Линейная функция', 'todo', null, null, null, null, null],
                [642, 'Степени с натуральным показателем', 'graded', $d(-4), $d(-3), $d(-3), '5/5', 451],
            ],
            default => [
                [651, 'ЕГЭ: задание 13, тригонометрия', 'todo', null, $d(2, '21:00'), null, null, null],
                [652, 'Пробник ЕГЭ, вариант 3', 'graded', $d(-6), $d(-5), $d(-4), ['106' => '68/100', '107' => '86/100', '108' => '61/100'][(string) $id], 460 + $id],
                [653, 'Задание 15: неравенства', $id === 108 ? 'overdue' : 'graded', $id === 108 ? null : $d(-11), $d(-10), $id === 108 ? null : $d(-9), $id === 108 ? null : '2/2', 470 + $id],
            ],
        };

        return collect($items)->map(function (array $h) {
            [$hwId, $title, $state, $submitted, $deadline, $graded, $grade, $submissionId] = $h;
            $waitDays = $submitted ? (int) $submitted->copy()->startOfDay()->diffInDays(Carbon::today()) : 0;

            return [
                'id' => $hwId,
                'title' => $title,
                'state' => $state,
                'order' => ['review' => 0, 'revision' => 1, 'overdue' => 2, 'todo' => 3, 'graded' => 4][$state],
                'sort' => $state === 'todo' ? ($deadline?->timestamp ?? PHP_INT_MAX) : -($graded ?? $submitted ?? $deadline)->timestamp,
                'sub' => match ($state) {
                    'review' => 'Сдано ' . HumanDate::day($submitted),
                    'revision' => 'Вернули на доработку',
                    'graded' => 'Проверено ' . HumanDate::day($graded),
                    'overdue' => 'Срок был ' . HumanDate::date($deadline) . ' · не сдано',
                    default => $deadline ? null : 'Без срока',
                },
                'urgent' => match ($state) {
                    'review' => $waitDays > 0 ? 'ждёт ' . plural_ru($waitDays, 'день', 'дня', 'дней') : 'сдано сегодня',
                    'revision' => $deadline && $deadline->isFuture() ? 'исправить до ' . HumanDate::date($deadline) : null,
                    'todo' => $deadline ? 'Сдать до ' . HumanDate::at($deadline) : null,
                    default => null,
                },
                'grade' => $state === 'graded' ? $grade : null,
                'url' => $submissionId && $state !== 'overdue' && $state !== 'todo'
                    ? route('cabinet.teacher.review', ['submission' => $submissionId])
                    : route('cabinet.teacher.task', ['homework' => $hwId]),
            ];
        })->sortBy([['order', 'asc'], ['sort', 'asc']])->values();
    }

    /** Найденные на Serdal ученики (вкладка «Уже на Serdal»). */
    protected function availableStudents(string $query): Collection
    {
        $needle = mb_strtolower(trim($query));

        return collect(self::AVAILABLE)
            ->map(function (array $s, int $id) {
                $user = new User;
                $user->forceFill(['id' => $id, 'name' => $s[0] . ' ' . $s[1], 'last_name' => $s[0], 'first_name' => $s[1],
                    'email' => $s[2], 'role' => User::ROLE_STUDENT, 'avatar' => null]);
                $user->exists = true;

                return $user;
            })
            ->filter(fn (User $u) => $needle !== '' && (str_contains(mb_strtolower($u->name), $needle) || str_contains($u->email, $needle)))
            ->sortBy('name')
            ->values();
    }

    /** Ссылка на занятие (комнату). */
    protected function lessonUrl(int $roomId): string
    {
        return route('cabinet.teacher.lesson', ['room' => $roomId]);
    }

    /** «Сегодня в 16:00» — ближайшее занятие ученика (идущее или будущее). */
    protected function nextLesson(int $id): ?array
    {
        return World::lessons(Carbon::now(), Carbon::now()->addDays(14))
            ->first(fn (array $l) => ! $l['past'] && $l['participants']->contains('id', $id));
    }

    protected static function ucfirst(string $s): string
    {
        return Str::ucfirst($s);
    }
}
