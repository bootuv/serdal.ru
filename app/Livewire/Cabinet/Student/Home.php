<?php

namespace App\Livewire\Cabinet\Student;

use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\PaymentRecord;
use App\Models\Room;
use App\Models\RoomSchedule;
use App\Models\User;
use App\Services\PaymentClaimService;
use App\Services\PaymentRecordService;
use App\Services\StudentPerformanceService;
use App\Services\StudentScheduleService;
use App\Services\StudentTeachersService;
use App\Services\TeacherStudentsService;
use App\Support\HumanDate;
use App\Support\Money;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/** Главная ученика. Макет: «Ученик · Главная» (docs/design/BRAND.md). */
#[Layout('components.layouts.cabinet', ['title' => 'Главная', 'active' => 'home'])]
class Home extends Component
{
    /** Учитель, по которому показана успеваемость. */
    public ?int $perfTeacherId = null;

    /** Обновляем, когда учитель начинает или завершает занятие. */
    #[On('echo:rooms,.room.status.updated')]
    public function refreshRooms(): void {}

    public function mount(): void
    {
        abort_unless(auth()->user()?->role === User::ROLE_STUDENT, 403);
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
        ]);
    }

    /** Занятия ученика: идущие сейчас, затем ближайшие по времени начала (на 7 дней вперёд). */
    private function upcomingRooms(int $studentId): Collection
    {
        return Room::query()
            ->whereHas('participants', fn ($q) => $q->where('users.id', $studentId))
            ->where(fn ($q) => $q->where('is_running', true)
                ->orWhereBetween('next_start', [now()->subHours(3), now()->addDays(7)]))
            ->with('user:id,name')
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
            'running' => $running,
            'blocked' => in_array($room->user_id, $blockedTeacherIds, true),
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

        return [
            'count' => $unpaid->count(),
            'sum' => $sum ? Money::format($sum) : null,
            // Под суммой: сколько занятий и до какого числа; просроченный срок — жирным
            'facts' => implode(' · ', array_filter([
                $sum ? plural_ru($unpaid->count(), 'занятие', 'занятия', 'занятий') : null,
                $first->due_date && ! $first->isOverdue() ? 'оплатить до ' . HumanDate::date($first->due_date) : null,
            ])),
            'late' => $first->due_date && $first->isOverdue() ? 'срок прошёл ' . HumanDate::date($first->due_date) : null,
            'waiting' => $claimable->isEmpty(),
            'url' => $this->paymentsUrl($teacherIds->count() === 1 ? ['report' => $teacherIds->first()] : []),
        ];
    }
}
