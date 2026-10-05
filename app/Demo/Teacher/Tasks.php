<?php

namespace App\Demo\Teacher;

use App\Demo\Concerns\DemoHomework;
use App\Demo\Screen;
use App\Livewire\Cabinet\Teacher\Tasks as Real;
use App\Models\Homework;
use App\Models\HomeworkSubmission as Sub;
use App\Support\HumanDate;

/** «Задания» учителя — App\Livewire\Cabinet\Teacher\Tasks: «Нужно проверить», «Выданные», «Все» с фильтрами. */
class Tasks extends Screen
{
    use DemoHomework;

    public const PATH = 'tasks';

    public const EXAMPLES = ['tasks', 'tasks?tab=issued', 'tasks?tab=all', 'tasks?tab=all&studentId=102', 'tasks?tab=all&roomId=206', 'tasks?tab=all&search=уравн', 'tasks?tab=all&search=ничего'];

    public string $view = 'livewire.cabinet.teacher.tasks';

    public string $title = 'Задания';

    public ?string $active = 'tasks';

    public function data(): array
    {
        $tab = $this->state('tab', 'review');
        if (! in_array($tab, ['review', 'issued', 'all'], true)) {
            $tab = 'review';
        }
        $studentId = $this->state('studentId', '');
        $roomId = $this->state('roomId', '');
        $search = $this->state('search', '');

        $review = collect();
        $waiting = collect();
        $list = collect();

        if ($tab === 'review') {
            $review = $this->toReview()->map(fn (Sub $s, int $i) => $this->real(Real::class, 'reviewRow', $s, $i === 0));
            $waiting = $this->waiting();
        } elseif ($tab === 'issued') {
            // Hw::notDone: черновики и задания, где не все назначенные ученики оценены
            $list = $this->allHomeworks()
                ->filter(fn (Homework $h) => ! $h->is_visible || $h->graded_count < $h->students_count)
                ->sortBy(fn (Homework $h) => $this->real(Real::class, 'sortKey', $h))
                ->map(fn (Homework $h) => $this->real(Real::class, 'taskRow', $h))
                ->values();
        } else {
            $needle = mb_strtolower(trim($search));
            $list = $this->allHomeworks()
                ->filter(fn (Homework $h) => $studentId === '' || $h->students->contains('id', (int) $studentId))
                ->filter(fn (Homework $h) => $roomId === '' || $h->room_id === (int) $roomId)
                ->filter(fn (Homework $h) => $needle === '' || str_contains(mb_strtolower($h->title), $needle))
                ->sortByDesc(fn (Homework $h) => [$h->created_at->timestamp, $h->id])
                ->map(fn (Homework $h) => $this->real(Real::class, 'taskRow', $h))
                ->values();
        }

        return [
            'reviewCount' => $this->toReview()->count(),
            'review' => $review,
            'reviewMore' => 0,
            'waiting' => $waiting,
            'list' => $list,
            'listMore' => 0,
            'students' => $tab === 'all' ? $this->studentOptions() : [],
            'rooms' => $tab === 'all' ? $this->roomOptions() : [],
            'filtered' => $studentId !== '' || $roomId !== '' || trim($search) !== '',
            'hasAny' => true,
            'newUrl' => route('cabinet.teacher.task-new'),
            // Публичные свойства компонента
            'tab' => $tab,
            'studentId' => $studentId,
            'roomId' => $roomId,
            'search' => $search,
            'shown' => 30,
        ];
    }

    /** «Ждём от учеников» — как Tasks::waiting: работы на доработке, затем задания, которые ещё не сдали (по сроку). */
    private function waiting()
    {
        $revisions = $this->allSubmissions()
            ->filter(fn (Sub $s) => $s->status === Sub::STATUS_REVISION_REQUESTED && $s->homework->is_visible)
            ->sortBy(fn (Sub $s) => $s->updated_at->timestamp)
            ->take(5)
            ->map(fn (Sub $s) => [
                'title' => $s->homework->title,
                'sub' => trim(($s->student?->name ?? 'Ученик') . ' · вернули ' . HumanDate::date($s->updated_at)),
                'em' => null,
                'badge' => ['danger', 'На доработке'],
                'url' => route('cabinet.teacher.review', $s),
            ]);

        // Hw::awaitingSubmissions: опубликованные, где кто-то из учеников ещё не сдал; срок не прошёл
        $pending = $this->allHomeworks()
            ->filter(fn (Homework $h) => $h->is_visible && $h->submitted_count < $h->students_count)
            ->filter(fn (Homework $h) => $h->deadline === null || $h->deadline->isFuture())
            ->sortBy(fn (Homework $h) => [$h->deadline === null ? 1 : 0, $h->deadline?->timestamp ?? 0, -$h->id])
            ->take(5 - $revisions->count())
            ->map(fn (Homework $h) => $this->real(Real::class, 'taskRow', $h))
            ->map(fn (array $row) => [
                'title' => $row['title'],
                'sub' => $row['who'] . ($row['due'] ? ' · ' . $row['due'] : ''),
                'em' => $row['dueEm'],
                'progress' => $row['group'] ? 'сдали ' . $row['prog'][1] : null,
                'badge' => null,
                'url' => $row['url'],
            ]);

        return $revisions->concat($pending)->values();
    }

    private function studentOptions(): array
    {
        return $this->allHomeworks()->flatMap(fn (Homework $h) => $h->students)
            ->unique('id')->sortBy('name')
            ->mapWithKeys(fn ($u) => [(string) $u->id => $u->name])->all();
    }

    private function roomOptions(): array
    {
        return $this->allHomeworks()->map(fn (Homework $h) => $h->room)
            ->unique('id')->sortBy('name')
            ->mapWithKeys(fn ($r) => [(string) $r->id => $r->name])->all();
    }
}
