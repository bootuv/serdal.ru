<?php

namespace App\Demo\Teacher;

use App\Demo\Concerns\DemoHomework;
use App\Demo\Screen;
use App\Livewire\Cabinet\Teacher\Task as Real;
use App\Models\User;
use App\Support\HumanDate;
use App\Support\RichText;

/** Экран задания учителя — App\Livewire\Cabinet\Teacher\Task: ученики и состояние их работ, условие и файлы. */
class Task extends Screen
{
    use DemoHomework;

    public const PATH = 'tasks/{homework}';

    public const EXAMPLES = ['tasks/301', 'tasks/304', 'tasks/307', 'tasks/309', 'tasks/308', 'tasks/304?confirmDelete=1'];

    public string $view = 'livewire.cabinet.teacher.task';

    public string $title = 'Задание';

    public ?string $active = 'tasks';

    public function actions(): array
    {
        return [
            'delete' => ['go' => route('cabinet.teacher.tasks'), 'toast' => 'Задание удалено'],
        ];
    }

    public function modalParams(): array
    {
        return ['confirmDelete'];
    }

    public function data(): array
    {
        // Нет такого задания в демо — показываем первое (страницы 404 в демо нет: её раскладка читает базу)
        $h = $this->homework((int) $this->param('homework')) ?? $this->homework(304);
        $this->title = $h->title;

        $students = $h->students;
        $subs = $h->submissions->keyBy('student_id');

        $rows = $students
            ->map(fn (User $u) => $this->real(Real::class, 'studentRow', $h, $u, $subs->get($u->id)))
            ->sortBy('sort')
            ->values();

        $total = $students->count();
        $submitted = $subs->whereNotNull('submitted_at')->count();
        $graded = $subs->whereNotNull('grade')->count();
        $firstReview = $rows->firstWhere('review', true);

        return [
            'homework' => $h,
            'facts' => $this->real(Real::class, 'facts', $h, $students, $submitted < $total),
            'rows' => $rows,
            'more' => 0,
            'progress' => match (true) {
                ! $h->is_visible || $total === 0 => null,
                $graded === $total => 'проверено ' . $graded . ' из ' . $total,
                default => 'сдали ' . $submitted . ' из ' . $total,
            },
            'reviewUrl' => $firstReview['url'] ?? null,
            'description' => RichText::html($h->description),
            'files' => $this->demoFileViews($h->demoFiles),
            'issued' => ($h->is_visible ? 'Выдано ' : 'Создано ') . HumanDate::date($h->created_at)
                . ' · оценка от 1 до ' . plural_ru($h->effective_max_score, 'балла', 'баллов', 'баллов'),
            'editUrl' => route('cabinet.teacher.task-new', ['edit' => $h->id]),
            'backUrl' => route('cabinet.teacher.tasks'),
            'shown' => 30,
            'confirmDelete' => $this->state('confirmDelete', false),
        ];
    }
}
