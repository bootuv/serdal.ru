<?php

namespace App\Demo\Teacher;

use App\Demo\Concerns\DemoHomework;
use App\Demo\Screen;
use App\Demo\World;
use App\Support\HumanDate;
use Carbon\Carbon;

/**
 * «Новое задание» и «Изменить задание» (?edit=301) — App\Livewire\Cabinet\Teacher\TaskNew.
 *
 * Состояние формы в адресе: roomId — выбранное занятие (его ученики подставляются в «Кому», как updatedRoomId),
 * studentToggle — ученики, которых добавили или убрали поверх этого набора (toggleStudent).
 * Можно открыть с ?room={id} или ?student={id}, как настоящий экран.
 */
class TaskNew extends Screen
{
    use DemoHomework;

    public const PATH = 'tasks/new';

    public const EXAMPLES = ['tasks/new', 'tasks/new?roomId=206', 'tasks/new?student=101&studentToggle=102', 'tasks/new?edit=301', 'tasks/new?edit=308&confirmDelete=1'];

    public string $view = 'livewire.cabinet.teacher.task-new';

    public string $title = 'Новое задание';

    public ?string $active = 'tasks';

    public function actions(): array
    {
        $edit = $this->editing();
        $tasks = route('cabinet.teacher.tasks');

        return [
            'toggleStudent' => ['toggle' => 'studentToggle'],
            // Изменённое задание — обратно на его экран (как TaskNew::save)
            'publish' => ['go' => $edit ? route('cabinet.teacher.task', $edit) : $tasks, 'toast' => $edit ? 'Задание сохранено' : 'Задание выдано'],
            'saveDraft' => ['go' => $edit ? route('cabinet.teacher.task', $edit) : $tasks, 'toast' => 'Черновик сохранён'],
            'delete' => ['go' => $tasks, 'toast' => 'Задание удалено'],
        ];
    }

    public function modalParams(): array
    {
        return ['confirmDelete'];
    }

    /** Значение редактора (x-ui.editor читает $wire.entangle('description')). */
    public function props(): array
    {
        return ['description' => (string) $this->editing()?->description];
    }

    public function data(): array
    {
        $homework = $this->editing();
        $this->title = $homework ? 'Изменить задание' : 'Новое задание';

        $roomId = $this->state('roomId', $homework ? (string) $homework->room_id : (string) $this->state('room', ''));
        if ($roomId !== '' && ! isset(World::ROOMS[(int) $roomId])) {
            $roomId = '';
        }

        // Набор «Кому»: ученики занятия, изменяемого задания или ?student=, затем добавленные и убранные вручную
        $base = match (true) {
            $homework && $roomId === (string) $homework->room_id => $homework->students->pluck('id')->all(),
            $roomId !== '' => World::ROOMS[(int) $roomId][2],
            $this->state('student', 0) > 0 && isset(World::STUDENTS[$this->state('student', 0)]) => [$this->state('student', 0)],
            default => [],
        };
        $studentIds = $base;
        foreach ($this->stateInts('studentToggle') as $id) {
            $studentIds = in_array($id, $studentIds, true) ? array_values(array_diff($studentIds, [$id])) : array_merge($studentIds, [$id]);
        }

        $allowed = World::students()->sortBy('name')->values();
        $selected = $allowed->whereIn('id', $studentIds);
        $count = $selected->count();
        $deadline = $homework?->deadline;

        return [
            'homework' => $homework,
            'rooms' => $this->rooms(),
            'people' => $allowed->map(fn ($u) => ['id' => (int) $u->id, 'name' => (string) $u->name, 'email' => (string) $u->email, 'photo' => null])->all(),
            'chosen' => $selected->values(),
            'whoLabel' => $count === 0
                ? 'Выберите хотя бы одного ученика'
                : 'Получат задание: ' . plural_ru($count, 'ученик', 'ученика', 'учеников'),
            'keptFiles' => $homework ? $this->demoFileViews($homework->demoFiles) : [],
            'newFiles' => [],
            'backUrl' => $homework ? route('cabinet.teacher.task', $homework) : route('cabinet.teacher.tasks'),
            // Публичные свойства компонента
            'editId' => $homework?->id,
            'title' => (string) $homework?->title,
            'description' => (string) $homework?->description,
            'roomId' => $roomId,
            'studentIds' => array_values($studentIds),
            'date' => $deadline?->format('Y-m-d') ?? '',
            'time' => $deadline?->format('H:i') ?? '',
            'deadlineOriginal' => $deadline?->format('Y-m-d H:i'),
            'maxScore' => $homework?->max_score ?? 10,
            'picked' => [],
            'files' => [],
            'kept' => $homework ? array_keys($homework->demoFiles) : [],
            'keptNames' => $homework ? array_map(fn ($f) => $f[0], $homework->demoFiles) : [],
            'confirmDelete' => $this->state('confirmDelete', false),
        ];
    }

    private function editing()
    {
        $id = (int) $this->request->query('edit');

        return $id ? $this->homework($id) : null;
    }

    /** Занятия для списка с поиском: название и ближайшее время (как TaskNew::render), ближайшие сверху. */
    private function rooms(): array
    {
        $lessons = World::lessons(Carbon::now(), Carbon::now()->addDays(7));

        return collect(World::ROOMS)->map(function (array $r, int $id) use ($lessons) {
            $next = $lessons->first(fn ($l) => $l['roomId'] === $id && $l['start']->isFuture());

            return [
                'value' => (string) $id,
                'title' => $r[0],
                'sub' => $next ? HumanDate::at($next['start']) : null,
                'sort' => $next ? $next['start']->timestamp : PHP_INT_MAX,
            ];
        })->sortBy('sort')->map(fn ($o) => array_diff_key($o, ['sort' => 1]))->values()->all();
    }
}
