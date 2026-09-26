<?php

namespace App\Livewire\Cabinet\Teacher;

use App\Livewire\Cabinet\Teacher\Concerns\TeacherScreen;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\PaymentRecord;
use App\Models\Room;
use App\Models\User;
use App\Services\PaymentRecordService;
use App\Services\StudentPerformanceService;
use App\Services\TeacherScheduleService;
use App\Services\TeacherStudentsService;
use App\Support\HumanDate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Карточка ученика. Макеты: «Учитель · Ученик» (TeacherStudent) и окна PmMarkPaid, PmWaive, PmExtend,
 * PmSettings, PmAssign, PmRemove. Логика — TeacherStudentsService (общая со старым StudentResource).
 */
#[Layout('components.layouts.cabinet', ['title' => 'Ученик', 'active' => 'students'])]
class Student extends Component
{
    use TeacherScreen;

    #[Locked]
    public User $student;

    #[Url(except: 'overview')]
    public string $tab = 'overview';

    /** Открытое окно: mark | waive | extend | settings | assign | remove. */
    public ?string $modal = null;

    /** Начисления, выбранные в окнах «Отметить оплату» и «Не требовать оплату». */
    public array $selected = [];

    public int $extendDays = 3;

    public bool $settingsFree = false;

    public string $settingsType = 'default';

    /** Занятия, отмеченные в окне «Занятия ученика». */
    public array $roomIds = [];

    /** Только что отмеченные как оплаченные начисления — для «Отменить». */
    public array $justPaid = [];

    public function mount(User $student): void
    {
        $teacher = $this->authorizeTeacher();
        abort_unless($this->service()->owns($teacher, $student->id), 404);

        $this->student = $student;

        if (! in_array($this->tab, ['overview', 'lessons', 'tasks', 'pay'], true)) {
            $this->tab = 'overview';
        }
    }

    private function service(): TeacherStudentsService
    {
        return app(TeacherStudentsService::class);
    }

    private function teacher(): User
    {
        return auth()->user();
    }

    private function unpaid(): Collection
    {
        return $this->service()->unpaidRecords($this->teacher(), $this->student->id);
    }

    /*
     | Окна
     */

    public function openModal(string $name): void
    {
        $this->resetErrorBag();

        match ($name) {
            'mark', 'waive' => $this->selected = $this->unpaid()->pluck('id')->all(),
            'extend' => $this->extendDays = 3,
            'settings' => [
                $this->settingsFree = $this->service()->isFree($this->teacher(), $this->student->id),
                $this->settingsType = $this->service()->paymentTypeOverride($this->teacher(), $this->student->id) ?? 'default',
            ],
            'assign' => $this->roomIds = $this->service()->studentRooms($this->teacher(), $this->student)->pluck('id')->all(),
            'remove' => null,
            default => abort(404),
        };

        $this->modal = $name;
    }

    public function closeModal(): void
    {
        $this->modal = null;
    }

    public function toggleAllRecords(): void
    {
        $all = $this->unpaid()->pluck('id')->all();
        $this->selected = count(array_intersect($all, $this->selectedIds())) === count($all) ? [] : $all;
    }

    /*
     | Оплата
     */

    public function markPaid(): void
    {
        if (! $this->requireSelection()) {
            return;
        }

        $marked = $this->service()->markRecords($this->teacher(), $this->student->id, $this->selected, PaymentRecord::STATUS_PAID);
        $this->justPaid = $marked->pluck('id')->all();
        $this->modal = null;

        $sum = $this->service()->amountLabel($marked);
        $this->dispatch('toast', message: 'Оплата отмечена' . ($sum ? ' · ' . $sum : ''));
    }

    public function undoPaid(): void
    {
        $this->service()->undoPaid($this->teacher(), $this->student->id, $this->justPaid);
        $this->justPaid = [];
        $this->dispatch('toast', message: 'Отметка оплаты отменена');
    }

    public function waive(): void
    {
        if (! $this->requireSelection()) {
            return;
        }

        $this->service()->markRecords($this->teacher(), $this->student->id, $this->selected, PaymentRecord::STATUS_CANCELLED);
        $this->modal = null;
        $this->dispatch('toast', message: 'Оплата не требуется');
    }

    public function extend(): void
    {
        $due = $this->service()->extendRecords($this->teacher(), $this->student->id, $this->unpaid()->pluck('id')->all(), $this->extendDays);
        $this->modal = null;
        $this->dispatch('toast', message: $due ? 'Срок продлён до ' . HumanDate::date($due) : 'Продлевать нечего — долгов нет');
    }

    public function saveSettings(): void
    {
        $result = $this->service()->applyPaymentSettings(
            $this->teacher(),
            $this->student,
            $this->settingsFree,
            $this->settingsType === 'default' ? null : $this->settingsType,
        );

        $this->modal = null;
        $this->justPaid = [];
        $this->dispatch('toast', message: match (true) {
            $result['free_changed'] && $result['is_free'] => 'Сохранено: ученик занимается бесплатно',
            $result['free_changed'] => 'Сохранено: оплата снова отслеживается',
            $result['override_changed'] => 'Условия оплаты сохранены',
            default => 'Изменений нет',
        });
    }

    /** @return array<int> */
    private function selectedIds(): array
    {
        return array_map('intval', $this->selected);
    }

    private function requireSelection(): bool
    {
        $valid = $this->unpaid()->pluck('id')->intersect($this->selectedIds());

        if ($valid->isEmpty()) {
            $this->addError('selected', 'Отметьте хотя бы одно занятие');

            return false;
        }

        $this->selected = $valid->values()->all();

        return true;
    }

    /*
     | Занятия и список
     */

    public function saveRooms(): void
    {
        ['added' => $added, 'removed' => $removed] = $this->service()->syncRooms($this->teacher(), $this->student, $this->roomIds);

        $this->modal = null;
        $this->dispatch('toast', message: $added->isEmpty() && $removed->isEmpty() ? 'Изменений нет' : 'Занятия ученика сохранены');
    }

    public function remove(): void
    {
        $this->service()->removeFromList($this->teacher(), $this->student);

        session()->flash('cabinet_toast', $this->student->name . ' больше не в вашем списке');
        $this->redirect($this->studentsUrl());
    }

    private function studentsUrl(): string
    {
        return Route::has('cabinet.teacher.students') ? route('cabinet.teacher.students') : url('/tutor/students');
    }

    /*
     | Данные экрана
     */

    public function render()
    {
        $teacher = $this->teacher();
        $student = $this->student;
        $rooms = $this->service()->studentRooms($teacher, $student)->load('schedules');
        $unpaid = $this->unpaid();
        $isFree = $this->service()->isFree($teacher, $student->id);
        $firstName = $student->first_name ?: Str::before(trim($student->name), ' ');
        $next = $this->nextLesson($rooms);
        $since = $this->service()->since($teacher, $student->id);
        $justPaid = $this->justPaid
            ? PaymentRecord::whereIn('id', $this->justPaid)->where('teacher_id', $teacher->id)->where('status', PaymentRecord::STATUS_PAID)->with('meetingSession.room')->get()
            : collect();

        return view('livewire.cabinet.teacher.student', [
            'firstName' => $firstName,
            'facts' => array_filter([
                $rooms->isNotEmpty() ? $rooms->map(fn (Room $r) => $r->name)->take(2)->implode(', ')
                    . ' · ' . ($rooms->contains(fn (Room $r) => $r->type === 'group') ? ($rooms->count() > 1 ? 'лично и в группе' : 'в группе') : 'индивидуально') : null,
                $since ? 'с ' . HumanDate::date($since) : null,
            ]),
            'since' => $since ? HumanDate::date($since) : null,
            'next' => $next,
            'backUrl' => $this->studentsUrl(),
            'chatUrl' => $this->service()->chatUrl($teacher, $student->id),
            'planUrl' => Route::has('cabinet.teacher.schedule')
                ? route('cabinet.teacher.schedule', ['plan' => 1, 'student' => $student->id])
                : url('/tutor/rooms/create'),
            'taskNewUrl' => Route::has('cabinet.teacher.task-new')
                ? route('cabinet.teacher.task-new', ['student' => $student->id])
                : url('/tutor/homework/create'),
            'scheduleUrl' => Route::has('cabinet.teacher.schedule') ? route('cabinet.teacher.schedule') : url('/tutor/schedule-calendar'),
            'pricesUrl' => Route::has('cabinet.teacher.profile') ? route('cabinet.teacher.profile', ['tab' => 'prices']) : url('/tutor/prices'),
            'tabCounts' => ['pay' => $isFree ? 0 : $unpaid->count()],
            'isFree' => $isFree,
            'dues' => $isFree ? collect() : $unpaid->map(fn (PaymentRecord $r) => $this->dueRow($r)),
            'dueSum' => $this->service()->amountLabel($unpaid),
            'dueCount' => $this->service()->countLabel($unpaid),
            'dueOverdue' => $unpaid->contains(fn (PaymentRecord $r) => $r->isOverdue()),
            'paidNow' => $justPaid->map(fn (PaymentRecord $r) => [
                'title' => $r->human_label,
                'amount' => ($a = $r->amount()) ? TeacherStudentsService::rub($a) : null,
            ]),
            'rooms' => $rooms,
            'roomsLine' => $rooms->isNotEmpty() ? $rooms->pluck('name')->implode(', ') : 'Пока ни одного занятия',
            'contacts' => $this->contacts($student),
            'metrics' => $this->tab === 'overview' ? app(StudentPerformanceService::class)->metrics($student, $teacher->id) : null,
            'perfSub' => $this->tab === 'overview' ? $this->perfSub($student, $teacher) : null,
            'homework' => in_array($this->tab, ['overview', 'tasks'], true) ? $this->homework($teacher, $student) : collect(),
            'upcoming' => $this->tab === 'lessons' ? $this->upcoming($teacher, $student) : collect(),
            'past' => $this->tab === 'lessons' ? $this->past($teacher, $student, $unpaid) : null,
            'history' => $this->tab === 'pay' ? $this->payHistory($teacher, $student) : collect(),
            'terms' => $this->tab === 'pay' || $this->modal === 'settings' ? $this->service()->paymentTerms($teacher, $student) : null,
            'debtStatus' => $unpaid->contains(fn (PaymentRecord $r) => $r->isOverdue()) ? PaymentRecordService::debtStatus($student->id, $teacher->id) : null,
            'modalData' => $this->modalData($teacher, $unpaid, $next),
        ])->title($student->name);
    }

    /** Ближайшее занятие ученика у учителя: идущее сейчас или с ближайшим началом. */
    private function nextLesson(Collection $rooms): ?array
    {
        $room = $rooms
            ->filter(fn (Room $r) => $r->is_running
                || ($r->next_start && $r->next_start->copy()->addMinutes($r->duration ?: 45)->isFuture()))
            ->sortBy(fn (Room $r) => [$r->is_running ? 0 : 1, $r->next_start?->timestamp ?? PHP_INT_MAX])
            ->first();

        if (! $room) {
            return null;
        }

        return [
            'when' => $room->is_running ? 'идёт сейчас' : HumanDate::at($room->next_start),
            'href' => $this->lessonUrl($room),
        ];
    }

    private function lessonUrl(Room|int $room): string
    {
        $id = $room instanceof Room ? $room->id : $room;

        return Route::has('cabinet.teacher.lesson') ? route('cabinet.teacher.lesson', $id) : url('/tutor/rooms/' . $id);
    }

    private function dueRow(PaymentRecord $r): array
    {
        $overdue = $r->isOverdue();

        return [
            'id' => $r->id,
            'title' => $r->human_label,
            'overdue' => $overdue,
            'hint' => match (true) {
                ! $r->due_date => null,
                $overdue => 'Срок был ' . HumanDate::date($r->due_date),
                default => 'Оплатить до ' . HumanDate::date($r->due_date),
            },
            'amount' => ($a = $r->amount()) ? TeacherStudentsService::rub($a) : null,
        ];
    }

    /** Контакты для связи: почта, телефон, мессенджеры. */
    private function contacts(User $student): array
    {
        return array_values(array_filter([
            $student->email ? ['label' => $student->email, 'href' => 'mailto:' . $student->email, 'external' => false] : null,
            ...array_map(fn ($c) => ['label' => $c['label'] === 'Telegram' ? 'Telegram · @' . ltrim($student->telegram, '@') : ($c['label'] === 'WhatsApp' ? 'WhatsApp · ' . $student->whatsup : $c['label']),
                'href' => $c['href'], 'external' => $c['external']], $student->contactLinks()),
        ]));
    }

    private function perfSub(User $student, User $teacher): ?string
    {
        $total = app(StudentPerformanceService::class)->stats($student, $teacher->id)['lessons_total'];

        return $total ? 'за всё время · ' . plural_ru($total, 'занятие', 'занятия', 'занятий') : null;
    }

    /** Задания ученика у учителя: сначала на проверку, затем на доработке, просроченные, ждущие сдачи, проверенные. */
    private function homework(User $teacher, User $student): Collection
    {
        return Homework::query()
            ->where('teacher_id', $teacher->id)
            ->whereHas('students', fn ($q) => $q->where('users.id', $student->id))
            ->with(['submissions' => fn ($q) => $q->where('student_id', $student->id)])
            ->latest()
            ->get()
            ->map(function (Homework $h) {
                $sub = $h->submissions->first();
                $state = match (true) {
                    $sub?->status === HomeworkSubmission::STATUS_GRADED || ($sub && $sub->grade !== null) => 'graded',
                    $sub?->status === HomeworkSubmission::STATUS_REVISION_REQUESTED => 'revision',
                    (bool) $sub?->submitted_at => 'review',
                    $h->is_overdue => 'overdue',
                    default => 'todo',
                };

                $url = $sub?->submitted_at
                    ? (Route::has('cabinet.teacher.review') ? route('cabinet.teacher.review', $sub) : url('/tutor/homework-submissions/' . $sub->id))
                    : (Route::has('cabinet.teacher.tasks') ? route('cabinet.teacher.tasks') : url('/tutor/homework/' . $h->id));

                $waitDays = $sub?->submitted_at ? (int) $sub->submitted_at->copy()->startOfDay()->diffInDays(today()) : 0;

                return [
                    'id' => $h->id,
                    'title' => $h->title,
                    'state' => $state,
                    'order' => ['review' => 0, 'revision' => 1, 'overdue' => 2, 'todo' => 3, 'graded' => 4][$state],
                    'sort' => $state === 'todo' ? ($h->deadline?->timestamp ?? PHP_INT_MAX) : -($sub?->updated_at?->timestamp ?? $h->created_at?->timestamp ?? 0),
                    'sub' => match ($state) {
                        'review' => 'Сдано ' . HumanDate::day($sub->submitted_at),
                        'revision' => 'Вернули на доработку',
                        'graded' => 'Проверено ' . HumanDate::day($sub->updated_at ?? $sub->submitted_at),
                        'overdue' => 'Срок был ' . HumanDate::date($h->deadline) . ' · не сдано',
                        default => $h->deadline ? null : 'Без срока',
                    },
                    'urgent' => match ($state) {
                        'review' => $waitDays > 0 ? 'ждёт ' . plural_ru($waitDays, 'день', 'дня', 'дней') : 'сдано сегодня',
                        'revision' => $h->deadline && $h->deadline->isFuture() ? 'исправить до ' . HumanDate::date($h->deadline) : null,
                        'todo' => $h->deadline ? 'Сдать до ' . HumanDate::at($h->deadline) : null,
                        default => null,
                    },
                    'grade' => $state === 'graded' && $sub->grade !== null ? $h->formatGrade($sub->grade) : null,
                    'url' => $url,
                ];
            })
            ->sortBy([['order', 'asc'], ['sort', 'asc']])
            ->values();
    }

    /** Предстоящие занятия ученика у учителя (30 дней). */
    private function upcoming(User $teacher, User $student): Collection
    {
        return app(TeacherScheduleService::class)
            ->lessons($teacher->id, now()->startOfDay(), now()->addDays(30))
            ->filter(fn (array $l) => ! $l['past'] && $l['participants']->contains('id', $student->id))
            ->take(5)
            ->map(fn (array $l) => [
                'key' => $l['key'],
                'time' => $l['start']->format('H:i'),
                'title' => Str::ucfirst(HumanDate::day($l['start'])) . ' · ' . $l['title'],
                'soon' => $l['running'] ? 'Идёт сейчас' : ($l['start']->isToday() ? 'Начнётся ' . HumanDate::until($l['start']) : null),
                'meta' => implode(' · ', array_filter([
                    $l['duration'] ? plural_ru($l['duration'], 'минута', 'минуты', 'минут') : null,
                    $l['repeat'],
                ])),
                'today' => $l['start']->isToday() || $l['running'],
                'running' => $l['running'],
                'href' => $this->lessonUrl($l['roomId']),
                'startUrl' => route('rooms.start', $l['roomId']),
            ])
            ->values();
    }

    /** Прошедшие занятия: посещение, длительность, активность; неоплаченные — бейджем. */
    private function past(User $teacher, User $student, Collection $unpaid): array
    {
        $history = collect($this->service()->attendanceHistory($teacher, $student));
        $unpaidSessions = $unpaid->pluck('meeting_session_id')->filter()->all();

        return [
            'total' => $history->count(),
            'missed' => $history->where('attended', false)->count(),
            'rows' => $history->take(20)->map(fn (array $h) => [
                'key' => $h['session_id'],
                'time' => $h['started_at']?->format('H:i'),
                'title' => implode(' · ', array_filter([
                    ($h['started_at'] ?? $h['ended_at']) ? Str::ucfirst(HumanDate::day($h['started_at'] ?? $h['ended_at'])) : null,
                    $h['room_name'],
                ])),
                'sub' => $h['attended']
                    ? implode(' · ', array_filter([
                        $h['started_at'] && $h['ended_at'] ? plural_ru(max(1, (int) $h['started_at']->diffInMinutes($h['ended_at'])), 'минута', 'минуты', 'минут') : null,
                        'активность ' . $h['activity_score'] . ' из 10',
                    ]))
                    : 'Не было на занятии',
                'missed' => ! $h['attended'],
                'unpaid' => in_array($h['session_id'], $unpaidSessions),
                'href' => Route::has('cabinet.teacher.lesson') ? route('cabinet.teacher.lesson', $h['room_id']) : url('/tutor/meeting-sessions/' . $h['session_id']),
            ])->values(),
        ];
    }

    /** История оплат: оплаченные и «оплата не требуется», новые сверху. */
    private function payHistory(User $teacher, User $student): Collection
    {
        return PaymentRecord::query()
            ->where('teacher_id', $teacher->id)
            ->where('student_id', $student->id)
            ->where('status', '!=', PaymentRecord::STATUS_UNPAID)
            ->with('meetingSession.room')
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get()
            ->map(fn (PaymentRecord $r) => [
                'id' => $r->id,
                'title' => $r->human_label,
                'waived' => $r->status === PaymentRecord::STATUS_CANCELLED,
                'sub' => $r->status === PaymentRecord::STATUS_PAID && $r->paid_at
                    ? 'Оплачено ' . HumanDate::date($r->paid_at) . ($r->isPaidLate() ? ', позже срока' : '')
                    : null,
                'amount' => $r->status === PaymentRecord::STATUS_PAID && ($a = $r->amount()) ? TeacherStudentsService::rub($a) : null,
            ]);
    }

    private function modalData(User $teacher, Collection $unpaid, ?array $next): array
    {
        return match ($this->modal) {
            'mark', 'waive' => [
                'rows' => $unpaid->map(fn (PaymentRecord $r) => $this->dueRow($r)),
                'allOn' => $unpaid->isNotEmpty() && $unpaid->every(fn (PaymentRecord $r) => in_array($r->id, $this->selectedIds(), true)),
                'total' => ($sel = $unpaid->whereIn('id', $this->selectedIds()))->isNotEmpty()
                    ? ($this->service()->amountLabel($sel) ?? $this->service()->countLabel($sel))
                    : null,
            ],
            'extend' => $this->extendData($unpaid),
            'assign' => [
                'rooms' => $this->service()->teacherRooms($teacher)->load('schedules')->map(fn (Room $r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                    'group' => $r->type === 'group',
                    'sub' => implode(' · ', array_filter([
                        $r->type === 'group' ? 'Групповое · ' . plural_ru($r->participants_count, 'ученик', 'ученика', 'учеников') : 'Индивидуальное',
                        TeacherScheduleService::repeatLabel($r->schedules->firstWhere('is_active', true)),
                    ])),
                ]),
            ],
            'remove' => [
                'next' => $next,
                'debt' => $unpaid->isNotEmpty() ? ($this->service()->amountLabel($unpaid) ?? $this->service()->countLabel($unpaid)) : null,
            ],
            'settings' => [
                'debt' => $unpaid->isNotEmpty() ? $this->service()->countLabel($unpaid) . (($s = $this->service()->amountLabel($unpaid)) ? ' (' . $s . ')' : '') : null,
            ],
            default => [],
        };
    }

    private function extendData(Collection $unpaid): ?array
    {
        $first = $unpaid->first();

        if (! $first) {
            return null;
        }

        $new = $first->extendedDue($this->extendDays);

        return [
            'sub' => $this->student->name . ' · ' . ($this->service()->amountLabel($unpaid) ?? $this->service()->countLabel($unpaid)),
            'newDate' => 'до ' . HumanDate::day($new),
            'explain' => $first->isOverdue()
                ? 'Срок прошёл ' . HumanDate::date($first->due_date) . ' — считаем от сегодня. До новой даты вход в занятия не закроется.'
                : 'Считаем от текущего срока — ' . HumanDate::day($first->due_date) . '. Напоминание сдвинется.',
        ];
    }
}
