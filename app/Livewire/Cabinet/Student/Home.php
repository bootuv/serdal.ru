<?php

namespace App\Livewire\Cabinet\Student;

use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\MeetingSession;
use App\Models\PaymentRecord;
use App\Models\Review;
use App\Models\Room;
use App\Models\RoomSchedule;
use App\Models\User;
use App\Rules\NoContacts;
use App\Services\PaymentClaimService;
use App\Services\PaymentRecordService;
use App\Services\ReviewPromptService;
use App\Services\StudentPerformanceService;
use App\Services\StudentScheduleService;
use App\Services\StudentTeachersService;
use App\Services\TeacherStudentsService;
use App\Support\HumanDate;
use App\Support\Money;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/** Главная ученика. Макеты: «Ученик · Главная», «Отзыв: оставить / изменить / состояния» (docs/design/BRAND.md). */
#[Layout('components.layouts.cabinet', ['title' => 'Главная', 'active' => 'home'])]
class Home extends Component
{
    /** Учитель, по которому показана успеваемость. */
    public ?int $perfTeacherId = null;

    // Окно отзыва
    public ?int $reviewTeacherId = null;
    public int $rating = 5;
    public string $reviewText = '';

    // Карточка «Как вам занятия?» (ReviewPromptService): звезда открывает окно отзыва с этой оценкой
    #[Locked]
    public ?int $promptTeacherId = null;
    public int $promptRating = 0;

    /** Обновляем, когда учитель начинает или завершает занятие. */
    #[On('echo:rooms,.room.status.updated')]
    public function refreshRooms(): void {}

    public function mount(): void
    {
        abort_unless(auth()->user()?->role === User::ROLE_STUDENT, 403);

        // Ссылка из уведомления «Оставить отзыв» (?review=учитель) сразу открывает окно; устаревшая — просто главная
        if ($teacherId = (int) request()->query('review')) {
            try {
                $this->openReview($teacherId);
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException) {
            }
        }
    }

    public function updatedPromptRating(int $value): void
    {
        $this->promptRating = 0;

        if ($this->promptTeacherId && $value >= 1 && $value <= 5) {
            $this->openReview($this->promptTeacherId);
            $this->rating = $value;
        }
    }

    /** «Позже» в карточке «Как вам занятия?». */
    public function dismissReviewPrompt(): void
    {
        if (! $this->promptTeacherId) {
            return;
        }

        [$teacher, $count] = $this->reviewable($this->promptTeacherId);
        app(ReviewPromptService::class)->dismiss((int) auth()->id(), $teacher->id, $count);
        $this->promptTeacherId = null;
    }

    public function openReview(int $teacherId): void
    {
        [$teacher] = $this->reviewable($teacherId);

        $review = $this->teachersService()->review(auth()->id(), $teacher->id);
        $this->rating = $review?->rating ?? 5;
        $this->reviewText = (string) ($review?->text ?? '');
        $this->reviewTeacherId = $teacher->id;
        $this->resetValidation(['rating', 'reviewText']);
    }

    public function closeReview(): void
    {
        $this->reviewTeacherId = null;
        $this->resetValidation(['rating', 'reviewText']);
    }

    public function saveReview(): void
    {
        [$teacher] = $this->reviewable((int) $this->reviewTeacherId);

        $this->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'reviewText' => ['required', 'string', 'max:' . Review::MAX_TEXT, new NoContacts],
        ], [
            'reviewText.required' => 'Напишите хотя бы пару предложений',
            'reviewText.max' => 'Отзыв длиннее ' . Review::MAX_TEXT . ' символов — сократите его.',
        ]);

        $isNew = ! $this->teachersService()->review(auth()->id(), $teacher->id);
        $this->teachersService()->saveReview(auth()->user(), $teacher, $this->rating, trim($this->reviewText));

        $this->reviewTeacherId = null;
        $this->dispatch('toast', message: $isNew ? 'Спасибо! Отзыв опубликован' : 'Отзыв обновлён');
    }

    public function render()
    {
        $student = auth()->user();
        $blockedTeacherIds = PaymentRecordService::blockedTeacherIds($student->id);

        $rooms = $this->upcomingRooms($student->id);
        $next = $rooms->first();

        $teachers = $student->teachers()->orderBy('name')->get(['users.id', 'users.name']);
        $this->perfTeacherId ??= $teachers->first()?->id;
        $debt = $this->debt($student->id, $rooms);
        $nextView = $next ? $this->lessonView($next, $blockedTeacherIds) : null;
        $teacherRows = $this->teacherRows($student->id);
        $prompt = $teacherRows->firstWhere('prompt', true);
        $this->promptTeacherId = $prompt['id'] ?? null;

        return view('livewire.cabinet.student.home', [
            'firstName' => $student->first_name ?: $student->name,
            'today' => HumanDate::todayLong(),
            'next' => $nextView,
            // Пока вход не открыт, а занятие сегодня, — проверяем раз в минуту, чтобы кнопка появилась сама
            'poll' => $nextView && ! $nextView['canJoin'] && ! $nextView['blocked'] && $nextView['isToday'],
            // Занятий нет (макет SyEmptyStudent): учитель и первые шаги
            'teacher' => $next ? null : $this->teacher($student, $teachers->first()?->id),
            'steps' => $next ? null : $this->steps($student),
            'week' => $rooms->skip(1)->take(4)->map(fn (Room $r) => $this->lessonView($r, $blockedTeacherIds))->values(),
            'homework' => $this->homework($student->id),
            'debt' => $debt,
            'payment' => $this->payment($student->id, $debt),
            'paymentsUrl' => $this->paymentsUrl(),
            'teachers' => $teachers,
            'metrics' => $this->perfTeacherId
                ? app(StudentPerformanceService::class)->metrics($student, $this->perfTeacherId)
                : null,
            // Учителя и отзывы. На пустой главной без занятий и отзывов карточка повторила бы фокус-блок — её нет
            'teacherRows' => $next || $teacherRows->contains(fn (array $t) => $t['lessons'] > 0 || $t['review'] || ! $t['current'])
                ? $teacherRows
                : collect(),
            'reviewPrompt' => $prompt,
            'reviewing' => $this->reviewTeacherId ? $teacherRows->firstWhere('id', $this->reviewTeacherId) : null,
            'stars' => ['', '1 — очень плохо', '2 — плохо', '3 — нормально', '4 — хорошо', '5 — отлично'],
        ]);
    }

    /** Текущие, затем бывшие учителя — как в виджетах старого кабинета. */
    private function teacherRows(int $studentId): Collection
    {
        $svc = $this->teachersService();
        $pivots = app(ReviewPromptService::class)->pivotsForStudent($studentId);

        $current = $svc->currentTeachers($studentId)->with('subjects:id,name')->orderBy('name')->get()
            ->map(fn (User $t) => $this->teacherRow($studentId, $t, $svc->lessonsWithCurrentTeacher($studentId, $t), true, $pivots->get($t->id)));
        $former = $svc->formerTeachers($studentId)->with('subjects:id,name')->orderBy('name')->get()
            ->map(fn (User $t) => $this->teacherRow($studentId, $t, $svc->lessonsWithFormerTeacher($studentId, $t), false));

        return $current->concat($former)->values();
    }

    private function teacherRow(int $studentId, User $teacher, Collection $lessons, bool $current, ?object $pivot = null): array
    {
        $svc = $this->teachersService();
        $review = $svc->review($studentId, $teacher->id);
        $count = $lessons->count();
        $dates = $lessons->map(fn (MeetingSession $s) => $s->started_at ?? $s->ended_at ?? $s->created_at)->filter()->sort();

        $facts = match (true) {
            ! $current => $dates->isNotEmpty() ? 'занимались до ' . HumanDate::month($dates->last(), genitive: true) : null,
            $count > 0 => plural_ru($count, 'занятие', 'занятия', 'занятий') . ($dates->isNotEmpty() ? ' с ' . HumanDate::month($dates->first(), genitive: true) : ''),
            default => 'занятий пока не было',
        };

        return [
            'id' => $teacher->id,
            'name' => $teacher->name,
            'teacher' => $teacher,
            'current' => $current,
            'sub' => implode(' · ', array_filter([$teacher->subjects->pluck('name')->join(', '), $facts])),
            'review' => $review,
            'rejected' => $svc->hasRejectedReview($studentId, $teacher->id),
            'canReview' => $canReview = $svc->canReview($studentId, $teacher->id, $count),
            // Карточка «Как вам занятия?» — только по текущему учителю
            'prompt' => $current && $pivot && ReviewPromptService::shouldPrompt($pivot, $count, (bool) $review, $canReview),
            'requested' => $pivot && ReviewPromptService::requested($pivot),
            'lessons' => $count,
            'chat' => $current ? $svc->chatUrl($studentId, $teacher->id) : null,
            'publicUrl' => $teacher->is_active && $teacher->username ? route('tutors.show', ['username' => $teacher->username]) : null,
        ];
    }

    /** Учитель, которому ученик может оставить отзыв; иначе — 403. Возвращает [учитель, занятий]. */
    private function reviewable(int $teacherId): array
    {
        $studentId = (int) auth()->id();
        $svc = $this->teachersService();

        if ($teacher = $svc->currentTeachers($studentId)->find($teacherId)) {
            $count = $svc->lessonsWithCurrentTeacher($studentId, $teacher)->count();
        } elseif ($teacher = $svc->formerTeachers($studentId)->find($teacherId)) {
            $count = $svc->lessonsWithFormerTeacher($studentId, $teacher)->count();
        } else {
            abort(403);
        }

        abort_unless($svc->canReview($studentId, $teacher->id, $count), 403);

        return [$teacher, $count];
    }

    private function teachersService(): StudentTeachersService
    {
        return app(StudentTeachersService::class);
    }

    /** Занятия ученика: идущие сейчас, затем ближайшие по времени начала (на 7 дней вперёд). */
    private function upcomingRooms(int $studentId): Collection
    {
        return Room::query()
            ->whereHas('participants', fn ($q) => $q->where('users.id', $studentId))
            ->where(fn ($q) => $q->where('is_running', true)
                ->orWhereBetween('next_start', [now()->subHours(3), now()->addDays(7)]))
            ->with('user:id,name,avatar')
            ->get()
            ->filter(fn (Room $r) => $r->is_running
                || ($r->next_start && $r->next_start->copy()->addMinutes($r->duration ?: RoomSchedule::DEFAULT_DURATION)->isFuture()))
            ->sortBy(fn (Room $r) => [$r->is_running ? 0 : 1, $r->next_start?->timestamp ?? PHP_INT_MAX])
            ->values();
    }

    private function lessonView(Room $room, array $blockedTeacherIds): array
    {
        $start = $room->next_start;
        $end = $start?->copy()->addMinutes($room->duration ?: RoomSchedule::DEFAULT_DURATION);
        $running = (bool) $room->is_running;

        return [
            'id' => $room->id,
            'title' => $room->name,
            'teacher' => $room->user?->name,
            'teacherId' => $room->user_id,
            'teacherPhoto' => $room->user?->photoThumb(),
            'running' => $running,
            'blocked' => $blocked = in_array($room->user_id, $blockedTeacherIds, true),
            // Вход закрыт до оплаты — ссылка сразу открывает «Сообщить об оплате» учителю этого занятия
            'reportUrl' => $blocked ? $this->paymentsUrl(['report' => $room->user_id]) : null,
            // «Войти в класс» — только когда занятие идёт или вот-вот начнётся
            'canJoin' => StudentScheduleService::canJoin($running, $start, $end),
            'joinHint' => StudentScheduleService::joinOpensLabel($start),
            'start' => $start,
            'when' => $start ? HumanDate::day($start) . ', ' . $start->format('H:i') . '–' . $end->format('H:i') : null,
            'until' => $room->is_running ? 'идёт сейчас' : ($start ? HumanDate::until($start) : null),
            'isToday' => $start?->isToday() ?? false,
            'joinUrl' => route('rooms.connect', $room),
            'url' => route('cabinet.student.lesson', $room),
        ];
    }

    /** «Ваш учитель» на пустой главной: имя, предметы, чат и Telegram, если он указан. */
    private function teacher(User $student, ?int $teacherId): ?array
    {
        $teacher = $teacherId ? User::with('subjects:id,name')->find($teacherId) : null;

        if (! $teacher) {
            return null;
        }

        $subjects = $teacher->subjects->pluck('name')->map(fn ($n) => mb_strtolower($n))->implode(', ');
        $telegram = ltrim((string) $teacher->telegram, '@');

        return [
            'id' => $teacher->id,
            'name' => $teacher->name,
            'photo' => $teacher->photoThumb(),
            'sub' => 'Ваш учитель' . ($subjects !== '' ? ' · ' . $subjects : ''),
            'chatUrl' => app(StudentTeachersService::class)->chatUrl($student->id, $teacher->id),
            'telegram' => $telegram !== '' ? '@' . $telegram : null,
            'telegramUrl' => $telegram !== '' ? 'https://t.me/' . $telegram : null,
        ];
    }

    /**
     * «Первые шаги»: уведомления (подписка хотя бы на одном устройстве; на этом устройстве браузер уточнит сам)
     * и профиль (указан класс). Когда всё сделано — блока нет.
     */
    private function steps(User $student): ?array
    {
        $push = $student->pushSubscriptions()->exists();
        $profile = ! empty($student->grade);

        return $push && $profile ? null : [
            'push' => $push,
            'profile' => $profile,
            'profileUrl' => route('cabinet.student.profile'),
            'vapid' => (string) config('webpush.vapid.public_key', ''),
        ];
    }

    /** Задания, которые ещё не оценены: сначала с ближайшим сроком. */
    private function homework(int $studentId): Collection
    {
        return Homework::query()
            ->where('is_visible', true)
            ->whereHas('students', fn ($q) => $q->where('users.id', $studentId))
            ->whereDoesntHave('submissions', fn ($q) => $q->where('student_id', $studentId)->whereNotNull('grade'))
            ->with(['submissions' => fn ($q) => $q->where('student_id', $studentId), 'teacher:id,name'])
            ->orderByRaw('deadline is null, deadline asc')
            ->limit(4)
            ->get()
            ->map(function (Homework $h) {
                $sub = $h->submissions->first();
                $state = match (true) {
                    $sub?->status === HomeworkSubmission::STATUS_REVISION_REQUESTED => 'revision',
                    (bool) $sub?->submitted_at => 'review',
                    $h->is_overdue => 'overdue',
                    default => 'todo',
                };

                return [
                    'title' => $h->title,
                    'teacher' => $h->teacher?->name,
                    'state' => $state,
                    'deadline' => $h->deadline ? HumanDate::at($h->deadline) : null,
                    'url' => route('cabinet.student.task', $h),
                ];
            });
    }

    private function paymentsUrl(array $query = []): string
    {
        return \Illuminate\Support\Facades\Route::has('cabinet.student.payments')
            ? route('cabinet.student.payments', $query)
            : url('/student/payment-debts');
    }

    /**
     * Блок долга (макеты PmStudentDebt / PmStudentBlocked): просроченная оплата у одного учителя —
     * сначала тот, к кому вход уже закрыт, затем тот, к кому закроется раньше.
     */
    private function debt(int $studentId, Collection $rooms): ?array
    {
        $item = PaymentRecordService::debtStatuses($studentId)
            ->sortBy(fn (array $i) => [$i['status']['blocked'] ? 0 : 1, $i['status']['lessons_left']])
            ->first();

        if (! $item) {
            return null;
        }

        $teacher = $item['teacher'];
        $status = $item['status'];
        $records = PaymentRecord::unpaid()
            ->where('student_id', $studentId)
            ->where('teacher_id', $teacher->id)
            ->with('meetingSession:id,room_id,ended_at,pricing_snapshot')
            ->get();
        $pending = app(PaymentClaimService::class)->pendingRecordIds($studentId, $teacher->id);
        $sum = PaymentClaimService::knownSum($records);
        $todayLesson = $rooms->contains(fn (Room $r) => (int) $r->user_id === (int) $teacher->id && ($r->is_running || $r->next_start?->isToday()));

        return [
            'teacherId' => $teacher->id,
            'teacher' => $teacher->name,
            'blocked' => $status['blocked'],
            'count' => app(TeacherStudentsService::class)->countLabel($records),
            'sum' => $sum ? Money::format($sum) : null,
            'warning' => match (true) {
                $status['blocked'] => 'Вход откроется, когда учитель подтвердит оплату.',
                $status['lessons_left'] <= 1 && $todayLesson => 'Сегодняшнее занятие пройдёт как обычно, а после него вход закроется до оплаты.',
                $status['lessons_left'] <= 1 => 'Следующее занятие пройдёт как обычно, а после него вход закроется до оплаты.',
                default => 'Ещё ' . plural_ru($status['lessons_left'], 'занятие', 'занятия', 'занятий') . ' — и вход на занятия закроется до оплаты.',
            },
            'canReport' => $records->reject(fn (PaymentRecord $r) => in_array($r->id, $pending, true))->isNotEmpty(),
            'waiting' => $pending !== [],
            'reportUrl' => $this->paymentsUrl(['report' => $teacher->id]),
            'chatUrl' => app(StudentTeachersService::class)->chatUrl($studentId, $teacher->id),
        ];
    }

    /** К оплате (кроме учителя из блока долга): количество, сумма, срок; «Сообщить об оплате» ведёт в «Оплату». */
    private function payment(int $studentId, ?array $debt): ?array
    {
        $unpaid = PaymentRecord::unpaid()
            ->where('student_id', $studentId)
            ->when($debt, fn ($q) => $q->where('teacher_id', '!=', $debt['teacherId']))
            ->with('meetingSession:id,room_id,ended_at,pricing_snapshot')
            ->orderBy('due_date')
            ->get();

        if ($unpaid->isEmpty()) {
            return null;
        }

        $first = $unpaid->first();
        $pending = app(PaymentClaimService::class)->pendingRecordIds($studentId);
        $claimable = $unpaid->reject(fn (PaymentRecord $r) => in_array($r->id, $pending, true));
        $teacherIds = $claimable->pluck('teacher_id')->unique();
        $sum = PaymentClaimService::knownSum($unpaid);

        $countLabel = app(TeacherStudentsService::class)->countLabel($unpaid);

        return [
            'count' => $unpaid->count(),
            'countLabel' => $countLabel,
            'sum' => $sum ? Money::format($sum) : null,
            // Под суммой: сколько занятий и до какого числа; просроченный срок — жирным
            'facts' => implode(' · ', array_filter([
                $sum ? $countLabel : null,
                $first->due_date && ! $first->isOverdue() ? 'оплатить до ' . HumanDate::date($first->due_date) : null,
            ])),
            'late' => $first->due_date && $first->isOverdue() ? 'срок прошёл ' . HumanDate::date($first->due_date) : null,
            'waiting' => $claimable->isEmpty(),
            'url' => $this->paymentsUrl($teacherIds->count() === 1 ? ['report' => $teacherIds->first()] : []),
        ];
    }
}
