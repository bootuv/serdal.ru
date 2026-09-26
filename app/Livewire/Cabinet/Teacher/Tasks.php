<?php

namespace App\Livewire\Cabinet\Teacher;

use App\Livewire\Cabinet\Teacher\Concerns\TeacherScreen;
use App\Models\Homework;
use App\Models\HomeworkActivity;
use App\Models\HomeworkSubmission;
use App\Services\HomeworkSubmissionService as Hw;
use App\Support\HumanDate;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Задания учителя: работы на проверку, выданные, все. Макет: «Учитель · Задания» (docs/design/BRAND.md). */
#[Layout('components.layouts.cabinet', ['title' => 'Задания', 'active' => 'tasks'])]
class Tasks extends Component
{
    use TeacherScreen;

    private const PAGE = 30;

    /** Вкладка: review — нужно проверить, issued — выданные, all — все. */
    #[Url(except: 'review')]
    public string $tab = 'review';

    /** Фильтр «Все» по ученику. */
    #[Url(as: 'student', except: '')]
    public string $studentId = '';

    /** Сколько строк показано в длинных списках. */
    public int $shown = self::PAGE;

    public function mount(): void
    {
        $this->authorizeTeacher();

        if (! in_array($this->tab, ['review', 'issued', 'all'], true)) {
            $this->tab = 'review';
        }

        // Сообщение после сохранения задания на соседнем экране
        if ($message = session('toast')) {
            $this->dispatch('toast', message: $message);
        }
    }

    public function updatedTab(): void
    {
        $this->shown = self::PAGE;
    }

    public function updatedStudentId(): void
    {
        $this->shown = self::PAGE;
    }

    public function showMore(): void
    {
        $this->shown += self::PAGE;
    }

    public function render()
    {
        $teacherId = auth()->id();

        $queue = Hw::toReview($teacherId)
            ->with(['student:id,name', 'homework:id,title,deadline,room_id', 'homework.room:id,name,type'])
            ->withExists(['activities as resubmitted' => fn ($q) => $q->where('type', HomeworkActivity::TYPE_RESUBMITTED)])
            ->get();

        $homeworks = Homework::query()
            ->where('teacher_id', $teacherId)
            ->with([
                'students:id,name',
                'submissions:id,homework_id,student_id,status,grade,submitted_at,updated_at',
                'room:id,name,type',
            ])
            ->latest()
            ->get();

        $rows = $homeworks->map(fn (Homework $h) => $this->taskRow($h));

        $list = match ($this->tab) {
            'issued' => $rows->where('done', false)->sortBy('sort')->values(),
            'all' => $rows->when($this->studentId !== '', fn ($c) => $c->filter(fn ($r) => in_array((int) $this->studentId, $r['studentIds'], true)))->values(),
            default => collect(),
        };

        return view('livewire.cabinet.teacher.tasks', [
            'reviewCount' => $queue->count(),
            'review' => $this->tab === 'review'
                ? $queue->take($this->shown)->values()->map(fn (HomeworkSubmission $s, int $i) => $this->reviewRow($s, $i === 0))
                : collect(),
            'reviewMore' => max(0, $queue->count() - $this->shown),
            'waiting' => $this->tab === 'review' ? $this->waiting($homeworks) : collect(),
            'list' => $list->take($this->shown),
            'listMore' => max(0, $list->count() - $this->shown),
            'students' => $this->tab === 'all' ? $this->studentOptions($homeworks) : [],
            'hasAny' => $homeworks->isNotEmpty(),
            'newUrl' => route('cabinet.teacher.task-new'),
        ]);
    }

    /** Строка работы на проверку. first — самая давняя, с кнопкой «Проверить». */
    private function reviewRow(HomeworkSubmission $s, bool $first): array
    {
        $h = $s->homework;
        $late = $h->deadline && $s->submitted_at->gt($h->deadline);
        $days = (int) $s->submitted_at->copy()->startOfDay()->diffInDays(today(), true);

        $sent = match (true) {
            (bool) $s->resubmitted => 'исправлено ' . HumanDate::day($s->submitted_at),
            $late => 'сдано ' . HumanDate::day($s->submitted_at) . ', срок был до ' . HumanDate::date($h->deadline),
            default => 'сдано ' . HumanDate::day($s->submitted_at),
        };

        return [
            'id' => $s->id,
            'title' => $h->title,
            'student' => $s->student?->name ?? 'Ученик',
            'studentId' => $s->student_id,
            'sub' => implode(' · ', array_filter([
                $s->student?->name,
                $h->room?->type === 'group' ? 'группа «' . $h->room->name . '»' : null,
                $sent,
            ])),
            'wait' => $first && $days >= 1 ? 'ждёт ' . plural_ru($days, 'день', 'дня', 'дней') : null,
            'badge' => match (true) {
                (bool) $s->resubmitted => ['neutral', 'Пересдано'],
                $late => ['danger', 'Позже срока'],
                default => null,
            },
            'url' => route('cabinet.teacher.review', $s),
        ];
    }

    /**
     * Строка задания: кому, срок, прогресс или состояние работы.
     * sort — порядок во «Выданных»: ждём сдачи (по сроку), на проверке, на доработке, просрочено, черновики.
     */
    private function taskRow(Homework $h): array
    {
        $students = $h->students;
        $total = $students->count();
        $subs = $h->submissions->whereIn('student_id', $students->pluck('id'));
        $submitted = $subs->whereNotNull('submitted_at')->count();
        $graded = $subs->whereNotNull('grade')->count();
        $group = $total > 1;
        $done = $h->is_visible && $total > 0 && $graded === $total;
        $single = $group ? null : $subs->first();

        $who = match (true) {
            $total === 0 => 'Ученики не выбраны',
            $group && $h->room?->type === 'group' => 'Группа «' . $h->room->name . '»',
            $group => plural_ru($total, 'ученик', 'ученика', 'учеников'),
            default => $students->first()->name,
        };

        $row = [
            'id' => $h->id,
            'title' => $h->title,
            'who' => $who,
            'group' => $group,
            'avatarId' => $group ? 0 : (int) $students->first()?->id,
            'studentIds' => $students->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'due' => $h->deadline ? 'до ' . HumanDate::date($h->deadline) : null,
            'dueEm' => null,
            'prog' => null,
            'badge' => null,
            'done' => $done,
            'sort' => [9, 0],
            // Экрана задания в новом кабинете нет — кто сдал, видно в старом
            'url' => route('filament.app.resources.homework.view', $h),
        ];

        if (! $h->is_visible) {
            return array_merge($row, [
                'due' => $group ? 'ученики пока не видят' : 'пока не видно ученику',
                'badge' => ['neutral', 'Черновик'],
                'sort' => [4, 0],
                'url' => route('cabinet.teacher.task-new', ['edit' => $h->id]),
            ]);
        }

        if ($group || $total === 0) {
            $row['prog'] = $done ? ['проверено', "{$graded} из {$total}"] : ['сдали', "{$submitted} из {$total}"];
            if (! $done && $h->deadline) {
                $row = array_merge($row, $this->deadline($h, $submitted < $total));
            }
            $row['sort'] = $done ? [5, 0] : [$h->is_overdue ? 3 : 0, $h->deadline?->timestamp ?? PHP_INT_MAX];

            return $row;
        }

        $state = Hw::state($h, $single);
        $row['url'] = $single?->submitted_at ? route('cabinet.teacher.review', $single) : $row['url'];

        return match ($state) {
            Hw::STATE_GRADED => array_merge($row, [
                'due' => 'сдано ' . HumanDate::date($single->submitted_at),
                'badge' => ['ok', 'Оценка ' . $h->formatGrade($single->grade)],
                'sort' => [5, 0],
            ]),
            Hw::STATE_REVIEW => array_merge($row, ['badge' => ['neutral', 'На проверке'], 'sort' => [1, 0]]),
            Hw::STATE_REVISION => array_merge($row, ['badge' => ['danger', 'На доработке'], 'sort' => [2, 0]]),
            Hw::STATE_OVERDUE => array_merge($row, $this->deadline($h, true), ['badge' => ['danger', 'Просрочено'], 'sort' => [3, 0]]),
            default => array_merge($row, $h->deadline ? $this->deadline($h, true) : [], [
                'badge' => ['neutral', 'Ещё не сдано'],
                'sort' => [0, $h->deadline?->timestamp ?? PHP_INT_MAX],
            ]),
        };
    }

    /** Срок: прошедший или близкий (3 дня) — жирным, дальний — обычным текстом. */
    private function deadline(Homework $h, bool $pending): array
    {
        $d = $h->deadline;

        if ($pending && $d->isPast()) {
            return ['due' => null, 'dueEm' => 'срок был ' . HumanDate::date($d)];
        }

        $text = today()->diffInDays($d->copy()->startOfDay()) <= 1
            ? 'до ' . HumanDate::day($d) . ', ' . $d->format('H:i')
            : 'до ' . HumanDate::day($d);

        return $pending && $d->lte(now()->addDays(3))
            ? ['due' => null, 'dueEm' => $text]
            : ['due' => $text, 'dueEm' => null];
    }

    /** «Ждём от учеников»: работы на доработке, затем опубликованные задания, которые ещё не сдали (по сроку). */
    private function waiting(Collection $homeworks): Collection
    {
        $revisions = $homeworks->where('is_visible', true)
            ->flatMap(fn (Homework $h) => $h->submissions
                ->where('status', HomeworkSubmission::STATUS_REVISION_REQUESTED)
                ->map(fn (HomeworkSubmission $s) => [
                    'title' => $h->title,
                    'sub' => trim(($h->students->firstWhere('id', $s->student_id)?->name ?? 'Ученик') . ' · вернули ' . HumanDate::date($s->updated_at)),
                    'em' => null,
                    'badge' => ['danger', 'На доработке'],
                    'url' => route('cabinet.teacher.review', $s),
                    'at' => $s->updated_at->timestamp,
                ]))
            ->sortBy('at');

        $pending = $homeworks
            ->filter(fn (Homework $h) => $h->is_visible && ! $h->is_overdue && $h->students->isNotEmpty()
                && $h->submissions->whereIn('student_id', $h->students->pluck('id'))->whereNotNull('submitted_at')->count() < $h->students->count())
            ->sortBy(fn (Homework $h) => $h->deadline?->timestamp ?? PHP_INT_MAX)
            ->map(function (Homework $h) {
                $row = $this->taskRow($h);

                return [
                    'title' => $h->title,
                    'sub' => $row['who'] . ($row['due'] ? ' · ' . $row['due'] : ''),
                    'em' => $row['dueEm'],
                    'progress' => $row['group'] ? 'сдали ' . $row['prog'][1] : null,
                    'badge' => null,
                    'url' => $row['url'],
                ];
            });

        return $revisions->concat($pending)->take(5)->values();
    }

    /** Ученики для фильтра вкладки «Все». */
    private function studentOptions(Collection $homeworks): array
    {
        return $homeworks->flatMap->students->unique('id')->sortBy('name')
            ->mapWithKeys(fn ($s) => [(string) $s->id => $s->name])->all();
    }
}
