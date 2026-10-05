<?php

namespace App\Demo\Teacher;

use App\Demo\Concerns\DemoHomework;
use App\Demo\Concerns\ScheduleDemoData;
use App\Demo\Concerns\TeacherModals;
use App\Demo\Screen;
use App\Demo\World;
use App\Livewire\Cabinet\Teacher\Concerns\LessonRows;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\PaymentRecord;
use App\Models\Room;
use App\Models\RoomSchedule;
use App\Models\User;
use App\Services\LessonActivityService;
use App\Services\TeacherLessonService;
use App\Services\TeacherScheduleService;
use App\Support\HumanDate;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * «Занятие» учителя — App\Livewire\Cabinet\Teacher\Lesson: предстоящее (обзор, материалы, записи и история),
 * идущее (?class=1 — сюда ведёт «Начать занятие»: видеосвязи в демо нет, экран показывает занятие «как будто класс открыт»),
 * отчёт о проведённом (?session=…), отменённое и перенесённое (?at=…), все окна экрана.
 *
 * Состояние окон — в адресе: open=reschedule|cancel|lessonPlan|edit|presentations|deleteRequest (+ rsScope, rsDays, cancelScope,
 * editKind, editStudents), confirmStop, confirmPresentation; после «Отменить занятие» — cancelled=1, после запроса удаления — deletion=1.
 */
class Lesson extends Screen
{
    use DemoHomework, LessonRows, ScheduleDemoData;
    use TeacherModals {
        TeacherModals::modalParams as teacherModalParams;
    }

    public const PATH = 'lessons/{room}';

    public const EXAMPLES = [
        'lessons/201', 'lessons/206', 'lessons/201?class=1', 'lessons/206?class=1', 'lessons/206?class=1&confirmStop=1',
        'lessons/201?tab=materials', 'lessons/206?tab=history', 'lessons/203?tab=history', 'lessons/205', 'lessons/204',
        'lessons/201?open=reschedule', 'lessons/201?open=reschedule&rsScope=following&rsDays=1,3', 'lessons/206?open=cancel',
        'lessons/206?open=cancel&cancelScope=series', 'lessons/201?open=lessonPlan', 'lessons/206?open=edit&editKind=group&editStudents=106,107,108',
        'lessons/201?open=edit', 'lessons/201?open=presentations', 'lessons/201?tab=materials&confirmPresentation=x', 'lessons/201?cancelled=1',
        'lessons/203?open=plan', 'lessons/203?mark=103',
    ];

    /** Презентации к занятию: открываются в классе при старте. */
    private const PRESENTATIONS = [
        201 => ['Квадратные уравнения — разбор.pdf', 'Системы уравнений.pptx'],
        202 => ['Производная — повторение.pdf'],
        203 => ['Законы Ньютона.pptx'],
        204 => ['Подобие треугольников.pptx'],
        205 => [],
        206 => ['Пробник №4 — разбор.pptx', 'Отбор корней в тригонометрии.pdf'],
    ];

    /** План ближайшего занятия (у «Алгебры» Хавы — пусто, чтобы было видно «Составить»). */
    private const PLANS = [
        201 => ['Проверить домашнее задание: квадратные уравнения, вариант 3', 'Разобрать задачу 5 — когда дискриминант меньше нуля', 'Новая тема: системы уравнений, способ подстановки', 'Три задачи из демоверсии ОГЭ на время'],
        202 => ['Разобрать ошибки в логарифмах (задачи 7 и 9)', 'Производная сложной функции', 'Задача 12 ЕГЭ: наибольшее и наименьшее значение'],
        203 => ['Сила трения: направление и формула', 'Задачи 7–8 из домашнего листка', 'Кинематика: графики скорости'],
        204 => ['Признаки подобия — повторить', 'Задачи 539–542 с чертежами', 'Самостоятельная на 10 минут'],
        205 => [],
        206 => ['Разбор пробника №4: задания 15 и 16', 'Тригонометрические уравнения — отбор корней', 'Самостоятельная на 15 минут', 'Выдать задание на формулы приведения'],
    ];

    /** С какого месяца занимается ученик (месяцев назад). */
    private const SINCE = [101 => 13, 102 => 8, 103 => 5, 104 => 4, 105 => 2, 106 => 7, 107 => 7, 108 => 1];

    public string $view = 'livewire.cabinet.teacher.lesson';

    public string $title = 'Занятие';

    public ?string $active = 'schedule';

    private ?Room $roomModel = null;

    private function roomId(): int
    {
        $id = (int) $this->param('room');

        return isset(World::ROOMS[$id]) ? $id : 201;
    }

    private function group(): bool
    {
        return World::ROOMS[$this->roomId()][1] === 'group';
    }

    private function student(): ?User
    {
        return $this->group() ? null : World::student(World::ROOMS[$this->roomId()][2][0]);
    }

    public function notice(): ?string
    {
        return $this->state('class', false)
            ? 'В настоящем кабинете здесь откроется класс: видео, доска, презентации и запись занятия. В демо видеосвязь не запускается'
            : null;
    }

    public function modalParams(): array
    {
        return [...$this->teacherModalParams(), 'rsScope', 'rsRepeat', 'rsDays', 'rsDate', 'rsTime', 'cancelScope',
            'editKind', 'editStudents', 'confirmPresentation', 'confirmStop'];
    }

    /*
     |--------------------------------------------------------------------------
     | Какое занятие показано
     |--------------------------------------------------------------------------
     */

    /** Занятия этой серии на две недели вперёд (и сегодняшние). */
    private function series(): Collection
    {
        return self::demoLessons(Carbon::today(), Carbon::today()->addDays(14)->endOfDay())
            ->where('roomId', $this->roomId())->values();
    }

    /** Показанное занятие: по ?at= (в том числе отменённое) или ближайшее, которое не закончилось и не отменено. */
    private function occurrence(): ?array
    {
        $at = (string) $this->state('at', '');
        $found = null;

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $at)) {
            $day = Carbon::createFromFormat('Y-m-d\TH:i', $at)->startOfDay();
            $found = self::demoLessons($day, $day->copy()->endOfDay())
                ->first(fn ($l) => $l['roomId'] === $this->roomId() && $l['originalStart']->format('Y-m-d\TH:i') === $at);
        }

        $found ??= $this->series()->first(fn ($l) => ! $l['cancelled'] && $l['end']->isFuture());

        // После «Отменить занятие» в демо: это занятие показываем отменённым
        if ($found && $this->state('cancelled', false)) {
            $found['cancelled'] = true;
        }

        return $found;
    }

    /** Следующее занятие после показанного (для «следующее — …» и «Следующее занятие» в отчёте). */
    private function following(?Carbon $after = null): ?array
    {
        $after ??= Carbon::now();

        return $this->series()->first(fn ($l) => ! $l['cancelled'] && $l['start']->gt($after));
    }

    private function room(): Room
    {
        if ($this->roomModel) {
            return $this->roomModel;
        }

        $id = $this->roomId();
        [$name, $type, $students] = World::ROOMS[$id];

        $room = new Room;
        $room->forceFill([
            'id' => $id,
            'name' => $name,
            'type' => $type,
            'user_id' => World::TEACHER_ID,
            'is_running' => false,
            'presentations' => array_map(fn ($f) => 'presentations/' . $id . '/' . $f, self::PRESENTATIONS[$id]),
            'deleted_at' => null,
        ]);
        $room->exists = true;
        $room->setRelation('participants', new EloquentCollection(collect($students)->map(fn ($s) => World::student($s))->all()));
        $room->setRelation('schedules', new EloquentCollection);

        return $this->roomModel = $room;
    }

    /** Последнее проведённое занятие этой серии (куда ведёт «Завершить занятие» в демо). */
    private function lastHeld(): ?array
    {
        return self::demoLessons(Carbon::today()->subDays(14), Carbon::now())
            ->where('roomId', $this->roomId())
            ->filter(fn ($l) => self::demoHeld($l) && count(self::demoHeld($l)['attended']) > 0)
            ->last();
    }

    /*
     |--------------------------------------------------------------------------
     | Действия
     |--------------------------------------------------------------------------
     */

    public function actions(): array
    {
        $room = $this->room();
        $next = $this->occurrence();
        $student = $this->student();
        $name = $student?->first_name;
        $sent = $name ? ', ' . $name . ' получит уведомление' : ', ученики получат уведомление';
        $ids = $room->participants->pluck('id')->map(fn ($id) => (string) $id)->all();
        $days = collect(World::ROOMS[$this->roomId()][3])->pluck(0)->unique()->implode(',');

        // Текст тоста «Перенести» — из того, что сейчас выбрано в окне
        $rs = $this->rescheduleState($next);
        $rsToast = $rs['mode'] === 'following'
            ? 'Расписание изменено: ' . TeacherScheduleService::repeatLabel(new RoomSchedule(['type' => 'recurring', 'recurrence_type' => 'weekly', 'recurrence_days' => $rs['days']])) . ' в ' . $rs['time'] . $sent
            : 'Занятие перенесено на ' . (($first = TeacherLessonService::firstOccurrence('once', $rs['date'], $rs['time'])) ? HumanDate::at($first) : $rs['time']) . $sent;
        $series = $this->state('cancelScope', 'one') === 'series';

        return $this->modalActions() + [
            'askStop' => ['set' => ['confirmStop' => '1']],
            'keepRunning' => ['set' => ['confirmStop' => null]],
            'openReschedule' => ['set' => ['open' => 'reschedule', 'rsScope' => 'one', 'rsDays' => $days]],
            'toggleRsDay' => ['toggle' => 'rsDays'],
            'saveReschedule' => ['close' => true, 'toast' => $rsToast],
            'openCancel' => ['set' => ['open' => 'cancel']],
            'confirmCancel' => ['close' => true, 'set' => ['cancelled' => '1', 'at' => $next ? $next['originalStart']->format('Y-m-d\TH:i') : null],
                'toast' => ($series ? 'Серия занятий отменена' : 'Занятие отменено') . $sent],
            'openLessonPlan' => ['set' => ['open' => 'lessonPlan']],
            'saveLessonPlan' => ['close' => true, 'toast' => 'План занятия сохранён'],
            'openEdit' => ['set' => ['open' => 'edit', 'editKind' => $this->group() ? 'group' : 'individual', 'editStudents' => implode(',', $ids)]],
            'pickEditStudent' => $this->state('editKind', $this->group() ? 'group' : 'individual') === 'group'
                ? ['toggle' => 'editStudents']
                : ['set' => ['editStudents' => '{0}']],
            'saveEdit' => ['close' => true, 'toast' => 'Сохранено'],
            'openPresentations' => ['set' => ['open' => 'presentations']],
            'savePresentations' => ['toast' => 'Это демо — файлы не загружаются. Зарегистрируйтесь, чтобы добавить свои презентации'],
            'askDeletePresentation' => ['set' => ['confirmPresentation' => '{0}']],
            'deletePresentation' => ['set' => ['confirmPresentation' => null], 'toast' => 'Презентация удалена'],
            'openDeleteRequest' => ['set' => ['open' => 'deleteRequest']],
            'sendDeleteRequest' => ['close' => true, 'set' => ['deletion' => '1'], 'toast' => 'Запрос отправлен, решение придёт в уведомлениях'],
            'revokeDeleteRequest' => ['set' => ['deletion' => null], 'toast' => 'Запрос отозван, занятие остаётся в истории'],
            'undoPaid' => ['toast' => 'Отметка об оплате отменена'],
        ];
    }

    /** Значения окна «Перенести»: из адреса, по умолчанию — показанное занятие. */
    private function rescheduleState(?array $next): array
    {
        $start = $next['start'] ?? Carbon::now()->addHour()->startOfHour();

        return [
            'mode' => $this->state('rsScope', 'one') === 'following' ? 'following' : 'one',
            'date' => (string) $this->state('rsDate', $start->format('Y-m-d')),
            'time' => (string) $this->state('rsTime', $start->format('H:i')),
            'days' => $this->stateInts('rsDays', collect(World::ROOMS[$this->roomId()][3])->pluck(0)->unique()->values()->all()),
            'duration' => (int) ($next['duration'] ?? 60),
        ];
    }

    /*
     |--------------------------------------------------------------------------
     | Экран
     |--------------------------------------------------------------------------
     */

    public function data(): array
    {
        $room = $this->room();
        $sessionId = $this->state('session', 0);
        $sessionLesson = $sessionId ? self::demoSessionLesson($this->roomId(), $sessionId) : null;

        $mode = match (true) {
            (bool) $sessionLesson => 'report',
            (bool) $this->state('class', false) => 'live',
            default => 'upcoming',
        };

        $next = $this->occurrence();
        $cancelled = (bool) ($next['cancelled'] ?? false);
        $group = $this->group();
        $student = $this->student();
        $tab = in_array($t = $this->state('tab', 'overview'), ['overview', 'materials', 'history'], true) ? $t : 'overview';
        $plan = self::PLANS[$this->roomId()];

        $data = [
            'room' => $room,
            'mode' => $mode,
            'group' => $group,
            'archived' => false,
            'next' => $next ? ['start' => $next['start'], 'end' => $next['end'], 'original' => $next['originalStart'], 'schedule' => null, 'exception' => null] : null,
            'cancelled' => $cancelled,
            'planItems' => $next && $mode !== 'report' && ! $cancelled ? $plan : [],
            'hasOccurrence' => (bool) $next,
            'startBlock' => null,
            'backUrl' => route('cabinet.teacher.schedule'),
            'chatUrl' => route('cabinet.teacher.messages', ['room' => $room->id]),
            'taskUrl' => route('cabinet.teacher.task-new', ['room' => $room->id]),
            'recordingsUrl' => route('cabinet.teacher.recordings'),
            'whoLine' => $room->name . ($student ? ' · ' . $student->name : ''),
            'otherRunning' => false,
            'homework' => $this->homeworkRows(),

            // Публичные свойства компонента
            'roomId' => $room->id,
            'at' => (string) $this->state('at', ''),
            'tab' => $tab,
            'session' => $sessionLesson ? $sessionId : null,
            'confirmStop' => (bool) $this->state('confirmStop', false),
            'justPaid' => [],
        ];

        $data += match ($mode) {
            'report' => $this->report($sessionLesson),
            'live' => $this->live($next),
            default => $this->upcoming($next),
        };

        if ($mode !== 'report') {
            $data += $this->people();
            $data += match ($tab) {
                'materials' => $this->materials(),
                'history' => $this->history(),
                default => [],
            };
        }

        return $data + $this->modals($next, $sessionLesson) + $this->modalData();
    }

    private static function when(Carbon $start, Carbon $end): string
    {
        return Str::ucfirst(HumanDate::day($start)) . ', ' . $start->format('H:i') . '–' . $end->format('H:i');
    }

    private static function facts(array $parts): HtmlString
    {
        return new HtmlString(collect($parts)->filter()->map(fn ($p) => is_array($p)
            ? '<span class="font-semibold text-ink">' . e($p[0]) . '</span>'
            : e($p))->implode(' · '));
    }

    private function upcoming(?array $next): array
    {
        $repeat = World::repeatLabel($this->roomId());

        $parts = match (true) {
            ! $next => [['Время не назначено']],
            $next['cancelled'] => (function () use ($next) {
                $following = $this->following($next['originalStart']);

                return [
                    self::when($next['originalStart'], $next['originalStart']->copy()->addMinutes($next['duration'])),
                    ['отменено' . ($next['reason'] ? ' · ' . $next['reason'] : '')],
                    $following ? 'следующее — ' . HumanDate::day($following['start']) : 'больше не повторяется',
                ];
            })(),
            (bool) $next['movedFrom'] => [
                self::when($next['start'], $next['end']),
                ['перенесено ' . TeacherScheduleService::movedFromLabel($next['movedFrom'])],
                'остальные — ' . $repeat,
            ],
            $next['end']->isPast() => [self::when($next['start'], $next['end']), ['уже прошло'], $repeat],
            default => [
                self::when($next['start'], $next['end']),
                $next['start']->isFuture()
                    ? ($next['start']->isToday() ? ['начнётся ' . HumanDate::until($next['start'])] : 'начнётся ' . HumanDate::until($next['start']))
                    : ['идёт по расписанию'],
                $repeat,
            ],
        };

        return ['sub' => self::facts($parts)];
    }

    /** Идёт сейчас: учитель только что открыл класс, ученики подключились. */
    private function live(?array $next): array
    {
        $now = Carbon::now();
        $teacher = World::teacher();
        $joined = $this->group() ? [107, 106] : [World::ROOMS[$this->roomId()][2][0]];

        $present = collect($joined)->map(fn (int $id) => [
            'me' => false, 'id' => $id, 'name' => World::student($id)->name, 'avatar' => World::student($id)->name, 'photo' => null,
            'sub' => 'Подключился в ' . $now->format('H:i'),
        ])->push([
            'me' => true, 'id' => $teacher->id, 'name' => 'Вы', 'avatar' => $teacher->name, 'photo' => null,
            'sub' => 'Ведёте занятие с ' . $now->format('H:i'),
        ])->values();

        $last = $this->lastHeld();

        return [
            'sub' => self::facts([
                ['Идёт 1 минуту'],
                'Идёт запись',
                $next ? self::when($next['start'], $next['end']) : null,
                World::repeatLabel($this->roomId()),
            ]),
            'present' => $present,
            // Не route('rooms.join'): номера комнат демо могут совпасть с настоящими — это был бы вход гостем на чужое занятие
            'guestUrl' => url('/rooms/demo/join'),
            // «Вернуться в класс» — снова сюда (с тостом про видеосвязь)
            'joinUrl' => route('cabinet.teacher.lesson', ['room' => $this->roomId(), 'class' => 1]),
            // «Завершить занятие» — отчёт о проведённом занятии этой серии
            'stopUrl' => $last ? $this->lessonUrl($this->roomId(), self::demoHeld($last)['id']) : route('cabinet.teacher.lesson', ['room' => $this->roomId()]),
        ];
    }

    /** Ученики, цена и неоплаченное (правая колонка). */
    private function people(): array
    {
        $roomId = $this->roomId();
        $unpaid = [103 => [2, true], 104 => [1, false]];

        $people = $this->room()->participants->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'photo' => null,
            'since' => 'Занимается с ' . HumanDate::month(Carbon::now()->subMonths(self::SINCE[$u->id] ?? 3), true),
            'url' => route('cabinet.teacher.student', ['student' => $u->id]),
            'price' => World::ROOMS[$roomId][4],
            'unpaid' => $unpaid[$u->id][0] ?? 0,
            'overdue' => $unpaid[$u->id][1] ?? false,
            'justPaid' => false,
        ]);

        return [
            'people' => $people,
            'priceUnit' => $this->group() ? 'в месяц' : 'за занятие',
            'monthly' => $this->group(),
        ];
    }

    /** Задания к занятию — те же, что на экране «Задания» (DemoHomework), как Lesson::homework(). */
    private function homeworkRows(): Collection
    {
        $group = $this->group();

        return $this->allHomeworks()
            ->filter(fn (Homework $h) => (int) $h->room_id === $this->roomId())
            ->sortByDesc(fn (Homework $h) => $h->created_at->timestamp)
            ->take(3)
            ->map(function (Homework $h) use ($group) {
                $submitted = $h->submissions->whereNotNull('submitted_at');
                $sub = $h->submissions->first();

                [$tone, $label] = match (true) {
                    $group || $h->students_count > 1 => [null, 'Сдали ' . $submitted->count() . ' из ' . $h->students_count],
                    $sub?->status === HomeworkSubmission::STATUS_REVISION_REQUESTED => ['danger', 'На доработке'],
                    $sub?->grade !== null && $sub !== null => ['ok', 'Оценка ' . $sub->grade],
                    (bool) $sub?->submitted_at => ['neutral', 'На проверке'],
                    $h->is_overdue => ['danger', 'Срок прошёл'],
                    default => ['neutral', 'Ещё не сдано'],
                };

                return [
                    'id' => $h->id,
                    'title' => $h->title,
                    'deadline' => $h->deadline && $h->deadline->isFuture() ? 'сдать ' . HumanDate::at($h->deadline) : null,
                    'tone' => $tone,
                    'label' => $label,
                    'url' => route('cabinet.teacher.task', ['homework' => $h->id]),
                ];
            })
            ->values();
    }

    /** Презентации класса и материалы, открытые этому занятию (те же файлы, что в «Материалах»). */
    private function materials(): array
    {
        $files = collect($this->room()->presentations)->map(fn (string $path) => [
            'key' => 'p-' . md5($path),
            'presentation' => md5($path),
            'name' => basename($path),
            'file' => basename($path),
            'sub' => 'Откроется в классе при старте',
            'url' => '#',
        ]);

        $shared = collect(self::sharedMaterials())
            ->filter(fn (array $f) => is_array($f[5]) && in_array($this->roomId(), $f[5], true))
            ->sortBy(fn (array $f) => $f[1])
            ->map(fn (array $f, int $id) => [
                'key' => 'm-' . $id,
                'name' => $f[1],
                'file' => $f[2],
                'thumb' => null,
                'sub' => 'Видно ученикам занятия',
                'url' => '#',
                'presentation' => null,
            ]);

        return [
            'files' => $files->concat($shared)->values(),
            'materialsUrl' => route('cabinet.teacher.materials'),
        ];
    }

    /** Файлы экрана «Материалы» (Materials::FILES), если он есть; иначе — пусто. */
    private static function sharedMaterials(): array
    {
        try {
            return (new \ReflectionClassConstant(Materials::class, 'FILES'))->getValue();
        } catch (\Throwable) {
            return [];
        }
    }

    /** Прошедшие занятия этой серии: длительность, посещаемость, долг, запись; отменённые — с причиной. */
    private function history(): Collection|array
    {
        $rows = self::demoLessons(Carbon::today()->subWeeks(5), Carbon::now())
            ->where('roomId', $this->roomId())
            ->filter(fn ($l) => $l['past'] || ($l['cancelled'] && $l['originalStart']->isPast()))
            ->map(function (array $l) {
                if ($l['cancelled']) {
                    return [
                        'id' => 'c' . $l['key'],
                        'at' => $l['originalStart'],
                        'title' => Str::ucfirst(HumanDate::day($l['originalStart'])) . ' в ' . $l['originalStart']->format('H:i'),
                        'sub' => $l['reason'] ? 'Отменено: ' . $l['reason'] : 'Отменено',
                        'unpaid' => false, 'deletion' => false, 'url' => null, 'recordingUrl' => null,
                    ];
                }

                $h = self::demoHeld($l);
                $attended = count($h['attended']);

                return [
                    'id' => 's' . $h['id'],
                    'at' => $h['started'],
                    'title' => Str::ucfirst(HumanDate::day($h['started'])) . ' в ' . $h['started']->format('H:i'),
                    'sub' => collect([
                        plural_ru($h['minutes'], 'минута', 'минуты', 'минут'),
                        $h['total'] > 1 ? 'были ' . $attended . ' из ' . $h['total'] : ($attended === 0 ? 'ученик не пришёл' : null),
                    ])->filter()->implode(' · '),
                    'unpaid' => $h['overdue'],
                    'deletion' => false,
                    'url' => $this->lessonUrl($this->roomId(), $h['id']),
                    'recordingUrl' => $attended > 0 ? route('cabinet.teacher.recordings', ['open' => $h['id']]) : null,
                ];
            })
            ->sortByDesc(fn ($r) => $r['at']->timestamp)
            ->take(30)
            ->values();

        return ['past' => $rows];
    }

    /** Отчёт о проведённом занятии. */
    private function report(array $l): array
    {
        $h = self::demoHeld($l);
        $minutes = $h['minutes'];
        $day = (int) $l['start']->format('j');
        $attendedIds = $h['attended'];
        $attendedWord = $h['total'] > 1 ? 'учеников были' : 'ученик был';
        $chat = $l['group'] ? 14 + $day % 9 : 3 + $day % 5;
        $polls = $l['group'] ? 2 : 0;
        $next = $this->following();

        $attendance = $l['participants']->map(function (User $u, int $i) use ($attendedIds, $minutes, $day) {
            if (! in_array($u->id, $attendedIds, true)) {
                return ['id' => $u->id, 'name' => $u->name, 'photo' => null, 'attended' => false, 'sub' => 'Не был на занятии', 'score' => null];
            }
            $in = $minutes - ($i + $day) % 4;
            $talk = 8 + ($u->id + $day) % 17;
            $camera = ($u->id + $day) % 5 !== 0;

            return [
                'id' => $u->id, 'name' => $u->name, 'photo' => null, 'attended' => true,
                'sub' => collect([
                    'В классе ' . plural_ru($in, 'минуту', 'минуты', 'минут') . ' из ' . $minutes,
                    'с микрофоном ' . plural_ru($talk, 'минуту', 'минуты', 'минут'),
                    $camera ? 'камера была включена' : 'без камеры',
                ])->implode(' · '),
                'score' => (6 + ($u->id + $day) % 5) . ' из ' . LessonActivityService::MAX_SCORE,
            ];
        })->values();

        // Оплата: индивидуальное — начисление ученику, если был; групповое — помесячно, начислений нет
        $payments = collect();
        if (! $l['group'] && $attendedIds) {
            $s = $l['participants']->first();
            $due = $l['start']->copy()->addDays(3);
            $unpaid = $h['overdue'] || ($s->id === 104 && $l['start']->gt(Carbon::now()->subDays(4)));
            $payments->push([
                'id' => $h['id'],
                'studentId' => $s->id,
                'name' => $s->name,
                'amount' => World::ROOMS[$l['roomId']][4],
                'status' => $unpaid ? PaymentRecord::STATUS_UNPAID : PaymentRecord::STATUS_PAID,
                'overdue' => $unpaid && $due->lt(Carbon::today()),
                'due' => HumanDate::date($due),
                'justPaid' => false,
            ]);
        }

        $deletion = $this->state('deletion', false)
            ? ['at' => HumanDate::at(Carbon::now()), 'reason' => 'Связь прервалась, и мы начали занятие заново']
            : null;

        return [
            'sub' => self::facts([
                [$h['ended']->isToday() ? 'Завершено сегодня в ' . $h['ended']->format('H:i') : 'Прошло ' . HumanDate::day($h['started'])],
                $h['started']->format('H:i') . '–' . $h['ended']->format('H:i'),
                World::repeatLabel($l['roomId']),
            ]),
            'stats' => array_values(array_filter([
                ['value' => $minutes . ' мин', 'label' => 'длительность'],
                ['value' => count($attendedIds) . ' из ' . $h['total'], 'label' => $attendedWord],
                ['value' => (string) $chat, 'label' => plural_ru($chat, 'сообщение', 'сообщения', 'сообщений', false) . ' в чате'],
                $polls ? ['value' => (string) $polls, 'label' => plural_ru($polls, 'голосование', 'голосования', 'голосований', false)] : null,
            ])),
            'attendance' => $attendance,
            'teacherMinutes' => $minutes,
            'recording' => $attendedIds ? [
                'title' => 'Запись · ' . plural_ru($minutes, 'минута', 'минуты', 'минут'),
                'sub' => HumanDate::day($h['started']) . ', ' . $h['started']->format('H:i'),
                'url' => route('cabinet.teacher.recordings', ['open' => $h['id']]),
            ] : null,
            'recordingState' => $attendedIds ? 'ready' : 'none',
            'payments' => $payments,
            'nextInfo' => $next ? [
                'when' => HumanDate::at($next['start']),
                'sub' => Str::ucfirst(World::repeatLabel($l['roomId'])) . ' · ' . plural_ru($next['duration'], 'минута', 'минуты', 'минут'),
            ] : null,
            'sameDay' => collect(),
            'deletion' => $deletion,
            'sessionTitle' => collect([$h['total'] === 1 ? $l['participants']->first()->name : null,
                HumanDate::day($h['started']) . ', ' . $h['started']->format('H:i') . '–' . $h['ended']->format('H:i')])->filter()->implode(' · '),
        ];
    }

    /** Окна экрана и их поля (публичные свойства настоящего компонента). */
    private function modals(?array $next, ?array $sessionLesson): array
    {
        $open = $this->state('open');
        $room = $this->room();
        $student = $this->student();
        $name = $student?->first_name;
        $roomId = $this->roomId();

        // В отчёте «Перенести» относится к следующему занятию
        $target = $sessionLesson ? $this->following() : ($next && ! $next['cancelled'] ? $next : null);
        $rs = $this->rescheduleState($target);
        $editKind = $this->state('editKind', $this->group() ? 'group' : 'individual') === 'group' ? 'group' : 'individual';
        $editStudents = $this->stateInts('editStudents', $room->participants->pluck('id')->all());
        if ($editKind === 'individual' && count($editStudents) > 1) {
            $editStudents = [$editStudents[0]];
        }
        $cancelScope = $this->state('cancelScope', 'one') === 'series' ? 'series' : 'one';
        $presentationKey = $this->state('confirmPresentation');
        $presentationName = collect($room->presentations)->first(fn ($p) => md5($p) === $presentationKey);

        $data = [
            'notifySub' => ($name ? $name . ' получит уведомление' : 'Ученики получат уведомление') . ' в кабинете и на телефоне',
            'cancelOpen' => $open === 'cancel' && (bool) $target,
            'cancelScope' => $cancelScope,
            'cancelScheduleId' => $roomId,
            'cancelAt' => $target ? $target['originalStart']->format('Y-m-d\TH:i') : '',
            'cancelReason' => '',
            'cancelNotify' => true,
            'cancelArchive' => false,
            'rescheduleOpen' => $open === 'reschedule',
            'rsScheduleId' => $roomId,
            'rsAt' => $target ? $target['originalStart']->format('Y-m-d\TH:i') : '',
            'rsScope' => $rs['mode'],
            'rsRepeat' => 'weekly',
            'rsDate' => $rs['date'],
            'rsTime' => $rs['time'],
            'rsDuration' => $rs['duration'],
            'rsDays' => $rs['days'],
            'rsUntil' => '',
            'rsNotify' => true,
            'lessonPlanOpen' => $open === 'lessonPlan',
            'lessonPlanBody' => implode("\n", self::PLANS[$roomId]),
            'deleteRequestOpen' => $open === 'deleteRequest',
            'deletionReason' => '',
            'editOpen' => $open === 'edit',
            'editName' => $room->name,
            'editKind' => $editKind,
            'editStudents' => $editStudents,
            'presentationsOpen' => $open === 'presentations',
            'presentationUploads' => [],
            'presentationUploadNames' => [],
            'confirmPresentation' => $presentationName ? $presentationKey : null,
            'confirmPresentationName' => $presentationName ? basename($presentationName) : null,
        ];

        if ($data['rescheduleOpen']) {
            $repeat = World::repeatLabel($roomId);
            $following = $rs['mode'] === 'following';
            $first = $following
                ? TeacherLessonService::firstOccurrence('weekly', $rs['date'], $rs['time'], $rs['days'])
                : TeacherLessonService::firstOccurrence('once', $rs['date'], $rs['time']);

            $data += [
                'rsMode' => $target ? $rs['mode'] : 'new',
                'rsSeries' => (bool) $target,
                'rsScopes' => $target ? [
                    'one' => ['Только это занятие', 'Остальные — ' . $repeat . ', без изменений'],
                    'following' => ['Это и все следующие', 'Сейчас — ' . $repeat . ' в ' . $target['originalStart']->format('H:i')],
                ] : [],
                'rsNew' => $first
                    ? ($following
                        ? TeacherScheduleService::repeatLabel(new RoomSchedule(['type' => 'recurring', 'recurrence_type' => 'weekly', 'recurrence_days' => $rs['days']])) . ' в ' . $rs['time'] . ', с ' . HumanDate::date($first)
                        : HumanDate::at($first))
                    : null,
                'rsDurations' => TeacherLessonService::durationOptions($rs['duration']),
                'rsCurrent' => $target ? HumanDate::at($target['start']) : null,
            ];
        }

        if ($data['cancelOpen']) {
            $data += [
                'cancelSeries' => true,
                'cancelScopes' => [
                    'one' => ['Только это занятие', Str::ucfirst(HumanDate::at($target['start']))],
                    'series' => ['Всю серию', Str::ucfirst(World::repeatLabel($roomId)) . ' в ' . $target['originalStart']->format('H:i') . ', начиная с ' . HumanDate::day($target['originalStart'])],
                ],
                'cancelIsSeries' => $cancelScope === 'series',
                'cancelReasonHint' => $name ? $name . ' увидит причину в своём расписании' : 'Ученики увидят причину в своём расписании',
                'cancelCanArchive' => false,
            ];
        }

        if ($data['editOpen']) {
            $options = World::students()->sortBy('name')->values();
            $count = count($editStudents);
            $current = $room->participants->pluck('id')->all();
            $added = array_diff($editStudents, $current);

            $data += [
                'editPeople' => $options->map(fn (User $u) => ['id' => (int) $u->id, 'name' => (string) $u->name, 'email' => (string) $u->email, 'photo' => null])->all(),
                'editChosen' => $options->whereIn('id', $editStudents)->values(),
                'editHint' => $editKind === 'group' && $count > 0
                    ? plural_ru($count, 'ученик', 'ученика', 'учеников') . ($count === 1 ? ' — пока занятие считается индивидуальным' : '') . ' · на «Профи» до 12 в занятии'
                    : 'Нового ученика сначала пригласите в разделе «Ученики»',
                'editNote' => $added ? (count($added) === 1 ? 'Новый ученик получит' : 'Новые ученики получат') . ' уведомление и задания занятия' : null,
            ];
        }

        if ($data['presentationsOpen']) {
            $data += [
                'presentationAccept' => TeacherLessonService::PRESENTATION_ACCEPT,
                'materialsLink' => route('cabinet.teacher.materials'),
            ];
        }

        if ($this->state('confirmStop', false)) {
            $data['stopNote'] = 'Запись появится в «Записях»' . ($this->group() ? '' : ', ученику начислится ' . Money::format(World::ROOMS[$roomId][4]) . ', если он был на занятии') . '.';
        }

        return $data;
    }
}
