<?php

namespace App\Livewire\Cabinet\Teacher;

use App\Livewire\Cabinet\Teacher\Concerns\LessonRows;
use App\Livewire\Cabinet\Teacher\Concerns\PlansLessons;
use App\Livewire\Cabinet\Teacher\Concerns\StartsLessons;
use App\Livewire\Cabinet\Teacher\Concerns\TeacherScreen;
use App\Models\MeetingSession;
use App\Models\PaymentRecord;
use App\Models\Room;
use App\Models\User;
use App\Services\TeacherScheduleService;
use App\Support\HumanDate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Расписание учителя. Макеты: TeacherSchedule, LsWeek, LsMonth, LsPlan (docs/design/BRAND.md). */
#[Layout('components.layouts.cabinet', ['title' => 'Расписание', 'active' => 'schedule'])]
class Schedule extends Component
{
    use LessonRows, PlansLessons, StartsLessons, TeacherScreen;

    /** На сколько дней вперёд показываем предстоящие занятия в списке. */
    private const DAYS_AHEAD = 14;

    /** Прошедшие занятия показываем порциями по две недели. */
    private const PAST_WEEKS_STEP = 2;

    private const WEEKDAYS = ['Воскресенье', 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота'];

    #[Url(except: 'upcoming')]
    public string $tab = 'upcoming';

    /** Вид: list | week | month. */
    #[Url(except: 'list')]
    public string $view = 'list';

    /** Фильтр: all | s{id} — ученик | r{id} — групповое занятие. */
    #[Url(except: 'all')]
    public string $who = 'all';

    /** Неделя или месяц, которые показаны (любая дата внутри), Y-m-d. Пусто — текущие. */
    #[Url(except: '')]
    public string $date = '';

    public int $pastWeeks = self::PAST_WEEKS_STEP;

    /** Обновляем, когда занятие начинается или завершается. */
    #[On('echo:rooms,.room.status.updated')]
    public function refreshRooms(): void {}

    public function mount(): void
    {
        $this->authorizeTeacher();
        $this->normalize();
    }

    public function updated(): void
    {
        $this->normalize();
    }

    private function normalize(): void
    {
        $this->tab = in_array($this->tab, ['upcoming', 'past'], true) ? $this->tab : 'upcoming';
        $this->view = in_array($this->view, ['list', 'week', 'month'], true) ? $this->view : 'list';
        $this->who = preg_match('/^(all|[sr]\d+)$/', $this->who) ? $this->who : 'all';

        if ($this->date !== '' && ! $this->anchor(true)) {
            $this->date = '';
        }
    }

    public function showEarlier(): void
    {
        $this->pastWeeks += self::PAST_WEEKS_STEP;
    }

    /** Предыдущая / следующая неделя или месяц. */
    public function shift(int $step): void
    {
        $anchor = $this->anchor();
        $this->date = ($this->view === 'month' ? $anchor->addMonthsNoOverflow($step) : $anchor->addWeeks($step))->format('Y-m-d');
    }

    public function goToday(): void
    {
        $this->date = '';
    }

    private function anchor(bool $check = false): ?Carbon
    {
        if ($this->date === '') {
            return today();
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $this->date)->startOfDay();
        } catch (\Throwable) {
            return $check ? null : today();
        }
    }

    public function render()
    {
        $teacher = auth()->user();
        $startBlock = $this->startBlock($teacher);

        $data = match (true) {
            $this->view === 'week' => $this->week($teacher),
            $this->view === 'month' => $this->month($teacher),
            $this->tab === 'past' => $this->past($teacher),
            default => $this->upcoming($teacher, (bool) $startBlock),
        };

        return view('livewire.cabinet.teacher.schedule', $data + [
            'whoOptions' => $this->whoOptions($teacher),
            'startBlock' => $startBlock,
        ] + $this->planView($teacher));
    }

    /** Варианты фильтра: ученики и групповые занятия учителя. */
    private function whoOptions(User $teacher): array
    {
        $rooms = Room::where('user_id', $teacher->id)->with('participants:id,name')->orderBy('name')->get();
        $students = $rooms->flatMap->participants->unique('id')->sortBy('name');
        $groups = $rooms->filter(fn (Room $r) => $r->participants->count() > 1 || $r->type === 'group');

        return ['all' => 'Все ученики']
            + $students->mapWithKeys(fn ($s) => ['s' . $s->id => $s->name])->all()
            + $groups->mapWithKeys(fn (Room $r) => ['r' . $r->id => 'Группа «' . $r->name . '»'])->all();
    }

    /** Подходит ли занятие под фильтр. */
    private function matches(int $roomId, Collection $participantIds): bool
    {
        return match (true) {
            $this->who === 'all' => true,
            $this->who[0] === 'r' => $roomId === (int) substr($this->who, 1),
            default => $participantIds->contains((int) substr($this->who, 1)),
        };
    }

    private function filteredLessons(User $teacher, Carbon $from, Carbon $to): Collection
    {
        return app(TeacherScheduleService::class)->lessons($teacher->id, $from, $to)
            ->filter(fn (array $l) => $this->matches($l['roomId'], $l['participants']->pluck('id')))
            ->values();
    }

    /** ID учеников с просроченной оплатой: у их занятий бейдж «Не оплачено». */
    private function overdueStudentIds(User $teacher): array
    {
        return PaymentRecord::overdue()->where('teacher_id', $teacher->id)->distinct()->pluck('student_id')->map(fn ($id) => (int) $id)->all();
    }

    /** Предстоящие занятия на две недели по дням; сегодняшние (включая прошедшие) — в фокусе. */
    private function upcoming(User $teacher, bool $blocked): array
    {
        $service = app(TeacherScheduleService::class);
        $lessons = $this->filteredLessons($teacher, today(), today()->addDays(self::DAYS_AHEAD)->endOfDay())
            ->filter(fn (array $l) => ! $l['past'] || $l['start']->isToday())
            ->values();

        $matched = $this->matchSessions($lessons->where('past', true), $service->sessions($teacher->id, today()));
        $recordings = $this->readyRecordings(collect(array_values($matched)));
        $overdue = $this->overdueStudentIds($teacher);
        $runningId = $this->otherRunningRoomId($teacher);

        // Первое занятие у тех, кого ещё ни разу не проводили
        $heldRoomIds = MeetingSession::whereIn('room_id', $lessons->pluck('roomId')->unique())->distinct()->pluck('room_id')->all();
        $firstSeen = [];

        $focus = $lessons->firstWhere('running', true) ?? $lessons->first(fn ($l) => ! $l['past'] && $l['start']->isToday());

        $rows = $lessons->map(function (array $l) use ($focus, $matched, $recordings, $overdue, $runningId, $blocked, $heldRoomIds, &$firstSeen) {
            $facts = collect([$l['group'] ? plural_ru($l['count'], 'ученик', 'ученика', 'учеников') : null, $l['repeat']])->filter()->implode(' · ');
            $status = null;
            $isFirst = ! $l['past'] && ! in_array($l['roomId'], $heldRoomIds, true) && ! isset($firstSeen[$l['roomId']]);
            if (! $l['past']) {
                $firstSeen[$l['roomId']] = true;
            }

            $opts = ['withAvatar' => true, 'withDuration' => true];

            if ($focus && $l['key'] === $focus['key']) {
                $opts += [
                    'focus' => true,
                    'status' => $l['running'] ? 'Идёт сейчас' : Str::ucfirst('начнётся ' . (HumanDate::until($l['start']) ?? 'сейчас')),
                    'action' => $this->startAction($l, $runningId, $blocked),
                ];
            } elseif ($l['past']) {
                $session = $matched[$l['key']] ?? null;
                $facts = $session ? 'Завершено' . (isset($recordings[$session->id]) ? ' · запись готова' : '') : 'Не состоялось';
                $opts['sessionId'] = $session?->id;
            } elseif ($isFirst) {
                $status = 'Первое занятие';
            }

            $unpaid = $l['participants']->pluck('id')->intersect($overdue)->isNotEmpty();

            return $this->lessonRow($l, $opts + [
                'status' => $status,
                'facts' => $facts,
                'badge' => ! $l['past'] && $unpaid ? ['tone' => 'danger', 'text' => 'Не оплачено'] : null,
            ]);
        });

        $days = $rows->groupBy(fn ($row, $i) => $lessons[$i]['start']->format('Y-m-d'))
            ->map(fn (Collection $dayRows, string $date) => $this->dayTitle(Carbon::parse($date)) + ['rows' => $dayRows->values()]);

        return [
            'sub' => 'Ближайшие две недели · ' . plural_ru($lessons->where('past', false)->count(), 'занятие', 'занятия', 'занятий'),
            'days' => $days->values(),
            'focusDate' => $days->keys()->first() === today()->format('Y-m-d') ? today()->format('Y-m-d') : null,
        ];
    }

    /** «Сегодня · чт, 26 сентября», «Суббота · 28 сентября». */
    private function dayTitle(Carbon $date): array
    {
        $human = HumanDate::day($date);

        return [
            'date' => $date->format('Y-m-d'),
            'title' => in_array($human, ['сегодня', 'завтра'], true) ? Str::ucfirst($human) : self::WEEKDAYS[$date->dayOfWeek],
            'sub' => in_array($human, ['сегодня', 'завтра'], true)
                ? ['вс', 'пн', 'вт', 'ср', 'чт', 'пт', 'сб'][$date->dayOfWeek] . ', ' . HumanDate::date($date)
                : HumanDate::date($date),
        ];
    }

    /** Прошедшие занятия по дням: посещаемость, долг, запись. */
    private function past(User $teacher): array
    {
        $service = app(TeacherScheduleService::class);
        $since = today()->subWeeks($this->pastWeeks);

        $sessions = $service->sessions($teacher->id, $since)
            ->filter(fn (MeetingSession $s) => $s->room && $this->matches($s->room_id, $s->room->participants->pluck('id')))
            ->values();

        $overdue = TeacherScheduleService::overdueSessionIds($teacher->id, $sessions->pluck('id'));
        $recordings = $this->readyRecordings($sessions);

        $rows = $sessions->map(function (MeetingSession $s) use ($overdue, $recordings) {
            $room = $s->room;
            $att = TeacherScheduleService::attendance($s);
            $group = $att['total'] > 1 || $room->type === 'group';
            $student = ! $group ? $att['students']->first() : null;

            return [
                'key' => $s->id,
                'date' => $s->started_at->format('Y-m-d'),
                'time' => $s->started_at->format('H:i'),
                'duration' => TeacherScheduleService::sessionMinutes($s) . ' мин',
                'heading' => $student ? $room->name . ' · ' . $student['name'] : $room->name,
                'avatar' => $student ? ['name' => $student['name'], 'id' => $student['id']] : ['group' => true],
                'dim' => $att['total'] > 0 && $att['attended'] === 0,
                'facts' => match (true) {
                    (bool) $s->deletion_requested_at => 'Запрошено удаление',
                    $group => 'Были ' . $att['attended'] . ' из ' . plural_ru($att['total'], 'ученика', 'учеников', 'учеников'),
                    $student && ! $student['attended'] => 'Ученик не пришёл',
                    default => null,
                },
                'unpaid' => in_array($s->id, $overdue, true),
                'url' => $this->lessonUrl($s->room_id, $s->id),
                'recordingUrl' => isset($recordings[$s->id]) ? $this->recordingUrl($recordings[$s->id]) : null,
            ];
        });

        $days = $rows->groupBy('date')->map(fn (Collection $dayRows, string $date) => $this->dayTitle(Carbon::parse($date)) + ['rows' => $dayRows->values()]);

        $hasEarlier = MeetingSession::where('status', 'completed')
            ->where('started_at', '<', $since)
            ->whereHas('room', fn ($q) => $q->withTrashed()->where('user_id', $teacher->id))
            ->exists();

        return [
            'sub' => 'Последние ' . plural_ru($this->pastWeeks, 'неделя', 'недели', 'недель') . ' · ' . plural_ru($sessions->count(), 'занятие', 'занятия', 'занятий'),
            'pastDays' => $days->values(),
            'hasEarlier' => $hasEarlier,
        ];
    }

    /** Неделя: семь дней с занятиями по времени. */
    private function week(User $teacher): array
    {
        $start = $this->anchor()->startOfWeek(Carbon::MONDAY);
        $end = $start->copy()->addDays(6)->endOfDay();
        $lessons = $this->filteredLessons($teacher, $start, $end);

        $days = collect(range(0, 6))->map(function (int $i) use ($start, $lessons) {
            $day = $start->copy()->addDays($i);

            return [
                'date' => $day,
                'short' => ['вс', 'пн', 'вт', 'ср', 'чт', 'пт', 'сб'][$day->dayOfWeek],
                'isToday' => $day->isToday(),
                'events' => $lessons->filter(fn ($l) => $l['start']->isSameDay($day))->map(fn ($l) => $this->calendarEvent($l))->values(),
            ];
        });

        $isCurrent = $start->isSameDay(today()->startOfWeek(Carbon::MONDAY));
        $sameMonth = $start->month === $end->month;

        return [
            'sub' => plural_ru($lessons->count(), 'занятие', 'занятия', 'занятий') . ($isCurrent ? ' на этой неделе' : ''),
            'calTitle' => $sameMonth ? $start->day . '–' . HumanDate::date($end) : HumanDate::date($start) . ' – ' . HumanDate::date($end),
            'weekDays' => $days,
            'isCurrent' => $isCurrent,
        ];
    }

    /** Месяц: сетка с понедельника, занятия в ячейках. */
    private function month(User $teacher): array
    {
        $first = $this->anchor()->startOfMonth();
        $gridStart = $first->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $first->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        $lessons = $this->filteredLessons($teacher, $gridStart, $gridEnd);

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

        $inMonth = $lessons->filter(fn ($l) => $l['start']->month === $first->month)->count();

        return [
            'sub' => plural_ru($inMonth, 'занятие', 'занятия', 'занятий'),
            'calTitle' => Str::ucfirst(HumanDate::month($first)) . ($first->year === now()->year ? ' ' . $first->year : ''),
            'cells' => $cells,
            'isCurrent' => $first->isSameMonth(today()),
        ];
    }

    private function calendarEvent(array $l): array
    {
        $who = $l['student'] ? $l['student']->name : $l['title'];

        return [
            'key' => $l['key'],
            'time' => $l['start']->format('H:i'),
            'title' => $who,
            'sub' => $l['student'] ? $l['title'] : plural_ru($l['count'], 'ученик', 'ученика', 'учеников'),
            'running' => $l['running'],
            'past' => $l['past'],
            'url' => $this->lessonUrl($l['roomId']),
        ];
    }
}
