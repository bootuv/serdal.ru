<?php

namespace App\Livewire\Cabinet\Student;

use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\PaymentRecord;
use App\Models\Room;
use App\Services\PaymentRecordService;
use App\Services\StudentPerformanceService;
use App\Support\HumanDate;
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
        abort_unless(auth()->user()?->role === \App\Models\User::ROLE_STUDENT, 403);
    }

    public function render()
    {
        $student = auth()->user();
        $blockedTeacherIds = PaymentRecordService::blockedTeacherIds($student->id);

        $rooms = $this->upcomingRooms($student->id);
        $next = $rooms->first();

        $teachers = $student->teachers()->orderBy('name')->get(['users.id', 'users.name']);
        $this->perfTeacherId ??= $teachers->first()?->id;

        return view('livewire.cabinet.student.home', [
            'firstName' => $student->first_name ?: $student->name,
            'today' => HumanDate::todayLong(),
            'next' => $next ? $this->lessonView($next, $blockedTeacherIds) : null,
            'week' => $rooms->skip(1)->take(4)->map(fn (Room $r) => $this->lessonView($r, $blockedTeacherIds))->values(),
            'homework' => $this->homework($student->id),
            'payment' => $this->payment($student->id),
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
                || ($r->next_start && $r->next_start->copy()->addMinutes($r->duration ?: 45)->isFuture()))
            ->sortBy(fn (Room $r) => [$r->is_running ? 0 : 1, $r->next_start?->timestamp ?? PHP_INT_MAX])
            ->values();
    }

    private function lessonView(Room $room, array $blockedTeacherIds): array
    {
        $start = $room->next_start;
        $end = $start?->copy()->addMinutes($room->duration ?: 45);

        return [
            'id' => $room->id,
            'title' => $room->name,
            'teacher' => $room->user?->name,
            'teacherId' => $room->user_id,
            'running' => (bool) $room->is_running,
            'blocked' => in_array($room->user_id, $blockedTeacherIds, true),
            'start' => $start,
            'when' => $start ? HumanDate::day($start) . ', ' . $start->format('H:i') . '–' . $end->format('H:i') : null,
            'until' => $room->is_running ? 'идёт сейчас' : ($start ? HumanDate::until($start) : null),
            'isToday' => $start?->isToday() ?? false,
            'joinUrl' => route('rooms.connect', $room),
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
                    'url' => route('filament.student.resources.homework.view', $h),
                ];
            });
    }

    /** Неоплаченные занятия. Суммы в PaymentRecord нет — показываем количество и срок. */
    private function payment(int $studentId): ?array
    {
        $unpaid = PaymentRecord::unpaid()->where('student_id', $studentId)->orderBy('due_date')->get();

        if ($unpaid->isEmpty()) {
            return null;
        }

        $first = $unpaid->first();

        return [
            'count' => $unpaid->count(),
            'overdue' => $first->isOverdue(),
            'due' => $first->due_date ? HumanDate::date($first->due_date) : null,
            'url' => url('/student/payment-debts'),
        ];
    }
}
