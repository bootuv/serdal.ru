<?php

namespace App\Livewire\Cabinet\Teacher;

use App\Livewire\Cabinet\Teacher\Concerns\LessonRows;
use App\Livewire\Cabinet\Teacher\Concerns\MarksPayments;
use App\Livewire\Cabinet\Teacher\Concerns\PlansLessons;
use App\Livewire\Cabinet\Teacher\Concerns\StartsLessons;
use App\Livewire\Cabinet\Teacher\Concerns\TeacherScreen;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\MeetingSession;
use App\Models\Message;
use App\Models\PaymentRecord;
use App\Models\Room;
use App\Models\RoomSchedule;
use App\Models\TeacherMaterial;
use App\Models\User;
use App\Services\TeacherLessonService;
use App\Services\TeacherScheduleService;
use App\Services\TeacherStudentsService;
use App\Support\HumanDate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Занятие учителя: предстоящее (обзор, материалы, история), идущее и отчёт о проведённом.
 * Макеты: Lesson, LsLive, LsEnded, LsReschedule, LsCancel, LsDeleteRequest, LsStartBlocked (docs/design/BRAND.md).
 */
#[Layout('components.layouts.cabinet', ['title' => 'Занятие', 'active' => 'schedule'])]
class Lesson extends Component
{
    use LessonRows, MarksPayments, StartsLessons, TeacherScreen;

    public int $roomId;

    #[Url(except: 'overview')]
    public string $tab = 'overview';

    /** Проведённое занятие (MeetingSession), отчёт о котором показан. */
    #[Url(except: null)]
    public ?int $session = null;

    public bool $confirmStop = false;

    /* Отмена (LsCancel) */
    public bool $cancelOpen = false;

    /** schedule — только это правило расписания, room — всё занятие. */
    public string $cancelScope = 'schedule';

    public ?int $cancelScheduleId = null;

    /* Перенос (LsReschedule): правка правила расписания */
    public bool $rescheduleOpen = false;

    public ?int $rsScheduleId = null;

    public string $rsRepeat = 'weekly';

    public string $rsDate = '';

    public string $rsTime = '';

    public int $rsDuration = 60;

    /** @var array<int> */
    public array $rsDays = [];

    public string $rsUntil = '';

    /* Запрос удаления проведённого занятия (LsDeleteRequest) */
    public bool $deleteRequestOpen = false;

    public string $deletionReason = '';

    /** Обновляем, когда занятие начинается или завершается. */
    #[On('echo:rooms,.room.status.updated')]
    public function refreshRooms(): void {}

    public function mount(int|string $room): void
    {
        $teacher = $this->authorizeTeacher();

        $model = Room::withTrashed()->find((int) $room);
        abort_unless($model && (int) $model->user_id === $teacher->id, 404);
        $this->roomId = $model->id;

        if (! in_array($this->tab, ['overview', 'materials', 'history'], true)) {
            $this->tab = 'overview';
        }

        if ($this->session && ! $this->sessionModel()) {
            $this->session = null;
        }
    }

    /** Занятие учителя (в том числе архивное). Владение проверяется на каждом запросе. */
    private function room(): Room
    {
        $room = Room::withTrashed()->with(['participants:id,name,avatar', 'schedules'])->find($this->roomId);
        abort_unless($room && (int) $room->user_id === auth()->id(), 404);

        return $room;
    }

    private function sessionModel(): ?MeetingSession
    {
        return $this->session
            ? MeetingSession::where('room_id', $this->roomId)->find($this->session)
            : null;
    }

    public function updatedTab(): void
    {
        if (! in_array($this->tab, ['overview', 'materials', 'history'], true)) {
            $this->tab = 'overview';
        }
    }

    /*
     |--------------------------------------------------------------------------
     | Завершить занятие
     |--------------------------------------------------------------------------
     | Само завершение — rooms.stop (RoomController::stop), он возвращает назад. Перед переходом
     | запоминаем в адресе идущее занятие, чтобы после завершения открылся его отчёт.
     */

    public function askStop(): void
    {
        $running = MeetingSession::where('room_id', $this->roomId)->where('status', 'running')->latest('started_at')->first();
        $this->session = $running?->id;
        $this->confirmStop = true;
    }

    public function keepRunning(): void
    {
        $this->confirmStop = false;
        $this->session = null;
    }

    /*
     |--------------------------------------------------------------------------
     | Перенести: изменить правило расписания (как в EditRoom; «только это занятие» в продукте нет)
     |--------------------------------------------------------------------------
     */

    public function openReschedule(?int $scheduleId = null): void
    {
        $room = $this->room();
        abort_if($room->trashed(), 403);

        $schedule = $scheduleId
            ? $room->schedules->firstWhere('id', $scheduleId)
            : (app(TeacherScheduleService::class)->nextOccurrence($room)['schedule'] ?? $room->schedules->first());

        $this->resetValidation();
        $this->rsScheduleId = $schedule?->id;
        $this->fillReschedule($schedule);
        $this->rescheduleOpen = true;
    }

    /** Переключение между правилами расписания занятия в окне переноса. */
    public function updatedRsScheduleId(): void
    {
        $schedule = $this->room()->schedules->firstWhere('id', (int) $this->rsScheduleId);
        $this->fillReschedule($schedule);
    }

    private function fillReschedule(?RoomSchedule $schedule): void
    {
        $next = $schedule?->getNextOccurrence();
        $default = now()->addHour()->startOfHour();

        if ($schedule?->type === 'once') {
            $this->rsRepeat = 'once';
            $this->rsDate = ($schedule->scheduled_at ?? $default)->format('Y-m-d');
            $this->rsTime = ($schedule->scheduled_at ?? $default)->format('H:i');
            $this->rsDays = [];
            $this->rsUntil = '';
        } elseif ($schedule) {
            $this->rsRepeat = 'weekly';
            $this->rsDate = ($next ?? $default)->format('Y-m-d');
            $this->rsTime = substr((string) $schedule->recurrence_time, 0, 5) ?: $default->format('H:i');
            $this->rsDays = match ($schedule->recurrence_type) {
                'daily' => [0, 1, 2, 3, 4, 5, 6],
                'weekly' => array_map('intval', $schedule->recurrence_days ?? []),
                default => [($next ?? $default)->dayOfWeek],
            };
            $this->rsUntil = $schedule->end_date?->format('Y-m-d') ?? '';
        } else {
            $this->rsRepeat = 'weekly';
            $this->rsDate = $default->format('Y-m-d');
            $this->rsTime = $default->format('H:i');
            $this->rsDays = [$default->dayOfWeek];
            $this->rsUntil = '';
        }

        $this->rsDuration = (int) ($schedule?->duration_minutes ?: 60);
    }

    public function toggleRsDay(int $day): void
    {
        if ($day >= 0 && $day <= 6) {
            $this->rsDays = in_array($day, $this->rsDays, true) ? array_values(array_diff($this->rsDays, [$day])) : [...$this->rsDays, $day];
        }
    }

    public function closeReschedule(): void
    {
        $this->rescheduleOpen = false;
    }

    public function saveReschedule(): void
    {
        $room = $this->room();
        abort_if($room->trashed(), 403);
        $weekly = $this->rsRepeat === 'weekly';

        $this->validate([
            'rsRepeat' => ['required', Rule::in(['once', 'weekly'])],
            'rsDate' => ['required', 'date'],
            'rsTime' => ['required', 'date_format:H:i'],
            'rsDuration' => ['required', 'integer', 'min:1', 'max:1440'],
            'rsDays' => [Rule::requiredIf($weekly), 'array'],
            'rsDays.*' => ['integer', 'between:0,6'],
            'rsUntil' => ['nullable', 'date', 'after_or_equal:rsDate'],
        ], [
            'rsDate.required' => 'Укажите дату',
            'rsTime.required' => 'Укажите время',
            'rsTime.date_format' => 'Время в формате 17:00',
            'rsDays.required' => 'Выберите дни недели',
            'rsUntil.after_or_equal' => 'Дата окончания раньше новой даты',
        ]);

        $attributes = TeacherLessonService::scheduleAttributes($this->rsRepeat, $this->rsDate, $this->rsTime, $this->rsDuration, $this->rsDays, $this->rsUntil ?: null);
        $service = app(TeacherLessonService::class);
        $schedule = $this->rsScheduleId ? $room->schedules->firstWhere('id', $this->rsScheduleId) : null;

        if ($schedule) {
            $service->updateSchedule($schedule, $attributes, auth()->user());
        } else {
            $service->addSchedule($room, $attributes, auth()->user());
        }

        $this->rescheduleOpen = false;
        $first = TeacherLessonService::firstOccurrence($this->rsRepeat, $this->rsDate, $this->rsTime, $this->rsDays);
        $this->dispatch('toast', message: ($weekly
            ? 'Расписание изменено: ' . TeacherScheduleService::repeatLabel(new RoomSchedule($attributes)) . ' в ' . $this->rsTime
            : 'Занятие перенесено на ' . ($first ? HumanDate::at($first) : $this->rsTime))
            . ($room->participants->isNotEmpty() ? ', ученики получат уведомление' : ''));
    }

    /*
     |--------------------------------------------------------------------------
     | Отменить: удалить правило расписания или всё занятие (как в старом кабинете), с уведомлением
     |--------------------------------------------------------------------------
     */

    public function openCancel(): void
    {
        $room = $this->room();
        abort_if($room->trashed(), 403);

        $next = app(TeacherScheduleService::class)->nextOccurrence($room);
        $this->cancelScheduleId = $next['schedule']?->id ?? $room->schedules->first()?->id;
        $this->cancelScope = $room->schedules->count() > 1 ? 'schedule' : 'room';
        $this->cancelOpen = true;
    }

    public function closeCancel(): void
    {
        $this->cancelOpen = false;
    }

    public function confirmCancel()
    {
        $room = $this->room();
        abort_if($room->trashed(), 403);
        $service = app(TeacherLessonService::class);
        $schedule = $room->schedules->firstWhere('id', $this->cancelScheduleId);

        if ($this->cancelScope === 'schedule' && $schedule && $room->schedules->count() > 1) {
            $service->deleteSchedule($schedule, auth()->user());
            $this->cancelOpen = false;
            $this->dispatch('toast', message: $schedule->type === 'once' ? 'Занятие отменено' : 'Серия занятий отменена');

            return null;
        }

        $service->archive($room, auth()->user());

        return $this->redirect(Route::has('cabinet.teacher.schedule') ? route('cabinet.teacher.schedule') : url('/tutor/schedule-calendar'));
    }

    /*
     |--------------------------------------------------------------------------
     | Запросить удаление проведённого занятия (RequestSessionDeletionAction)
     |--------------------------------------------------------------------------
     */

    public function openDeleteRequest(): void
    {
        $this->resetValidation();
        $this->deletionReason = '';
        $this->deleteRequestOpen = true;
    }

    public function closeDeleteRequest(): void
    {
        $this->deleteRequestOpen = false;
    }

    public function sendDeleteRequest(): void
    {
        $this->validate(['deletionReason' => ['required', 'string', 'min:3', 'max:2000']], [
            'deletionReason.required' => 'Укажите причину',
            'deletionReason.min' => 'Опишите причину чуть подробнее',
        ]);

        $session = $this->sessionModel();
        abort_unless($session && $session->status === 'completed', 404);

        if (! $session->deletion_requested_at) {
            $session->requestDeletion(trim($this->deletionReason), auth()->user());
        }

        $this->deleteRequestOpen = false;
        $this->dispatch('toast', message: 'Запрос отправлен, решение придёт в уведомлениях');
    }

    public function revokeDeleteRequest(): void
    {
        $session = $this->sessionModel();
        abort_unless($session, 404);

        if ($session->deletion_requested_at) {
            $session->cancelDeletionRequest();
            $this->dispatch('toast', message: 'Запрос отозван, занятие остаётся в истории');
        }
    }

    /*
     |--------------------------------------------------------------------------
     | Экран
     |--------------------------------------------------------------------------
     */

    public function render()
    {
        $teacher = auth()->user();
        $room = $this->room();
        $session = $this->sessionModel();
        $service = app(TeacherScheduleService::class);

        $mode = match (true) {
            $session && $session->status === 'completed' && ! $this->confirmStop => 'report',
            (bool) $room->is_running => 'live',
            default => 'upcoming',
        };

        $next = $room->trashed() ? null : $service->nextOccurrence($room);
        $running = $mode === 'live'
            ? MeetingSession::where('room_id', $room->id)->where('status', 'running')->latest('started_at')->first()
            : null;
        $startBlock = $this->startBlock($teacher);
        $participants = $room->participants;
        $group = $participants->count() > 1 || $room->type === 'group';

        $data = [
            'room' => $room,
            'mode' => $mode,
            'group' => $group,
            'archived' => $room->trashed(),
            'next' => $next,
            'startBlock' => $startBlock,
            'backUrl' => Route::has('cabinet.teacher.schedule') ? route('cabinet.teacher.schedule') : url('/tutor/schedule-calendar'),
            'chatUrl' => url('/tutor/messenger?room=' . $room->id),
            'taskUrl' => Route::has('cabinet.teacher.task-new') ? route('cabinet.teacher.task-new', ['room' => $room->id]) : url('/tutor/homework/create'),
            'recordingsUrl' => Route::has('cabinet.teacher.recordings') ? route('cabinet.teacher.recordings') : url('/tutor/recordings'),
            'editUrl' => url('/tutor/rooms/' . $room->id . '/edit'),
            'whoLine' => $room->name . ($group ? '' : ($participants->first() ? ' · ' . $participants->first()->name : '')),
            'otherRunning' => $this->otherRunningRoomId($teacher, $room->id) !== null,
            'homework' => $this->homework($room),
        ];

        $data += match ($mode) {
            'report' => $this->report($teacher, $room, $session, $next),
            'live' => $this->live($teacher, $room, $running, $next),
            default => $this->upcoming($teacher, $room, $next),
        };

        if ($mode !== 'report') {
            $data += $this->people($teacher, $room);
            $data += match ($this->tab) {
                'materials' => $this->materials($room),
                'history' => $this->history($teacher, $room),
                default => [],
            };
        }

        return view('livewire.cabinet.teacher.lesson', $data + $this->modals($room, $next, $session) + $this->markPaidView($teacher))
            ->title($room->name);
    }

    /** «Сегодня, 16:00–17:00», «Чт, 3 октября, 16:00–17:00». */
    private static function when(Carbon $start, Carbon $end): string
    {
        return Str::ucfirst(HumanDate::day($start)) . ', ' . $start->format('H:i') . '–' . $end->format('H:i');
    }

    /** Строка фактов под заголовком: части через «·», срочное — жирным. */
    private static function facts(array $parts): HtmlString
    {
        return new HtmlString(collect($parts)->filter()->map(fn ($p) => is_array($p)
            ? '<span class="font-semibold text-ink">' . e($p[0]) . '</span>'
            : e($p))->implode(' · '));
    }

    private function upcoming(User $teacher, Room $room, ?array $next): array
    {
        $parts = match (true) {
            $room->trashed() => [['Занятие в архиве']],
            ! $next => [['Время не назначено']],
            default => [
                self::when($next['start'], $next['end']),
                $next['start']->isFuture()
                    ? ($next['start']->isToday() ? ['начнётся ' . HumanDate::until($next['start'])] : 'начнётся ' . HumanDate::until($next['start']))
                    : ['идёт по расписанию'],
                TeacherScheduleService::repeatLabel($next['schedule']),
            ],
        };

        return ['sub' => self::facts($parts)];
    }

    private function live(User $teacher, Room $room, ?MeetingSession $running, ?array $next): array
    {
        $startedAt = $running?->started_at;
        $minutes = $startedAt ? max(1, (int) $startedAt->diffInMinutes(now())) : null;
        $snapshot = $running?->settings_snapshot ?? [];

        // Кто сейчас в классе: участники из вебхуков BBB, которые ещё не вышли
        $present = collect($running?->analytics_data['participants'] ?? [])
            ->filter(function ($p) {
                $joined = $p['last_joined_at'] ?? $p['joined_at'] ?? null;
                $left = $p['left_at'] ?? null;

                return ! $left || ($joined && Carbon::parse($joined)->gt(Carbon::parse($left)));
            })
            ->map(function ($p) use ($teacher, $startedAt) {
                $id = is_numeric($p['user_id'] ?? null) ? (int) $p['user_id'] : null;
                $at = isset($p['joined_at']) ? Carbon::parse($p['joined_at'])->timezone(config('app.timezone'))->format('H:i') : null;

                return $id === $teacher->id
                    ? ['me' => true, 'id' => $id, 'name' => 'Вы', 'avatar' => $teacher->name, 'sub' => 'Ведёте занятие' . ($startedAt ? ' с ' . $startedAt->format('H:i') : '')]
                    : ['me' => false, 'id' => $id ?? 0, 'name' => $p['full_name'] ?? 'Гость', 'avatar' => $p['full_name'] ?? 'Гость', 'sub' => $at ? 'Подключился в ' . $at : 'В классе'];
            })
            ->sortBy(fn ($p) => $p['me'] ? 1 : 0)
            ->values();

        return [
            'sub' => self::facts([
                [$minutes ? 'Идёт ' . plural_ru($minutes, 'минуту', 'минуты', 'минут') : 'Идёт сейчас'],
                ! empty($snapshot['record']) && ! empty($snapshot['autoStartRecording']) ? 'Идёт запись' : null,
                $next ? self::when($next['start'], $next['end']) : null,
                TeacherScheduleService::repeatLabel($next['schedule'] ?? null),
            ]),
            'present' => $present,
            'guestUrl' => route('rooms.join', $room),
            'joinUrl' => route('rooms.connect', $room),
            'stopUrl' => route('rooms.stop', $room),
        ];
    }

    /** Ученики занятия, цена и неоплаченное (правая колонка обзора). */
    private function people(User $teacher, Room $room): array
    {
        $students = app(TeacherStudentsService::class);
        $lessonType = $teacher->lessonTypes()->where('type', $room->type === 'group' ? 'group' : 'individual')->first();
        $unpaid = PaymentRecord::unpaid()->where('teacher_id', $teacher->id)
            ->whereIn('student_id', $room->participants->pluck('id'))
            ->get()
            ->groupBy('student_id');

        $people = $room->participants->map(function (User $u) use ($teacher, $students, $room, $unpaid) {
            $since = $students->since($teacher, $u->id);
            $own = $unpaid->get($u->id, collect());

            return [
                'id' => $u->id,
                'name' => $u->name,
                'since' => $since ? 'Занимается с ' . HumanDate::month($since, true) : null,
                'url' => Route::has('cabinet.teacher.student') ? route('cabinet.teacher.student', $u->id) : url('/tutor/students/' . $u->id),
                'price' => $room->getEffectivePrice($u->id),
                'unpaid' => $own->count(),
                'overdue' => $own->contains(fn (PaymentRecord $r) => $r->isOverdue()),
                'justPaid' => isset($this->justPaid[$u->id]),
            ];
        });

        return [
            'people' => $people,
            'priceUnit' => $lessonType?->isMonthly() ? 'в месяц' : 'за занятие',
            'monthly' => (bool) $lessonType?->isMonthly(),
        ];
    }

    /** Задания, выданные к занятию, со статусом сдачи. */
    private function homework(Room $room): Collection
    {
        $group = $room->participants->count() > 1;

        return Homework::where('room_id', $room->id)
            ->with('submissions')
            ->withCount('students')
            ->latest()
            ->limit(3)
            ->get()
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
                    'url' => url('/tutor/homework/' . $h->id),
                ];
            });
    }

    /** Материалы к занятию: презентации класса и материалы, открытые этому занятию. */
    private function materials(Room $room): array
    {
        $files = collect($room->presentations ?? [])->map(fn (string $path) => [
            'key' => 'p-' . md5($path),
            'name' => basename($path),
            'sub' => 'Откроется в классе при старте',
            'url' => Storage::disk('s3')->url($path),
        ]);

        $shared = TeacherMaterial::where('teacher_id', $room->user_id)
            ->where('visibility', TeacherMaterial::VISIBILITY_ROOMS)
            ->whereHas('rooms', fn ($q) => $q->where('rooms.id', $room->id))
            ->orderBy('title')
            ->get()
            ->map(fn (TeacherMaterial $m) => [
                'key' => 'm-' . $m->id,
                'name' => $m->title ?: $m->original_name,
                'file' => $m->original_name ?: $m->file_path,
                'sub' => 'Видно ученикам занятия',
                'url' => $m->file_url,
            ]);

        return [
            'files' => $files->concat($shared)->values(),
            'materialsUrl' => Route::has('cabinet.teacher.materials') ? route('cabinet.teacher.materials') : url('/tutor/materials'),
        ];
    }

    /** Прошедшие занятия: длительность, посещаемость, долг, запись. */
    private function history(User $teacher, Room $room): array
    {
        $sessions = app(TeacherScheduleService::class)->sessions($teacher->id, Carbon::create(2000), null, $room->id)->take(30);
        $overdue = TeacherScheduleService::overdueSessionIds($teacher->id, $sessions->pluck('id'));
        $recordings = $this->readyRecordings($sessions);

        return [
            'past' => $sessions->map(function (MeetingSession $s) use ($overdue, $recordings) {
                $att = TeacherScheduleService::attendance($s);

                return [
                    'id' => $s->id,
                    'title' => Str::ucfirst(HumanDate::day($s->started_at)) . ' в ' . $s->started_at->format('H:i'),
                    'sub' => collect([
                        plural_ru(TeacherScheduleService::sessionMinutes($s), 'минута', 'минуты', 'минут'),
                        $att['total'] > 1 ? 'были ' . $att['attended'] . ' из ' . $att['total'] : ($att['total'] === 1 && ! $att['attended'] ? 'ученик не пришёл' : null),
                    ])->filter()->implode(' · '),
                    'unpaid' => in_array($s->id, $overdue, true),
                    'deletion' => (bool) $s->deletion_requested_at,
                    'url' => $this->lessonUrl($s->room_id, $s->id),
                    'recordingUrl' => isset($recordings[$s->id]) ? $this->recordingUrl($recordings[$s->id]) : null,
                ];
            }),
        ];
    }

    /** Отчёт о проведённом занятии (LsEnded, LsDeleteRequest). */
    private function report(User $teacher, Room $room, MeetingSession $s, ?array $next): array
    {
        $att = TeacherScheduleService::attendance($s);
        $minutes = TeacherScheduleService::sessionMinutes($s);
        $ended = $s->ended_at ?? $s->started_at;
        $schedule = $next['schedule'] ?? $room->schedules->first();

        $recording = $this->readyRecordings(collect([$s]))[$s->id] ?? null;
        $recordEnabled = ! empty($s->settings_snapshot['record']);

        $chat = Message::where('room_id', $room->id)
            ->whereBetween('created_at', [$s->started_at, $s->ended_at ?? $s->started_at->copy()->addHours(4)])
            ->count();

        $records = PaymentRecord::where('meeting_session_id', $s->id)->where('teacher_id', $teacher->id)
            ->with(['student:id,name', 'meetingSession:id,pricing_snapshot'])
            ->get();

        $sameDay = MeetingSession::where('room_id', $room->id)
            ->where('id', '!=', $s->id)
            ->where('status', 'completed')
            ->whereDate('started_at', $s->started_at->toDateString())
            ->orderBy('started_at')
            ->get();

        $attendedWord = $att['total'] > 1 ? 'учеников были' : 'ученик был';

        return [
            'sub' => self::facts([
                [$ended->isToday() ? 'Завершено сегодня в ' . $ended->format('H:i') : 'Прошло ' . HumanDate::day($s->started_at)],
                $s->started_at->format('H:i') . '–' . $ended->format('H:i'),
                TeacherScheduleService::repeatLabel($schedule),
            ]),
            'stats' => array_values(array_filter([
                ['value' => $minutes . ' мин', 'label' => 'длительность'],
                $att['total'] ? ['value' => $att['attended'] . ' из ' . $att['total'], 'label' => $attendedWord] : null,
                ['value' => (string) $chat, 'label' => plural_ru($chat, 'сообщение', 'сообщения', 'сообщений', false) . ' в чате'],
            ])),
            'attendance' => $att['students'],
            'teacherMinutes' => $minutes,
            'recording' => $recording ? [
                'title' => 'Запись · ' . plural_ru($minutes, 'минута', 'минуты', 'минут'),
                'sub' => HumanDate::day($s->started_at) . ', ' . $s->started_at->format('H:i'),
                'url' => $this->recordingUrl($recording),
            ] : null,
            'recordingState' => match (true) {
                (bool) $recording => 'ready',
                $recordEnabled => 'processing',
                default => 'none',
            },
            'payments' => $records->map(fn (PaymentRecord $r) => [
                'id' => $r->id,
                'studentId' => $r->student_id,
                'name' => $r->student?->name,
                'amount' => $r->amount(),
                'status' => $r->status,
                'overdue' => $r->isOverdue(),
                'due' => $r->due_date ? HumanDate::date($r->due_date) : null,
                'justPaid' => in_array($r->id, $this->justPaid[$r->student_id] ?? [], true),
            ]),
            'nextInfo' => $next ? [
                'when' => HumanDate::at($next['start']),
                'sub' => collect([Str::ucfirst((string) TeacherScheduleService::repeatLabel($next['schedule'])), plural_ru((int) $next['start']->diffInMinutes($next['end']), 'минута', 'минуты', 'минут')])->filter()->implode(' · '),
            ] : null,
            'sameDay' => $sameDay->map(fn (MeetingSession $o) => [
                'id' => $o->id,
                'title' => $o->started_at->format('H:i') . '–' . ($o->ended_at ?? $o->started_at)->format('H:i') . ' · ' . plural_ru(TeacherScheduleService::sessionMinutes($o), 'минута', 'минуты', 'минут'),
                'url' => $this->lessonUrl($room->id, $o->id),
            ]),
            'deletion' => $s->deletion_requested_at ? [
                'at' => HumanDate::at($s->deletion_requested_at),
                'reason' => $s->deletion_reason,
            ] : null,
            'sessionTitle' => collect([$att['total'] === 1 ? $att['students']->first()['name'] : null, HumanDate::day($s->started_at) . ', ' . $s->started_at->format('H:i') . '–' . $ended->format('H:i')])->filter()->implode(' · '),
        ];
    }

    /** Данные окон: перенос, отмена, завершение. */
    private function modals(Room $room, ?array $next, ?MeetingSession $session): array
    {
        $data = [];

        if ($this->rescheduleOpen) {
            $first = TeacherLessonService::firstOccurrence($this->rsRepeat, $this->rsDate, $this->rsTime, $this->rsDays);
            $data['rsSchedules'] = $room->schedules->count() > 1
                ? $room->schedules->mapWithKeys(fn (RoomSchedule $s) => [$s->id => $s->type === 'once'
                    ? ($s->scheduled_at ? HumanDate::at($s->scheduled_at) : 'Разовое')
                    : Str::ucfirst((string) TeacherScheduleService::repeatLabel($s)) . ' в ' . substr((string) $s->recurrence_time, 0, 5)])->all()
                : [];
            $data['rsNew'] = $first
                ? ($this->rsRepeat === 'weekly'
                    ? TeacherScheduleService::repeatLabel(new RoomSchedule(['type' => 'recurring', 'recurrence_type' => 'weekly', 'recurrence_days' => $this->rsDays])) . ' в ' . $this->rsTime . ', с ' . HumanDate::date($first)
                    : HumanDate::at($first))
                : null;
            $data['rsDurations'] = TeacherLessonService::durationOptions($this->rsDuration);
            $data['rsCurrent'] = $next ? HumanDate::at($next['start']) : null;
        }

        if ($this->cancelOpen) {
            $schedule = $room->schedules->firstWhere('id', $this->cancelScheduleId);
            $data['cancelSchedule'] = $schedule ? [
                'once' => $schedule->type === 'once',
                'label' => $schedule->type === 'once'
                    ? ($schedule->scheduled_at ? Str::ucfirst(HumanDate::at($schedule->scheduled_at)) : 'Разовое занятие')
                    : Str::ucfirst((string) TeacherScheduleService::repeatLabel($schedule)) . ' в ' . substr((string) $schedule->recurrence_time, 0, 5),
            ] : null;
            $data['cancelMany'] = $room->schedules->count() > 1;
        }

        if ($this->confirmStop) {
            $lessonType = $room->user?->lessonTypes()->where('type', $room->type ?? 'individual')->first();
            $price = $room->participants->count() === 1 ? $room->getEffectivePrice($room->participants->first()->id) : null;
            $data['stopNote'] = collect([
                'Запись появится в «Записях»',
                ! $lessonType?->isMonthly() && $price ? 'ученику начислится ' . \App\Support\Money::format($price) . ', если он был на занятии' : null,
            ])->filter()->implode(', ') . '.';
        }

        return $data;
    }
}
