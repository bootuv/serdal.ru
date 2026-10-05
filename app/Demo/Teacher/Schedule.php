<?php

namespace App\Demo\Teacher;

use App\Demo\Concerns\ScheduleDemoData;
use App\Demo\Concerns\TeacherModals;
use App\Demo\Screen;
use App\Demo\World;
use App\Livewire\Cabinet\Teacher\Concerns\LessonRows;
use App\Services\TeacherScheduleService;
use App\Support\HumanDate;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * «Расписание» учителя — App\Livewire\Cabinet\Teacher\Schedule: список (предстоящие и прошедшие), неделя, месяц,
 * фильтр по ученику или группе, листание периодов, окно «Запланировать занятие».
 *
 * Состояние в адресе — как у настоящего экрана (tab, view, who, date) плюс step: «‹ ›» листают от date на step недель или месяцев.
 */
class Schedule extends Screen
{
    use LessonRows, ScheduleDemoData, TeacherModals;

    public const PATH = 'schedule';

    public const EXAMPLES = [
        'schedule', 'schedule?tab=past', 'schedule?tab=past&pastWeeks=4', 'schedule?view=week', 'schedule?view=week&step=1',
        'schedule?view=month', 'schedule?view=month&step=-1', 'schedule?who=s101', 'schedule?view=week&who=r206', 'schedule?open=plan',
    ];

    private const WEEKDAYS = ['Воскресенье', 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота'];

    private const QUARTER_TOP = ['top-0', 'top-4', 'top-8', 'top-12'];

    private const LANES = ['full' => 'inset-x-1', 0 => 'left-1 right-1/2', 1 => 'left-1/2 right-1'];

    private const TONES = [
        0 => 'bg-av-1/40 border-l-4 border-av-1',
        1 => 'bg-av-2/40 border-l-4 border-av-2',
        2 => 'bg-av-3/40 border-l-4 border-av-3',
        'group' => 'bg-soft border-l-4 border-line-strong',
        'off' => 'bg-white text-muted line-through shadow-line',
    ];

    private const SWATCHES = [0 => 'bg-av-1', 1 => 'bg-av-2', 2 => 'bg-av-3', 'group' => 'bg-line-strong'];

    /** Прошедшие: учитель «работает» на платформе с весны — раньше восьми недель занятий нет. */
    private const PAST_WEEKS_MAX = 8;

    public string $view = 'livewire.cabinet.teacher.schedule';

    public string $title = 'Расписание';

    public ?string $active = 'schedule';

    private function tab(): string
    {
        return in_array($t = $this->state('tab', 'upcoming'), ['upcoming', 'past'], true) ? $t : 'upcoming';
    }

    private function mode(): string
    {
        return in_array($v = $this->state('view', 'list'), ['list', 'week', 'month'], true) ? $v : 'list';
    }

    private function who(): string
    {
        return preg_match('/^(all|[sr]\d+)$/', $w = $this->state('who', 'all')) ? $w : 'all';
    }

    /** Показанная неделя или месяц: date + step. */
    private function anchor(): Carbon
    {
        $date = $this->state('date', '');
        try {
            $anchor = $date !== '' ? Carbon::createFromFormat('Y-m-d', $date)->startOfDay() : Carbon::today();
        } catch (\Throwable) {
            $anchor = Carbon::today();
        }

        $step = max(-24, min(24, $this->state('step', 0)));

        return $this->mode() === 'month' ? $anchor->addMonthsNoOverflow($step) : $anchor->addWeeks($step);
    }

    public function actions(): array
    {
        $pastWeeks = max(2, $this->state('pastWeeks', 2));

        return $this->modalActions() + [
            // «‹ ›»: от показанного периода на {0}; адрес хранит точку отсчёта и шаг
            'shift' => ['set' => ['date' => $this->anchor()->format('Y-m-d'), 'step' => '{0}']],
            'goToday' => ['set' => ['date' => null, 'step' => null]],
            'showEarlier' => ['set' => ['pastWeeks' => (string) ($pastWeeks + 2)]],
        ];
    }

    public function data(): array
    {
        $data = match (true) {
            $this->mode() === 'week' => $this->week(),
            $this->mode() === 'month' => $this->month(),
            $this->tab() === 'past' => $this->past(),
            default => $this->upcoming(),
        };

        return $data + [
            'tab' => $this->tab(),
            'view' => $this->mode(),
            'who' => $this->who(),
            'date' => $this->state('date', ''),
            'pastWeeks' => max(2, $this->state('pastWeeks', 2)),
            'whoOptions' => $this->whoOptions(),
        ] + $this->modalData();
    }

    private function whoOptions(): array
    {
        $students = World::students()->sortBy('name');
        $groups = collect(World::ROOMS)->filter(fn (array $r) => $r[1] === 'group');

        return ['all' => 'Все ученики']
            + $students->mapWithKeys(fn ($s) => ['s' . $s->id => $s->name])->all()
            + $groups->mapWithKeys(fn (array $r, int $id) => ['r' . $id => 'Группа «' . $r[0] . '»'])->all();
    }

    private function matches(int $roomId, Collection $participantIds): bool
    {
        $who = $this->who();

        return match (true) {
            $who === 'all' => true,
            $who[0] === 'r' => $roomId === (int) substr($who, 1),
            default => $participantIds->contains((int) substr($who, 1)),
        };
    }

    private function filtered(Carbon $from, Carbon $to): Collection
    {
        return self::demoLessons($from, $to)
            ->filter(fn (array $l) => $this->matches($l['roomId'], $l['participants']->pluck('id')))
            ->values();
    }

    /** Ученики с просроченной оплатой (у их занятий бейдж «Не оплачено») — Адам, как на «Сегодня». */
    private function overdueStudentIds(): array
    {
        return [103];
    }

    private function upcoming(): array
    {
        $lessons = $this->filtered(Carbon::today(), Carbon::today()->addDays(14)->endOfDay())
            ->filter(fn (array $l) => ! $l['past'] || $l['start']->isToday())
            ->values();
        $overdue = $this->overdueStudentIds();

        $active = $lessons->where('cancelled', false);
        $focus = $active->first(fn ($l) => ! $l['past'] && $l['start']->isToday());

        $rows = $lessons->map(function (array $l) use ($focus, $overdue) {
            $facts = collect([$l['group'] ? plural_ru($l['count'], 'ученик', 'ученика', 'учеников') : null, $l['repeat']])->filter()->implode(' · ');
            $status = null;
            $marks = $this->occurrenceMarks($l);
            $opts = ['withAvatar' => true, 'withDuration' => true];

            if ($focus && $l['key'] === $focus['key']) {
                $opts += [
                    'focus' => true,
                    'status' => Str::ucfirst('начнётся ' . (HumanDate::until($l['start']) ?? 'сейчас')),
                    // Видеосвязи в демо нет: «Начать занятие» открывает экран занятия «как будто класс открыт»
                    'action' => ['kind' => 'start', 'url' => route('cabinet.teacher.lesson', ['room' => $l['roomId'], 'class' => 1])],
                ];
            } elseif ($l['cancelled']) {
                [$status, $facts] = $marks;
                $opts['dim'] = true;
            } elseif ($l['past']) {
                $held = self::demoHeld($l);
                $facts = $held ? 'Завершено · запись готова' : 'Не состоялось';
                $opts['sessionId'] = $held['id'] ?? null;
            } elseif ($marks) {
                [$status, $facts] = $marks;
            }

            $unpaid = $l['participants']->pluck('id')->intersect($overdue)->isNotEmpty();

            return $this->lessonRow($l, $opts + [
                'status' => $status,
                'facts' => $facts,
                'badge' => ! $l['past'] && ! $l['cancelled'] && $unpaid ? ['tone' => 'danger', 'text' => 'Не оплачено'] : null,
            ]);
        });

        $days = $rows->groupBy(fn ($row, $i) => $lessons[$i]['start']->format('Y-m-d'))
            ->map(fn (Collection $dayRows, string $date) => $this->dayTitle(Carbon::parse($date)) + ['rows' => $dayRows->values()]);

        return [
            'sub' => 'Ближайшие две недели · ' . plural_ru($active->where('past', false)->count(), 'занятие', 'занятия', 'занятий'),
            'days' => $days->values(),
            'focusDate' => $days->keys()->first() === Carbon::today()->format('Y-m-d') ? Carbon::today()->format('Y-m-d') : null,
        ];
    }

    private function dayTitle(Carbon $date): array
    {
        $human = HumanDate::day($date);
        $near = in_array($human, ['сегодня', 'завтра'], true);

        return [
            'date' => $date->format('Y-m-d'),
            'title' => $near ? Str::ucfirst($human) : self::WEEKDAYS[$date->dayOfWeek],
            'sub' => $near ? ['вс', 'пн', 'вт', 'ср', 'чт', 'пт', 'сб'][$date->dayOfWeek] . ', ' . HumanDate::date($date) : HumanDate::date($date),
        ];
    }

    private function past(): array
    {
        $weeks = max(2, $this->state('pastWeeks', 2));
        $since = Carbon::today()->subWeeks($weeks);
        $lessons = $this->filtered($since, Carbon::now())->filter(fn (array $l) => $l['past'] || $l['cancelled'] && $l['originalStart']->isPast());

        $rows = $lessons->map(function (array $l) {
            $student = $l['student'];
            $avatar = $student ? ['name' => $student->name, 'id' => $student->id, 'photo' => null] : ['group' => true];

            if ($l['cancelled']) {
                return [
                    'key' => 'c' . $l['key'],
                    'sort' => $l['originalStart']->timestamp,
                    'date' => $l['originalStart']->format('Y-m-d'),
                    'time' => $l['originalStart']->format('H:i'),
                    'duration' => '—',
                    'heading' => $l['heading'],
                    'avatar' => $avatar,
                    'dim' => true,
                    'facts' => 'Отменено' . ($l['reason'] ? ': ' . $l['reason'] : ''),
                    'unpaid' => false,
                    'url' => $this->lessonUrl($l['roomId'], null, $l['originalStart']),
                    'recordingUrl' => null,
                ];
            }

            $held = self::demoHeld($l);
            $attended = count($held['attended']);

            return [
                'key' => $held['id'],
                'sort' => $held['started']->timestamp,
                'date' => $held['started']->format('Y-m-d'),
                'time' => $held['started']->format('H:i'),
                'duration' => $held['minutes'] . ' мин',
                'heading' => $l['heading'],
                'avatar' => $avatar,
                'dim' => $attended === 0,
                'facts' => match (true) {
                    $l['group'] => 'Были ' . $attended . ' из ' . plural_ru($held['total'], 'ученика', 'учеников', 'учеников'),
                    $attended === 0 => 'Ученик не пришёл',
                    default => null,
                },
                'unpaid' => $held['overdue'],
                'url' => $this->lessonUrl($l['roomId'], $held['id']),
                'recordingUrl' => $attended > 0 ? route('cabinet.teacher.recordings', ['open' => $held['id']]) : null,
            ];
        })->sortByDesc('sort')->values();

        $days = $rows->groupBy('date')->map(fn (Collection $dayRows, string $date) => $this->dayTitle(Carbon::parse($date)) + ['rows' => $dayRows->values()]);
        $count = $rows->where('duration', '!=', '—')->count();

        return [
            'sub' => 'Последние ' . plural_ru($weeks, 'неделя', 'недели', 'недель') . ' · ' . plural_ru($count, 'занятие', 'занятия', 'занятий'),
            'pastDays' => $days->values(),
            'hasEarlier' => $weeks < self::PAST_WEEKS_MAX,
        ];
    }

    private function week(): array
    {
        $start = $this->anchor()->startOfWeek(Carbon::MONDAY);
        $end = $start->copy()->addDays(6)->endOfDay();
        $lessons = $this->filtered($start, $end);
        $overdue = $this->overdueStudentIds();
        $held = $lessons->filter(fn ($l) => self::demoHeld($l))->mapWithKeys(fn ($l) => [$l['key'] => true])->all();

        $firstHour = min(9, (int) ($lessons->min(fn ($l) => $l['start']->hour) ?? 9));
        $endHour = min(24, max(22, (int) ($lessons->max(fn ($l) => $l['end']->hour + ($l['end']->minute > 0 ? 1 : 0)) ?? 22)));

        $days = collect(range(0, 6))->map(function (int $i) use ($start, $lessons, $held, $overdue, $firstHour, $endHour) {
            $day = $start->copy()->addDays($i);
            $dayLessons = $lessons->filter(fn ($l) => $l['start']->isSameDay($day))->sortBy(fn ($l) => $l['start']->timestamp)->values();

            $lanes = [];
            foreach ($dayLessons as $k => $l) {
                $overlaps = $dayLessons->filter(fn ($o, $j) => $j !== $k && $o['start']->lt($l['end']) && $o['end']->gt($l['start']));
                $lanes[$k] = $overlaps->isEmpty() ? 'full' : (($overlaps->keys()->filter(fn ($j) => $j < $k)->count()) % 2);
            }

            $events = $dayLessons->map(function ($l, $k) use ($held, $overdue, $firstHour, $endHour, $lanes) {
                $slot = intdiv($l['start']->hour * 60 + $l['start']->minute + 7, 15);
                $hour = max($firstHour, intdiv($slot, 4));
                $quarters = max(1, min((int) round($l['duration'] / 15), ($endHour - $hour) * 4 - $slot % 4));

                return $this->calendarEvent($l, $held, $overdue) + [
                    'hour' => $hour,
                    'top' => self::QUARTER_TOP[$slot % 4],
                    'quarters' => $quarters,
                    'lane' => self::LANES[$lanes[$k]],
                ];
            });

            return [
                'date' => $day,
                'short' => ['вс', 'пн', 'вт', 'ср', 'чт', 'пт', 'сб'][$day->dayOfWeek],
                'isToday' => $day->isToday(),
                'events' => $events->values(),
            ];
        });

        $isCurrent = $start->isSameDay(Carbon::today()->startOfWeek(Carbon::MONDAY));
        $now = Carbon::now();
        $nowSlot = intdiv($now->hour * 60 + $now->minute + 7, 15);

        return [
            'sub' => plural_ru($lessons->where('cancelled', false)->count(), 'занятие', 'занятия', 'занятий') . ($isCurrent ? ' на этой неделе' : ''),
            'calTitle' => $start->month === $end->month ? $start->day . '–' . HumanDate::date($end) : HumanDate::date($start) . ' – ' . HumanDate::date($end),
            'weekDays' => $days,
            'hours' => range($firstHour, $endHour - 1),
            'nowLine' => $isCurrent && intdiv($nowSlot, 4) >= $firstHour && intdiv($nowSlot, 4) < $endHour
                ? ['hour' => intdiv($nowSlot, 4), 'top' => self::QUARTER_TOP[$nowSlot % 4], 'label' => 'Сейчас ' . $now->format('H:i')]
                : null,
            'isCurrent' => $isCurrent,
        ];
    }

    private function month(): array
    {
        $first = $this->anchor()->startOfMonth();
        $gridStart = $first->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $first->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        $lessons = $this->filtered($gridStart, $gridEnd);

        $cells = collect();
        for ($day = $gridStart->copy(); $day->lte($gridEnd); $day->addDay()) {
            $d = $day->copy();
            $cells->push([
                'date' => $d,
                'inMonth' => $d->month === $first->month,
                'isToday' => $d->isToday(),
                'events' => $lessons->filter(fn ($l) => $l['start']->isSameDay($d))->map(fn ($l) => $this->calendarEvent($l))->values(),
            ]);
        }

        $inMonth = $lessons->filter(fn ($l) => $l['start']->month === $first->month);
        $cancelled = $inMonth->where('cancelled', true)->count();

        return [
            'sub' => plural_ru($inMonth->count() - $cancelled, 'занятие', 'занятия', 'занятий')
                . ($cancelled ? ', ' . $cancelled . ' ' . plural_ru($cancelled, 'отменено', 'отменены', 'отменено', false) : ''),
            'calTitle' => Str::ucfirst(HumanDate::month($first)) . ($first->year === Carbon::now()->year ? ' ' . $first->year : ''),
            'cells' => $cells,
            'legend' => $this->legend($lessons),
            'isCurrent' => $first->isSameMonth(Carbon::today()),
        ];
    }

    private function legend(Collection $lessons): array
    {
        $students = $lessons->pluck('student')->filter()->unique('id')->sortBy('name');
        $groups = $lessons->where('group', true)->pluck('title')->unique()->sort()->values();

        $items = $students->groupBy(fn ($s) => $s->id % 3)->sortKeys()
            ->map(fn ($list, $tone) => ['swatch' => self::SWATCHES[$tone], 'label' => $list->pluck('name')->implode(', ')])
            ->values();

        if ($groups->isNotEmpty()) {
            $items->push([
                'swatch' => self::SWATCHES['group'],
                'label' => ($groups->count() > 1 ? 'Группы ' : 'Группа ') . $groups->map(fn ($g) => '«' . $g . '»')->implode(', '),
            ]);
        }

        return $items->all();
    }

    /** Как Schedule::calendarEvent: $held — ключи проведённых вхождений (неделя). */
    private function calendarEvent(array $l, ?array $held = null, array $overdue = []): array
    {
        $who = $l['student'] ? $l['student']->name : $l['title'];
        $parts = preg_split('/\s+/u', trim($who));
        $short = $l['student'] && count($parts) > 1 ? $parts[0] . ' ' . mb_substr($parts[1], 0, 1) . '.' : $who;
        $minutes = (int) Carbon::now()->diffInMinutes($l['start'], false);

        $state = match (true) {
            $l['cancelled'] => 'отменено',
            (bool) $l['movedFrom'] => 'Перенесено ' . TeacherScheduleService::movedFromLabel($l['movedFrom']),
            $l['running'] => 'идёт сейчас',
            $l['past'] && $held !== null => isset($held[$l['key']]) ? 'завершено' : 'не состоялось',
            ! $l['past'] && $l['participants']->pluck('id')->intersect($overdue)->isNotEmpty() => 'не оплачено',
            $minutes > 0 && $minutes <= 60 => 'через ' . $minutes . ' мин',
            $l['extra'] => 'дополнительное',
            default => null,
        };
        $base = $l['student'] ? $l['title'] : plural_ru($l['count'], 'ученик', 'ученика', 'учеников');

        return [
            'key' => $l['key'],
            'time' => $l['start']->format('H:i'),
            'title' => $who . ($l['trial'] ? ' · пробное' : ''),
            'short' => $short . match (true) {
                $l['cancelled'] => ' · отменено',
                $l['trial'] => ' · пробное',
                default => '',
            },
            'sub' => $l['cancelled'] || $l['movedFrom'] ? ($l['cancelled'] && $l['reason'] ? 'отменено · ' . $l['reason'] : $state) : collect([$base, $state])->filter()->implode(' · '),
            'running' => $l['running'],
            'past' => $l['past'] && ! $l['cancelled'],
            'cancelled' => $l['cancelled'],
            'tone' => self::TONES[match (true) {
                $l['cancelled'] => 'off',
                (bool) $l['student'] => $l['student']->id % 3,
                default => 'group',
            }],
            'url' => $this->occurrenceUrl($l),
        ];
    }
}
