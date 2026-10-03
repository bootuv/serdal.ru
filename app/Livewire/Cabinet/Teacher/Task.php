<?php

namespace App\Livewire\Cabinet\Teacher;

use App\Livewire\Cabinet\Teacher\Concerns\TeacherScreen;
use App\Models\Homework;
use App\Models\HomeworkActivity;
use App\Models\HomeworkSubmission;
use App\Models\User;
use App\Services\HomeworkSubmissionService as Hw;
use App\Support\HumanDate;
use App\Support\RichText;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Экран задания учителя: условие и файлы, кто из учеников сдал и в каком состоянии работа, переход к проверке.
 * Отдельного макета нет — собран по «Учитель · Задания» и «Учитель · Проверка» (docs/design/BRAND.md).
 * Доступ — только к своему заданию (как HomeworkResource::getEloquentQuery в старом кабинете).
 */
#[Layout('components.layouts.cabinet', ['title' => 'Задание', 'active' => 'tasks'])]
class Task extends Component
{
    use TeacherScreen;

    private const PAGE = 30;

    public Homework $homework;

    /** Сколько учеников показано. */
    public int $shown = self::PAGE;

    public bool $confirmDelete = false;

    public function mount(Homework $homework): void
    {
        $this->authorizeTeacher();
        abort_unless((int) $homework->teacher_id === (int) auth()->id(), 404);

        $this->homework = $homework;

        // Сообщение после сохранения задания в редакторе
        if ($message = session('toast')) {
            $this->dispatch('toast', message: $message);
        }
    }

    public function showMore(): void
    {
        $this->shown += self::PAGE;
    }

    /** Удалить задание: работы учеников и файлы удаляют HomeworkObserver и HomeworkSubmissionObserver. */
    public function delete(): void
    {
        $homework = Homework::where('teacher_id', auth()->id())->findOrFail($this->homework->id);
        $homework->delete();

        session()->flash('toast', 'Задание удалено');
        $this->redirect(route('cabinet.teacher.tasks'));
    }

    public function render()
    {
        $h = $this->homework->loadMissing(['room:id,name,type']);
        $students = $h->students()->orderBy('name')->get(['users.id', 'users.name', 'users.avatar']);
        $submissions = $h->submissions()
            ->whereIn('student_id', $students->pluck('id'))
            ->withExists(['activities as resubmitted' => fn ($q) => $q->where('type', HomeworkActivity::TYPE_RESUBMITTED)])
            ->get()
            ->keyBy('student_id');

        $rows = $students
            ->map(fn (User $u) => $this->studentRow($h, $u, $submissions->get($u->id)))
            ->sortBy('sort')
            ->values();

        $total = $students->count();
        $submitted = $submissions->whereNotNull('submitted_at')->count();
        $graded = $submissions->whereNotNull('grade')->count();
        $firstReview = $rows->firstWhere('review', true);

        return view('livewire.cabinet.teacher.task', [
            'facts' => $this->facts($h, $students, $submitted < $total),
            'rows' => $rows->take($this->shown),
            'more' => max(0, $rows->count() - $this->shown),
            'progress' => match (true) {
                ! $h->is_visible || $total === 0 => null,
                $graded === $total => 'проверено ' . $graded . ' из ' . $total,
                default => 'сдали ' . $submitted . ' из ' . $total,
            },
            'reviewUrl' => $firstReview['url'] ?? null,
            'description' => RichText::html($h->description),
            'files' => Hw::fileViews($h->attachments ?? [], $h->file_names ?? []),
            'issued' => ($h->is_visible ? 'Выдано ' : 'Создано ') . HumanDate::date($h->created_at)
                . ' · оценка от 1 до ' . plural_ru($h->effective_max_score, 'балла', 'баллов', 'баллов'),
            'editUrl' => route('cabinet.teacher.task-new', ['edit' => $h->id]),
            'backUrl' => route('cabinet.teacher.tasks'),
        ])->title($h->title);
    }

    /** Строка фактов: занятие · кому · срок (близкий или прошедший, пока не все сдали, — жирным). */
    private function facts(Homework $h, $students, bool $pending): HtmlString
    {
        $total = $students->count();
        $em = fn (string $text) => '<span class="font-semibold text-ink">' . e($text) . '</span>';

        $who = match (true) {
            $total === 0 => 'ученики не выбраны',
            $total > 1 && $h->room?->type === 'group' => 'группа «' . $h->room->name . '»',
            $total > 1 => plural_ru($total, 'ученик', 'ученика', 'учеников'),
            default => $students->first()->name,
        };

        $parts = array_map('e', array_filter([$h->room?->type === 'group' && $total > 1 ? null : $h->room?->name, $who]));

        $d = $h->deadline;
        $parts[] = match (true) {
            ! $h->is_visible => e('черновик — ученики пока не видят'),
            $d === null => e('без срока'),
            $pending && $d->isPast() => $em('срок был ' . HumanDate::date($d) . ', ' . $d->format('H:i')),
            $pending && $d->lte(now()->addDays(3)) => $em('до ' . HumanDate::day($d) . ', ' . $d->format('H:i')),
            default => e('до ' . HumanDate::day($d) . ', ' . $d->format('H:i')),
        };

        return new HtmlString(implode(' · ', $parts));
    }

    /**
     * Строка ученика: когда сдано, состояние работы бейджем, ссылка на проверку (если работа сдана).
     * sort — сначала ждущие проверки (давние сверху), затем на доработке, не сданные, оценённые.
     */
    private function studentRow(Homework $h, User $u, ?HomeworkSubmission $s): array
    {
        $row = [
            'id' => $u->id,
            'name' => $u->name,
            'photo' => $u->photoThumb(),
            'sub' => null,
            'em' => null,
            'badge' => null,
            'url' => $s?->submitted_at ? route('cabinet.teacher.review', $s) : null,
            'review' => false,
        ];

        if (! $h->is_visible) {
            return array_merge($row, ['sub' => 'пока не видит задание', 'sort' => [5, $u->name]]);
        }

        $late = $s?->submitted_at && $h->deadline && $s->submitted_at->gt($h->deadline);

        return match (Hw::state($h, $s)) {
            Hw::STATE_REVIEW => array_merge($row, [
                'sub' => ($s->resubmitted ? 'исправлено ' : 'сдано ') . HumanDate::day($s->submitted_at)
                    . ($late && ! $s->resubmitted ? ', срок был до ' . HumanDate::date($h->deadline) : ''),
                'badge' => match (true) {
                    (bool) $s->resubmitted => ['neutral', 'Пересдано'],
                    $late => ['danger', 'Позже срока'],
                    default => ['neutral', 'На проверке'],
                },
                'review' => true,
                'sort' => [0, $s->submitted_at->timestamp],
            ]),
            Hw::STATE_REVISION => array_merge($row, [
                'sub' => 'вернули на доработку ' . HumanDate::date($s->updated_at),
                'badge' => ['danger', 'На доработке'],
                'sort' => [1, $u->name],
            ]),
            Hw::STATE_OVERDUE => array_merge($row, [
                'sub' => 'не сдано',
                'em' => 'срок был ' . HumanDate::date($h->deadline),
                'badge' => ['danger', 'Просрочено'],
                'sort' => [2, $u->name],
            ]),
            Hw::STATE_GRADED => array_merge($row, [
                'sub' => 'сдано ' . HumanDate::date($s->submitted_at) . ($late ? ', позже срока' : ''),
                'badge' => ['ok', 'Оценка ' . $h->formatGrade($s->grade)],
                'sort' => [4, $u->name],
            ]),
            default => array_merge($row, [
                'sub' => 'ещё не сдано',
                'badge' => ['neutral', 'Не сдано'],
                'sort' => [3, $u->name],
            ]),
        };
    }
}
