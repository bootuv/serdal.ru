<?php

namespace App\Livewire\Cabinet\Student;

use App\Models\Homework;
use App\Models\User;
use App\Services\HomeworkSubmissionService as Hw;
use App\Services\StudentPerformanceService;
use App\Support\HumanDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Задания ученика. Макет: «Ученик · Задания» (docs/design/BRAND.md). */
#[Layout('components.layouts.cabinet', ['title' => 'Задания', 'active' => 'tasks'])]
class Tasks extends Component
{
    private const PAGE = 30;

    /** Вкладка: actual — нужно сдать, done — сданные, all — все. */
    #[Url(except: 'actual')]
    public string $tab = 'actual';

    /** Фильтр по учителю (у заданий нет предмета — предмет у ученика задаёт учитель). */
    #[Url(as: 'teacher', except: '')]
    public string $teacherId = '';

    /** Сколько сданных работ показано. */
    public int $shown = self::PAGE;

    /** Учитель, по которому показана успеваемость. */
    public ?int $perfTeacherId = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->role === User::ROLE_STUDENT, 403);

        if (! in_array($this->tab, ['actual', 'done', 'all'], true)) {
            $this->tab = 'actual';
        }
    }

    public function updatedTab(): void
    {
        $this->shown = self::PAGE;
    }

    public function updatedTeacherId(): void
    {
        $this->shown = self::PAGE;

        // Успеваемость — по тому же учителю
        if ($this->teacherId !== '') {
            $this->perfTeacherId = (int) $this->teacherId;
        }
    }

    public function showMore(): void
    {
        $this->shown += self::PAGE;
    }

    public function render()
    {
        $student = auth()->user();
        $id = $student->id;

        $teacherOptions = $this->teacherOptions($id);
        if ($this->teacherId !== '' && ! isset($teacherOptions[$this->teacherId])) {
            $this->teacherId = '';
        }

        $actualCount = Hw::filterByStatus($this->visible($id), $id, 'actual')->count();
        $doneCount = Hw::filterByStatus($this->visible($id), $id, 'done')->count();
        $gradedCount = Hw::filterByStatus($this->visible($id), $id, 'graded')->count();

        $actual = $this->tab === 'done' ? collect() : $this->actual($id);
        $done = $this->tab === 'actual' ? collect() : $this->done($id);

        $teachers = $student->teachers()->orderBy('name')->get(['users.id', 'users.name']);
        if (! $teachers->contains('id', $this->perfTeacherId)) {
            $this->perfTeacherId = $teachers->first()?->id;
        }

        return view('livewire.cabinet.student.tasks', [
            'subtitle' => $this->subtitle($actualCount, $doneCount, $gradedCount),
            'actualCount' => $actualCount,
            'doneCount' => $doneCount,
            'focus' => $this->tab === 'actual' ? $actual->first() : null,
            'actual' => $this->tab === 'actual' ? $actual->slice(1)->values() : $actual,
            'done' => $done,
            'hasMore' => $doneCount > $done->count(),
            'teachers' => $teachers,
            'teacherOptions' => count($teacherOptions) > 1 ? $teacherOptions : [],
            'perfTeacher' => $teachers->firstWhere('id', $this->perfTeacherId)?->name,
            'metrics' => $this->perfTeacherId
                ? app(StudentPerformanceService::class)->metrics($student, $this->perfTeacherId)
                : null,
        ]);
    }

    /** Нужно сдать: сначала ближайший срок, без срока — в конце. */
    private function actual(int $studentId): Collection
    {
        return $this->withRelations(Hw::filterByStatus($this->visible($studentId), $studentId, 'actual'), $studentId)
            ->orderByRaw('deadline is null, deadline asc')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Homework $h) => self::item($h));
    }

    /** Сданные: как в старом кабинете — сначала ждущие проверки, затем новые. */
    private function done(int $studentId): Collection
    {
        $query = Hw::orderForStudent(Hw::filterByStatus($this->visible($studentId), $studentId, 'done'), $studentId);

        return $this->withRelations($query, $studentId)
            ->limit($this->shown)
            ->get()
            ->map(fn (Homework $h) => self::item($h));
    }

    /** Задания ученика с учётом фильтра по учителю. */
    private function visible(int $studentId): Builder
    {
        return Hw::visibleTo($studentId)
            ->when($this->teacherId !== '', fn ($q) => $q->where('homeworks.teacher_id', (int) $this->teacherId));
    }

    /** Учителя, от которых есть задания: id → имя. */
    private function teacherOptions(int $studentId): array
    {
        return User::query()
            ->whereIn('id', Hw::visibleTo($studentId)->select('teacher_id'))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => [(string) $id => $name])
            ->all();
    }

    private function withRelations(Builder $query, int $studentId): Builder
    {
        return $query->with([
            'submissions' => fn ($q) => $q->where('student_id', $studentId),
            'teacher:id,name',
            'room:id,name',
        ]);
    }

    /**
     * Строка списка: название, подпись (занятие · учитель · срок), бейдж только для исключений.
     * Нужны отношения submissions (только этого ученика), teacher и room. $withRoom = false — на странице самого занятия.
     */
    public static function item(Homework $h, bool $withRoom = true): array
    {
        $sub = $h->submissions->first();
        $state = Hw::state($h, $sub);
        $deadline = $h->deadline ? HumanDate::at($h->deadline) : null;
        $room = $withRoom ? $h->room?->name : null;

        $meta = array_filter([$room, $h->teacher?->name]);
        $em = null;
        $badge = null;

        switch ($state) {
            case Hw::STATE_TODO:
                $em = $deadline ? 'сдать до ' . $deadline : null;
                break;
            case Hw::STATE_OVERDUE:
                $meta[] = 'срок был ' . $deadline;
                $badge = ['danger', 'Срок прошёл'];
                break;
            case Hw::STATE_REVISION:
                $em = $deadline && ! $h->is_overdue ? 'пересдать до ' . $deadline : null;
                $badge = ['danger', 'На доработке'];
                break;
            case Hw::STATE_REVIEW:
                $meta = array_filter([$room, 'отправлено ' . HumanDate::date($sub->submitted_at)]);
                $badge = ['neutral', 'На проверке'];
                break;
            case Hw::STATE_GRADED:
                $meta = array_filter([$room, 'проверено ' . HumanDate::date($sub->updated_at)]);
                $badge = ['ok', 'Оценка ' . $h->formatGrade($sub->grade)];
                break;
        }

        $feedback = $state === Hw::STATE_REVISION && $sub->feedback
            ? trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($sub->feedback))))
            : null;

        return [
            'title' => $h->title,
            'room' => $h->room?->name,
            'teacher' => $h->teacher?->name,
            'teacherId' => $h->teacher_id,
            'state' => $state,
            'deadline' => $deadline,
            'overdue' => $h->is_overdue,
            'hasFiles' => ! empty($h->attachments),
            'meta' => implode(' · ', $meta),
            'em' => $em,
            'badge' => $badge,
            'feedback' => $feedback ?: null,
            'url' => route('cabinet.student.task', $h),
        ];
    }

    /** Строка фактов под заголовком; пустой раздел объясняет себя сам. */
    private function subtitle(int $actual, int $done, int $graded): ?string
    {
        if ($actual + $done === 0) {
            return null;
        }

        $waiting = $done - $graded;

        return match ($this->tab) {
            'actual' => $actual ? 'Нужно сдать ' . plural_ru($actual, 'задание', 'задания', 'заданий') : 'Всё сдано',
            'done' => $done
                ? implode(' · ', array_filter([
                    $graded ? $graded . ' проверено' : null,
                    $waiting ? $waiting . ' ' . plural_ru($waiting, 'ждёт', 'ждут', 'ждут', false) . ' проверки' : null,
                ]))
                : null,
            default => plural_ru($actual + $done, 'задание', 'задания', 'заданий'),
        };
    }
}
