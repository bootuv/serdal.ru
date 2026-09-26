<?php

namespace App\Livewire\Cabinet\Admin;

use App\Livewire\Cabinet\Admin\Concerns\AdminScreen;
use App\Livewire\Cabinet\Admin\Concerns\DecidesDeletions;
use App\Models\MeetingSession;
use App\Models\Recording;
use App\Models\Room;
use App\Models\SessionDeletionDecision;
use App\Models\User;
use App\Services\AdminLessonsService;
use App\Services\RecordingSyncService;
use App\Services\SessionDeletionService;
use App\Services\TeacherLessonService;
use App\Services\TeacherScheduleService;
use App\Support\HumanDate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Админка · Занятия: расписание всех учителей (список, неделя, месяц), проведённые, запросы на удаление, записи.
 * Макеты: AdminLessons, AdminSessions, AdminRecordings. Оплату занятий администратор не видит.
 */
#[Layout('components.layouts.cabinet', ['title' => 'Занятия', 'active' => 'lessons'])]
class Lessons extends Component
{
    use AdminScreen, DecidesDeletions;

    private const TABS = ['schedule', 'sessions', 'deletions', 'recordings'];

    private const PAGE = 30;

    #[Url(except: 'schedule')]
    public string $tab = 'schedule';

    /** Расписание: list | week | month. */
    #[Url(except: 'list')]
    public string $view = 'list';

    /** Список: all | live | none | arch. */
    #[Url(except: 'all')]
    public string $filter = 'all';

    /** Учитель (id) или '' — все. */
    #[Url(except: '')]
    public string $teacher = '';

    #[Url(except: '')]
    public string $q = '';

    /** Неделя или месяц календаря (любая дата внутри), Y-m-d. */
    #[Url(except: '')]
    public string $date = '';

    /** Проведённые и записи: week | month | all. */
    #[Url(except: 'month')]
    public string $period = 'month';

    /** Проведённые одного занятия (ссылка «Все» со страницы занятия). */
    #[Url(except: '')]
    public string $lesson = '';

    /** Запись, открытая в плеере. */
    #[Url(except: null)]
    public ?int $open = null;

    public int $limit = self::PAGE;

    public ?int $deleteRecordingId = null;

    /* Окно «Создать занятие» */
    public bool $createOpen = false;

    public string $cTeacher = '';

    public string $cName = '';

    /** @var array<int> */
    public array $cStudents = [];

    public string $cAdd = '';

    public string $cDate = '';

    public string $cTime = '';

    public int $cDuration = \App\Models\RoomSchedule::DEFAULT_DURATION;

    public string $cRepeat = 'weekly';

    /** @var array<int> */
    public array $cDays = [];

    public string $cUntil = '';

    /** Занятие началось или завершилось. */
    #[On('echo:rooms,.room.status.updated')]
    public function refreshRooms(): void {}

    public function mount(): void
    {
        $this->authorizeAdmin();
        $this->normalize();
    }

    public function updated(string $name): void
    {
        $this->normalize();

        if (in_array($name, ['tab', 'filter', 'teacher', 'q', 'period', 'view'], true)) {
            $this->limit = self::PAGE;
        }
        if ($name === 'tab') {
            $this->open = null;
        }
    }

    private function normalize(): void
    {
        $this->tab = in_array($this->tab, self::TABS, true) ? $this->tab : 'schedule';
        $this->view = in_array($this->view, ['list', 'week', 'month'], true) ? $this->view : 'list';
        $this->filter = in_array($this->filter, ['all', AdminLessonsService::LIVE, AdminLessonsService::NONE, AdminLessonsService::ARCHIVED], true) ? $this->filter : 'all';
        $this->period = in_array($this->period, ['week', 'month', 'all'], true) ? $this->period : 'month';
        $this->teacher = ctype_digit($this->teacher) ? $this->teacher : '';
        $this->lesson = ctype_digit($this->lesson) ? $this->lesson : '';
        if ($this->date !== '' && ! $this->anchor(true)) {
            $this->date = '';
        }
    }

    private function teacherId(): ?int
    {
        return $this->teacher !== '' ? (int) $this->teacher : null;
    }

    public function showMore(): void
    {
        $this->limit += self::PAGE;
    }

    public function resetFilters(): void
    {
        $this->filter = 'all';
        $this->teacher = '';
        $this->q = '';
        $this->lesson = '';
        $this->limit = self::PAGE;
    }

    /*
     |--------------------------------------------------------------------------
     | Календарь
     |--------------------------------------------------------------------------
     */

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

    /*
     |--------------------------------------------------------------------------
     | Записи
     |--------------------------------------------------------------------------
     */

    public function play(int $id): void
    {
        $recording = Recording::find($id);
        abort_unless($recording && $recording->status() !== 'processing', 404);
        $this->open = $id;
    }

    public function closePlayer(): void
    {
        $this->open = null;
    }

    public function askDeleteRecording(int $id): void
    {
        abort_unless(Recording::whereKey($id)->exists(), 404);
        $this->deleteRecordingId = $id;
        $this->open = null;
    }

    public function closeDeleteRecording(): void
    {
        $this->deleteRecordingId = null;
    }

    public function deleteRecording(): void
    {
        $this->authorizeAdmin();
        $recording = Recording::find($this->deleteRecordingId);
        abort_unless($recording, 404);

        app(RecordingSyncService::class)->delete($recording);

        if ($this->open === $recording->id) {
            $this->open = null;
        }
        $this->deleteRecordingId = null;
        $this->dispatch('toast', message: 'Запись удалена и с сервера видеосвязи');
    }

    public function sync(): void
    {
        $this->authorizeAdmin();

        try {
            $count = app(RecordingSyncService::class)->syncAll();
            $this->dispatch('toast', message: $count ? 'Список обновлён · ' . plural_ru($count, 'запись', 'записи', 'записей') . ' с сервера' : 'Список обновлён, новых записей нет');
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', message: 'Сервер видеосвязи не ответил. Попробуйте через минуту.', tone: 'danger');
        }
    }

    /*
     |--------------------------------------------------------------------------
     | Создать занятие (за учителя)
     |--------------------------------------------------------------------------
     */

    public function openCreate(): void
    {
        $this->resetValidation();
        $start = now()->addDay()->startOfHour()->setTime(17, 0);

        $this->cTeacher = $this->teacher;
        $this->cName = '';
        $this->cStudents = [];
        $this->cAdd = '';
        $this->cDate = $start->format('Y-m-d');
        $this->cTime = $start->format('H:i');
        $this->cDuration = \App\Models\RoomSchedule::DEFAULT_DURATION;
        $this->cRepeat = 'weekly';
        $this->cDays = [$start->dayOfWeek];
        $this->cUntil = '';
        $this->createOpen = true;
        $this->updatedCTeacher();
    }

    public function closeCreate(): void
    {
        $this->createOpen = false;
    }

    public function updatedCTeacher(): void
    {
        $teacher = $this->createTeacher();
        $this->cStudents = [];
        if ($teacher) {
            $this->cDuration = (int) ($teacher->lessonTypes()->where('type', 'individual')->value('duration') ?: \App\Models\RoomSchedule::DEFAULT_DURATION);
        }
    }

    public function updatedCAdd(): void
    {
        $id = (int) $this->cAdd;
        if ($id && ! in_array($id, $this->cStudents, true)) {
            $this->cStudents[] = $id;
        }
        $this->cAdd = '';
    }

    public function updatedCRepeat(): void
    {
        $this->cRepeat = in_array($this->cRepeat, ['once', 'weekly'], true) ? $this->cRepeat : 'once';
    }

    public function removeCStudent(int $id): void
    {
        $this->cStudents = array_values(array_filter($this->cStudents, fn ($s) => (int) $s !== $id));
    }

    public function toggleCDay(int $day): void
    {
        if ($day < 0 || $day > 6) {
            return;
        }
        $this->cDays = in_array($day, $this->cDays, true) ? array_values(array_diff($this->cDays, [$day])) : [...$this->cDays, $day];
    }

    private function createTeacher(): ?User
    {
        return $this->cTeacher !== '' ? User::where('role', User::ROLE_TUTOR)->find((int) $this->cTeacher) : null;
    }

    public function saveCreate(): void
    {
        $this->authorizeAdmin();
        $teacher = $this->createTeacher();
        $allowed = $teacher ? app(TeacherLessonService::class)->studentsQuery($teacher)->pluck('users.id')->all() : [];
        $weekly = $this->cRepeat === 'weekly';

        $this->validate([
            'cTeacher' => ['required', Rule::exists('users', 'id')->where('role', User::ROLE_TUTOR)],
            'cName' => ['required', 'string', 'max:255'],
            'cStudents' => ['required', 'array'],
            'cStudents.*' => [Rule::in($allowed)],
            'cDate' => ['required', 'date'],
            'cTime' => ['required', 'date_format:H:i'],
            'cDuration' => ['required', 'integer', 'min:1', 'max:1440'],
            'cDays' => [Rule::requiredIf($weekly), 'array'],
            'cDays.*' => ['integer', 'between:0,6'],
            'cUntil' => ['nullable', 'date', 'after_or_equal:cDate'],
        ], [
            'cTeacher.required' => 'Выберите учителя',
            'cTeacher.exists' => 'Выберите учителя',
            'cName.required' => 'Назовите занятие: предмет или тему',
            'cStudents.required' => 'Добавьте хотя бы одного ученика',
            'cStudents.*.in' => 'Можно выбрать только учеников этого учителя',
            'cDate.required' => 'Укажите дату',
            'cTime.required' => 'Укажите время',
            'cTime.date_format' => 'Время в формате 17:00',
            'cDays.required' => 'Выберите дни недели',
            'cUntil.after_or_equal' => 'Дата окончания раньше первого занятия',
        ]);

        $schedules = [$weekly
            ? TeacherLessonService::scheduleAttributes('weekly', $this->cDate, $this->cTime, $this->cDuration, $this->cDays, $this->cUntil ?: null)
            : TeacherLessonService::scheduleAttributes('once', $this->cDate, $this->cTime, $this->cDuration)];
        $first = TeacherLessonService::firstOccurrence($this->cRepeat, $this->cDate, $this->cTime, $this->cDays);

        $room = app(AdminLessonsService::class)->create($teacher, $this->cName, $this->cStudents, $schedules, $first);

        $this->createOpen = false;
        session()->flash('toast', 'Занятие создано, ' . AdminLessonsService::firstName($teacher) . ' и ученики получат уведомление');
        $this->redirect(route('cabinet.admin.lesson', ['room' => $room->id]));
    }

    /** Данные окна «Создать занятие». */
    private function createView(): array
    {
        if (! $this->createOpen) {
            return [];
        }

        $teacher = $this->createTeacher();
        $students = $teacher ? app(TeacherLessonService::class)->studentsQuery($teacher)->orderBy('name')->pluck('name', 'id') : collect();
        $first = TeacherLessonService::firstOccurrence($this->cRepeat, $this->cDate, $this->cTime, $this->cDays);
        $days = collect($this->cDays)->sortBy(fn ($d) => $d === 0 ? 7 : $d)->map(fn ($d) => ['вс', 'пн', 'вт', 'ср', 'чт', 'пт', 'сб'][$d])->values()->all();
        $count = count($this->cStudents);

        return [
            'cTeacherOptions' => app(AdminLessonsService::class)->teacherOptions(),
            'cAddOptions' => $students->except($this->cStudents)->all(),
            'cChosen' => collect($this->cStudents)->map(fn ($id) => ['id' => (int) $id, 'name' => $students[$id] ?? ''])->all(),
            'cWhoHint' => ! $teacher ? 'Сначала выберите учителя'
                : ($students->isEmpty() ? 'У этого учителя пока нет учеников'
                    : 'Можно выбрать только учеников этого учителя' . ($count > 1 ? ' · ' . plural_ru($count, 'ученик', 'ученика', 'учеников') . ', групповое занятие' : '')),
            'cDurations' => TeacherLessonService::durationOptions($this->cDuration),
            'cFoot' => ($this->cRepeat === 'weekly'
                    ? ($days && preg_match('/^\d{1,2}:\d{2}$/', $this->cTime) ? 'По ' . TeacherScheduleService::joinAnd($days) . ' в ' . $this->cTime : 'Выберите дни недели')
                    : ($first ? 'Разово, ' . TeacherLessonService::when($first) : 'Укажите дату и время'))
                . ' · учитель и ученики получат уведомление',
        ];
    }

    /*
     |--------------------------------------------------------------------------
     | Экран
     |--------------------------------------------------------------------------
     */

    protected function afterDecision(string $message): void
    {
        $this->dispatch('toast', message: $message);
    }

    public function render()
    {
        $service = app(AdminLessonsService::class);
        $pending = app(SessionDeletionService::class)->pending()->count();

        $data = match ($this->tab) {
            'sessions' => $this->sessions($service),
            'deletions' => $this->deletions(),
            'recordings' => $this->recordings($service),
            default => match ($this->view) {
                'week' => $this->week($service),
                'month' => $this->month($service),
                default => $this->list($service),
            },
        };

        return view('livewire.cabinet.admin.lessons', $data + [
            'pending' => $pending,
            'teacherOptions' => ['' => 'Все учителя'] + $service->teacherOptions(),
            'periodOptions' => ['week' => 'Эта неделя', 'month' => Str::ucfirst(HumanDate::month(now())), 'all' => 'Всё время'],
        ] + $this->createView() + $this->decisionView() + $this->recordingModals());
    }

    /** Список занятий. */
    private function list(AdminLessonsService $service): array
    {
        $result = $service->rooms($this->teacherId(), $this->q, $this->filter, $this->limit);
        $c = $result['counts'];

        return [
            'sub' => plural_ru($result['active'], 'занятие', 'занятия', 'занятий')
                . ($this->teacherId() ? '' : ' у ' . plural_ru($result['teachers'], 'учителя', 'учителей', 'учителей'))
                . ' · ' . $c[AdminLessonsService::ARCHIVED] . ' в архиве',
            'rows' => $result['rows'],
            'hasMore' => $result['hasMore'],
            'filters' => [
                'all' => 'Все',
                AdminLessonsService::LIVE => 'Идут сейчас · ' . $c[AdminLessonsService::LIVE],
                AdminLessonsService::NONE => 'Без расписания · ' . $c[AdminLessonsService::NONE],
                AdminLessonsService::ARCHIVED => 'В архиве · ' . $c[AdminLessonsService::ARCHIVED],
            ],
        ];
    }

    /** Сетка недели: первый и последний час (09–22), расширяется под занятия. */
    private const WEEK_FIRST_HOUR = 9;

    private const WEEK_END_HOUR = 22;

    private const QUARTER_TOP = ['top-0', 'top-4', 'top-8', 'top-12'];

    private const LANES = ['full' => 'inset-x-1', 0 => 'left-1 right-1/2', 1 => 'left-1/2 right-1'];

    /** Цвет занятия — цвет учителя (id % 3); идущее — чёрная полоса; отменённое — только контур. */
    private const TONES = [
        0 => 'bg-av-1/40 border-l-4 border-av-1',
        1 => 'bg-av-2/40 border-l-4 border-av-2',
        2 => 'bg-av-3/40 border-l-4 border-av-3',
        'live' => 'bg-mint border-l-4 border-ink',
        'off' => 'bg-white text-muted line-through shadow-line',
    ];

    private const SWATCHES = [0 => 'bg-av-1', 1 => 'bg-av-2', 2 => 'bg-av-3'];

    private function week(AdminLessonsService $service): array
    {
        $start = $this->anchor()->startOfWeek(Carbon::MONDAY);
        $end = $start->copy()->addDays(6)->endOfDay();
        $lessons = $service->calendar($this->teacherId(), $start, $end);

        $firstHour = min(self::WEEK_FIRST_HOUR, (int) ($lessons->min(fn ($l) => $l['start']->hour) ?? self::WEEK_FIRST_HOUR));
        $endHour = min(24, max(self::WEEK_END_HOUR, (int) ($lessons->max(fn ($l) => $l['end']->isSameDay($l['start'])
            ? $l['end']->hour + ($l['end']->minute > 0 ? 1 : 0) : 24) ?? self::WEEK_END_HOUR)));

        $days = collect(range(0, 6))->map(function (int $i) use ($start, $lessons, $firstHour, $endHour) {
            $day = $start->copy()->addDays($i);
            $dayLessons = $lessons->filter(fn ($l) => $l['start']->isSameDay($day))->values();

            $lanes = [];
            foreach ($dayLessons as $k => $l) {
                $overlaps = $dayLessons->filter(fn ($o, $j) => $j !== $k && $o['start']->lt($l['end']) && $o['end']->gt($l['start']));
                $lanes[$k] = $overlaps->isEmpty() ? 'full' : ($overlaps->keys()->filter(fn ($j) => $j < $k)->count() % 2);
            }

            $events = $dayLessons->map(function ($l, $k) use ($firstHour, $endHour, $lanes) {
                $slot = intdiv($l['start']->hour * 60 + $l['start']->minute + 7, 15);
                $hour = max($firstHour, intdiv($slot, 4));
                $quarters = max(1, min((int) round($l['duration'] / 15), ($endHour - $hour) * 4 - $slot % 4));

                return $this->calendarEvent($l, $lanes[$k] !== 'full') + [
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

        $isCurrent = $start->isSameDay(today()->startOfWeek(Carbon::MONDAY));
        $now = now();
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
            'legend' => $this->legend($lessons, true),
        ];
    }

    private function month(AdminLessonsService $service): array
    {
        $first = $this->anchor()->startOfMonth();
        $gridStart = $first->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $first->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        $lessons = $service->calendar($this->teacherId(), $gridStart, $gridEnd);

        $cells = collect();
        for ($day = $gridStart->copy(); $day->lte($gridEnd); $day->addDay()) {
            $d = $day->copy();
            $cells->push([
                'date' => $d,
                'inMonth' => $d->month === $first->month,
                'isToday' => $d->isToday(),
                'events' => $lessons->filter(fn ($l) => $l['start']->isSameDay($d))->map(fn ($l) => $this->calendarEvent($l, true))->values(),
            ]);
        }

        $inMonth = $lessons->filter(fn ($l) => $l['start']->month === $first->month);

        return [
            'sub' => plural_ru($inMonth->where('cancelled', false)->count(), 'занятие', 'занятия', 'занятий') . ' в ' . self::monthIn($first),
            'calTitle' => Str::ucfirst(HumanDate::month($first)) . ($first->year === now()->year ? ' ' . $first->year : ''),
            'cells' => $cells,
            'isCurrent' => $first->isSameMonth(today()),
            'legend' => $this->legend($lessons, false),
        ];
    }

    /** «сентябре» — для «в сентябре». */
    private static function monthIn(Carbon $date): string
    {
        $s = [1 => 'январе', 'феврале', 'марте', 'апреле', 'мае', 'июне', 'июле', 'августе', 'сентябре', 'октябре', 'ноябре', 'декабре'][$date->month];

        return $date->year === now()->year ? $s : $s . ' ' . $date->year;
    }

    /** Занятие в сетке: время, кто (ученик или группа), учитель, подпись, цвет учителя. */
    private function calendarEvent(array $l, bool $compact): array
    {
        $teacher = $l['teacher'];
        $surname = $teacher ? (preg_split('/\s+/u', trim($teacher->name))[0] ?? $teacher->name) : '';
        $who = $l['student'] ? $l['student']->name : $l['title'];

        return [
            'key' => $l['key'],
            'time' => $l['start']->format('H:i'),
            'title' => $compact ? $l['start']->format('H:i') : $l['start']->format('H:i') . ' · ' . $who,
            'teacher' => $compact ? $surname : ($teacher?->name ?? ''),
            'short' => $l['start']->format('H:i') . ' ' . $surname . ($l['cancelled'] ? ' · отменено' : ''),
            'note' => match (true) {
                $l['cancelled'] => 'отменено',
                $l['running'] => 'идёт сейчас',
                (bool) $l['movedFrom'] => 'перенесено',
                default => null,
            },
            'running' => $l['running'],
            'past' => $l['past'] && ! $l['cancelled'],
            'cancelled' => $l['cancelled'],
            'tone' => self::TONES[match (true) {
                $l['cancelled'] => 'off',
                $l['running'] => 'live',
                default => ($teacher?->id ?? 0) % 3,
            }],
            'url' => route('cabinet.admin.lesson', ['room' => $l['roomId']]),
        ];
    }

    /** Легенда: учителя по цвету, «Идёт сейчас», «Отменено». */
    private function legend(Collection $lessons, bool $withLive): array
    {
        $items = $lessons->pluck('teacher')->filter()->unique('id')->sortBy('name')
            ->groupBy(fn (User $t) => $t->id % 3)->sortKeys()
            ->map(fn ($list, $tone) => ['swatch' => self::SWATCHES[$tone], 'label' => $list->pluck('name')->implode(', ')])
            ->values()->all();

        if ($withLive) {
            $items[] = ['swatch' => 'bg-ink', 'label' => 'Идёт сейчас'];
        }
        $items[] = ['swatch' => 'bg-white shadow-line', 'label' => 'Отменено'];

        return $items;
    }

    /** Проведённые занятия. */
    private function sessions(AdminLessonsService $service): array
    {
        $roomId = $this->lesson !== '' ? (int) $this->lesson : null;
        $query = $service->sessionsQuery($this->teacherId(), $this->period, $this->q, $roomId);
        $sessions = (clone $query)->limit($this->limit + 1)->get();

        $monthCount = MeetingSession::where('status', 'completed')->where('started_at', '>=', now()->startOfMonth())->count();
        $running = MeetingSession::where('status', 'running')->count();

        return [
            'sub' => 'В ' . self::monthIn(now()) . ' — ' . $monthCount . ($running ? ' · сейчас ' . plural_ru($running, 'идёт', 'идут', 'идут', false) . ' ' . $running : ''),
            'sessionRows' => $sessions->take($this->limit)->map(fn (MeetingSession $s) => $this->sessionRow($s))->values(),
            'hasMore' => $sessions->count() > $this->limit,
            'lessonName' => $roomId ? Room::withTrashed()->whereKey($roomId)->value('name') : null,
        ];
    }

    private function sessionRow(MeetingSession $s): array
    {
        $att = TeacherScheduleService::attendance($s);
        $live = $s->status === 'running';
        $minutes = $live ? max(1, (int) $s->started_at->diffInMinutes(now())) : TeacherScheduleService::sessionMinutes($s);

        return [
            'id' => $s->id,
            'title' => $s->room?->name ?? 'Занятие',
            'teacher' => $s->room?->user?->name,
            'when' => Str::ucfirst(HumanDate::at($s->started_at)),
            'live' => $live,
            'dur' => $minutes . ' мин',
            'short' => ! $live && $minutes < 15,
            'came' => $att['total'] ? $att['attended'] . ' из ' . $att['total'] : null,
            'nobody' => ! $live && $att['total'] > 0 && $att['attended'] === 0,
            'request' => (bool) $s->deletion_requested_at,
            'url' => route('cabinet.admin.session', ['session' => $s->id]),
        ];
    }

    /** Запросы на удаление и решённые раньше. */
    private function deletions(): array
    {
        $pending = app(SessionDeletionService::class)->pending()->get();
        $first = $pending->first();

        $done = SessionDeletionDecision::with('teacher:id,name')->latest()->limit(20)->get()->map(fn (SessionDeletionDecision $d) => [
            'id' => $d->id,
            'title' => $d->room_name ?? 'Занятие',
            'meta' => collect([$d->teacher?->name, $d->session_started_at
                ? HumanDate::day($d->session_started_at) . ', ' . $d->session_started_at->format('H:i') . '–' . ($d->session_ended_at ?? $d->session_started_at)->format('H:i')
                : null])->filter()->implode(' · '),
            'result' => ($d->decision === SessionDeletionDecision::DELETED ? 'Удалено ' : 'Отклонено ') . ($d->created_at->isToday() ? 'сегодня' : HumanDate::date($d->created_at)),
            'reply' => $d->reply,
        ]);

        return [
            'sub' => $pending->isEmpty() ? 'Новых запросов нет' : plural_ru($pending->count(), 'запрос ждёт', 'запроса ждут', 'запросов ждут') . ' решения',
            'request' => $first ? $this->requestCard($first) : null,
            'others' => $pending->slice(1)->map(fn (MeetingSession $s) => [
                'id' => $s->id,
                'title' => $s->room?->name ?? 'Занятие',
                'meta' => collect([$s->room?->user?->name, HumanDate::day($s->started_at) . ', ' . $s->started_at->format('H:i'),
                    'запрос ' . HumanDate::at($s->deletion_requested_at)])->filter()->implode(' · '),
                'url' => route('cabinet.admin.session', ['session' => $s->id]),
            ])->values(),
            'done' => $done,
        ];
    }

    private function requestCard(MeetingSession $s): array
    {
        $att = TeacherScheduleService::attendance($s);
        $end = $s->ended_at ?? $s->started_at;
        $minutes = TeacherScheduleService::sessionMinutes($s);
        $after = MeetingSession::where('room_id', $s->room_id)->where('status', 'completed')->whereKeyNot($s->id)
            ->where('started_at', '>=', $end)->where('started_at', '<=', $end->copy()->addHours(2))
            ->orderBy('started_at')->first();

        return [
            'id' => $s->id,
            'title' => $s->room?->name ?? 'Занятие',
            'teacher' => $s->room?->user?->name,
            'range' => HumanDate::day($s->started_at) . ', ' . $s->started_at->format('H:i') . '–' . $end->format('H:i'),
            'duration' => plural_ru($minutes, 'минута', 'минуты', 'минут'),
            'came' => $att['total'] ? 'пришли ' . $att['attended'] . ' из ' . $att['total'] : null,
            'reason' => $s->deletion_reason,
            'sent' => 'Запрос отправлен ' . HumanDate::at($s->deletion_requested_at)
                . ($after ? ' · сразу после этого прошло занятие ' . $after->started_at->format('H:i') . '–' . ($after->ended_at ?? $after->started_at)->format('H:i')
                    . ', ' . plural_ru(TeacherScheduleService::sessionMinutes($after), 'минута', 'минуты', 'минут') : ''),
            'url' => route('cabinet.admin.session', ['session' => $s->id]),
        ];
    }

    /** Записи. */
    private function recordings(AdminLessonsService $service): array
    {
        $query = $service->recordingsQuery($this->teacherId(), $this->period, $this->q);
        $items = (clone $query)->limit($this->limit + 1)->get();
        $all = Recording::listed();
        $busy = (clone $all)->where(fn ($q) => $q->whereNull('s3_url'))->get()->filter(fn (Recording $r) => $r->status() !== 'ready')->count();

        return [
            'sub' => plural_ru((clone $all)->count(), 'запись', 'записи', 'записей') . ($busy ? ' · ' . $busy . ' ещё не ' . ($busy === 1 ? 'готова' : 'готовы') : ''),
            'recordingRows' => $items->take($this->limit)->map(fn (Recording $r) => $this->recordingRow($r))->values(),
            'hasMore' => $items->count() > $this->limit,
        ];
    }

    private function recordingRow(Recording $r): array
    {
        $start = $r->start_time;
        $minutes = $start && $r->end_time ? (int) round($start->diffInMinutes($r->end_time)) : null;
        $status = $r->status();

        return [
            'id' => $r->id,
            'title' => $r->room?->name ?: ($r->name ?: 'Запись занятия'),
            'teacher' => $r->room?->user?->name,
            'when' => $start ? Str::ucfirst(HumanDate::at($start)) : '—',
            'dur' => $minutes ? $minutes . ' мин' : '—',
            'people' => $r->participants ? plural_ru((int) $r->participants, 'человек', 'человека', 'человек') : '—',
            'status' => $status,
            'canWatch' => $status !== 'processing',
            'downloadUrl' => $r->s3_url ? route('recordings.download', $r) : null,
        ];
    }

    /** Плеер и подтверждение удаления записи. */
    private function recordingModals(): array
    {
        $data = ['player' => null, 'recordingToDelete' => null];

        if ($this->open && ($r = Recording::with(['room' => fn ($q) => $q->withTrashed()->with('user:id,name')])->find($this->open))) {
            $row = $this->recordingRow($r);
            $data['player'] = $row + [
                'meta' => collect([$row['teacher'], $r->start_time ? HumanDate::at($r->start_time) : null, $row['dur'] !== '—' ? $row['dur'] : null,
                    $r->participants ? plural_ru((int) $r->participants, 'человек', 'человека', 'человек') . ' в классе' : null])->filter()->implode(' · '),
                'video' => $r->s3_url,
                'externalUrl' => ! $r->s3_url && $r->url ? $r->url : null,
            ];
        }

        if ($this->deleteRecordingId && ($r = Recording::with(['room' => fn ($q) => $q->withTrashed()])->find($this->deleteRecordingId))) {
            $data['recordingToDelete'] = [
                'sub' => ($r->room?->name ?: ($r->name ?: 'Запись занятия')) . ($r->start_time ? ' · ' . HumanDate::at($r->start_time) : ''),
            ];
        }

        return $data;
    }
}
