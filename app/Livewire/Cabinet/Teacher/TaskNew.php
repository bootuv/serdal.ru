<?php

namespace App\Livewire\Cabinet\Teacher;

use App\Helpers\FileUploadHelper;
use App\Livewire\Cabinet\Teacher\Concerns\TeacherScreen;
use App\Models\Homework;
use App\Models\Room;
use App\Models\User;
use App\Services\HomeworkSubmissionService as Hw;
use App\Support\HumanDate;
use App\Support\RichText;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Новое задание и изменение задания (?edit={id}). Макет: «Учитель · Новое задание» (docs/design/BRAND.md).
 * Поля — как в форме HomeworkResource старого кабинета. Можно открыть с ?room={id} или ?student={id}.
 */
#[Layout('components.layouts.cabinet', ['title' => 'Новое задание', 'active' => 'tasks'])]
class TaskNew extends Component
{
    use TeacherScreen;
    use WithFileUploads;

    /** Изменяемое задание (null — новое). */
    #[Locked]
    public ?int $editId = null;

    public string $title = '';

    /** Условие — HTML из редактора (x-ui.editor), при сохранении чистится RichText::clean(). */
    public string $description = '';

    public string $roomId = '';

    /** @var array<int> */
    public array $studentIds = [];

    public string $search = '';

    public string $date = '';

    public string $time = '';

    #[Locked]
    public ?string $deadlineOriginal = null;

    public $maxScore = 10;

    /** Только что выбранные файлы (wire:model), после проверки переносятся в $files. */
    public array $picked = [];

    public array $files = [];

    /** Уже загруженные файлы задания (при изменении). */
    #[Locked]
    public array $kept = [];

    public bool $confirmDelete = false;

    public function mount(): void
    {
        $teacher = $this->authorizeTeacher();

        if ($id = request()->integer('edit')) {
            $homework = Homework::where('teacher_id', $teacher->id)->findOrFail($id);

            $this->editId = $homework->id;
            $this->title = $homework->title;
            $this->description = (string) $homework->description;
            $this->roomId = (string) ($homework->room_id ?? '');
            $this->studentIds = $homework->students()->pluck('users.id')->map(fn ($v) => (int) $v)->all();
            $this->maxScore = $homework->max_score;
            $this->kept = array_values(array_filter($homework->attachments ?? [], 'is_string'));
            if ($homework->deadline) {
                $this->date = $homework->deadline->format('Y-m-d');
                $this->time = $homework->deadline->format('H:i');
                $this->deadlineOriginal = $homework->deadline->format('Y-m-d H:i');
            }

            return;
        }

        $this->maxScore = Hw::defaultMaxScore($teacher->id);

        // Выдать задание из занятия или карточки ученика
        if ($roomId = request()->integer('room')) {
            if ($this->rooms()->contains('id', $roomId)) {
                $this->roomId = (string) $roomId;
                $this->updatedRoomId();
            }
        } elseif ($studentId = request()->integer('student')) {
            if ($this->allowedStudents()->contains('id', $studentId)) {
                $this->studentIds = [$studentId];
            }
        }
    }

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'roomId' => ['nullable', Rule::in($this->rooms()->pluck('id')->map(fn ($v) => (string) $v)->all())],
            'studentIds' => ['required', 'array', 'min:1'],
            'studentIds.*' => [Rule::in($this->allowedStudents()->pluck('id')->all())],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'time' => ['nullable', 'date_format:H:i'],
            'maxScore' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'files.*' => $this->fileRules(),
        ];
    }

    protected function messages(): array
    {
        $type = 'Можно прикрепить PDF, Word, Excel, PowerPoint или фото (JPG, PNG, GIF).';
        $size = 'Файл больше 100 МБ — уменьшите его или разделите на части.';

        return [
            'title.required' => 'Назовите задание.',
            'studentIds.required' => 'Выберите хотя бы одного ученика.',
            'studentIds.min' => 'Выберите хотя бы одного ученика.',
            'studentIds.*.in' => 'Этого ученика нет среди ваших — обновите страницу.',
            'date.date_format' => 'Укажите дату.',
            'time.date_format' => 'Укажите время, например 20:00.',
            'maxScore.integer' => 'Балл — целое число от 1 до 1000.',
            'maxScore.min' => 'Балл — целое число от 1 до 1000.',
            'maxScore.max' => 'Балл — целое число от 1 до 1000.',
            'picked.*.mimetypes' => $type,
            'picked.*.max' => $size,
            'files.*.mimetypes' => $type,
            'files.*.max' => $size,
        ];
    }

    private function fileRules(): array
    {
        return ['file', 'mimetypes:' . implode(',', Hw::TASK_MIMES), 'max:' . Hw::TASK_MAX_FILE_KB];
    }

    /** Выбрали занятие — подставляем его учеников (как в старом кабинете). */
    public function updatedRoomId(): void
    {
        $room = $this->roomId !== '' ? $this->rooms()->firstWhere('id', (int) $this->roomId) : null;

        if ($room) {
            $this->studentIds = $room->participants()->pluck('users.id')->map(fn ($v) => (int) $v)->all();
        }
    }

    public function toggleStudent(int $id): void
    {
        $this->studentIds = in_array($id, $this->studentIds, true)
            ? array_values(array_diff($this->studentIds, [$id]))
            : array_merge($this->studentIds, [$id]);
    }

    public function updatedPicked(): void
    {
        $this->validate(['picked.*' => $this->fileRules()]);

        foreach ($this->picked as $file) {
            if ($file instanceof TemporaryUploadedFile) {
                $this->files[] = $file;
            }
        }

        $this->picked = [];
    }

    public function removeFile(int $index): void
    {
        unset($this->files[$index]);
        $this->files = array_values($this->files);
    }

    public function removeKept(int $index): void
    {
        unset($this->kept[$index]);
        $this->kept = array_values($this->kept);
    }

    /** Выдать задание (ученики увидят его и получат уведомление). */
    public function publish(Hw $service): void
    {
        $this->save($service, true);
    }

    /** Сохранить черновик — ученики его не видят. */
    public function saveDraft(Hw $service): void
    {
        $this->save($service, false);
    }

    private function save(Hw $service, bool $visible): void
    {
        $this->studentIds = array_values(array_unique(array_map('intval', $this->studentIds)));
        $this->validate();

        $deadline = $this->deadline();
        if ($deadline === false) {
            return;
        }

        $homework = $this->editId ? $this->homework() : null;

        $description = RichText::clean($this->description);

        $data = [
            'title' => trim($this->title),
            'description' => $description,
            'room_id' => $this->roomId !== '' ? (int) $this->roomId : null,
            'deadline' => $deadline,
            'max_score' => $this->maxScore === '' || $this->maxScore === null ? null : (int) $this->maxScore,
            'is_visible' => $visible,
            'attachments' => array_merge($this->kept, FileUploadHelper::processFiles($this->files, Hw::TASK_DIRECTORY)),
        ];

        if ($homework) {
            $service->updateTask($homework, $data, $this->studentIds);
            $message = $visible ? 'Задание сохранено' : 'Черновик сохранён';
        } else {
            $service->createTask(auth()->user(), $data, $this->studentIds);
            $message = $visible ? 'Задание выдано' : 'Черновик сохранён';
        }

        session()->flash('toast', $message);
        $this->redirect(route('cabinet.teacher.tasks'));
    }

    /** Срок из даты и времени. Нет даты — без срока; прошедшее время нельзя (как в старом кабинете), если срок меняли. */
    private function deadline(): Carbon|null|false
    {
        if ($this->date === '') {
            return null;
        }

        $deadline = Carbon::createFromFormat('Y-m-d H:i', $this->date . ' ' . ($this->time !== '' ? $this->time : '20:00'));

        if ($deadline->isPast() && $deadline->format('Y-m-d H:i') !== $this->deadlineOriginal) {
            $this->addError('date', 'Срок уже прошёл — выберите дату позже.');

            return false;
        }

        return $deadline;
    }

    public function delete(): void
    {
        $homework = $this->homework();
        $homework->delete(); // файлы и работы учеников удаляет HomeworkObserver

        session()->flash('toast', 'Задание удалено');
        $this->redirect(route('cabinet.teacher.tasks'));
    }

    public function render()
    {
        $homework = $this->editId ? $this->homework() : null;
        $allowed = $this->allowedStudents();
        $selected = $allowed->whereIn('id', $this->studentIds);
        $needle = mb_strtolower(trim($this->search));
        $shown = $needle === ''
            ? $allowed
            : $allowed->filter(fn (User $u) => in_array($u->id, $this->studentIds, true) || str_contains(mb_strtolower($u->name), $needle));

        $count = $selected->count();

        return view('livewire.cabinet.teacher.task-new', [
            'homework' => $homework,
            'rooms' => $this->rooms()->mapWithKeys(fn (Room $r) => [(string) $r->id => $this->roomLabel($r)])->all(),
            'students' => $shown->values(),
            'searchable' => $allowed->count() > 12,
            'whoLabel' => $count === 0
                ? ($allowed->isEmpty() ? 'Пока нет учеников — пригласите их в разделе «Ученики».' : 'Выберите хотя бы одного ученика')
                : 'Получат: ' . $selected->pluck('name')->implode(', ') . ($count > 1 ? ' · всего ' . plural_ru($count, 'ученик', 'ученика', 'учеников') : ''),
            'keptFiles' => Hw::fileViews($this->kept),
            'newFiles' => collect($this->files)->map(fn (TemporaryUploadedFile $f) => [
                'name' => $f->getClientOriginalName(),
                'meta' => Hw::kind($f->getClientOriginalName()) . ' · ' . Hw::size($f->getSize()),
            ])->all(),
            'backUrl' => route('cabinet.teacher.tasks'),
        ])->title($homework ? 'Изменить задание' : 'Новое задание');
    }

    private function homework(): Homework
    {
        return Homework::where('teacher_id', auth()->id())->findOrFail($this->editId);
    }

    /** Занятия учителя: ближайшие сверху. */
    private function rooms(): Collection
    {
        return once(fn () => Room::query()
            ->where('user_id', auth()->id())
            ->orderByRaw('next_start is null, next_start asc')
            ->orderBy('name')
            ->get(['id', 'name', 'next_start']));
    }

    private function roomLabel(Room $room): string
    {
        return $room->next_start && $room->next_start->isFuture()
            ? $room->name . ' · ' . HumanDate::at($room->next_start)
            : $room->name;
    }

    /**
     * Кому можно выдать: ученики учителя (связка teacher_student), участники выбранного занятия
     * и уже назначенные ученики изменяемого задания (как список в форме старого кабинета).
     */
    private function allowedStudents(): Collection
    {
        $teacherId = auth()->id();
        $roomId = $this->roomId !== '' && $this->rooms()->contains('id', (int) $this->roomId) ? (int) $this->roomId : null;

        return User::query()
            ->where(function ($q) use ($teacherId, $roomId) {
                $q->whereHas('teachers', fn ($t) => $t->where('teacher_student.teacher_id', $teacherId));
                if ($roomId) {
                    $q->orWhereHas('assignedRooms', fn ($r) => $r->where('rooms.id', $roomId));
                }
                if ($this->editId) {
                    $q->orWhereHas('assignedHomeworks', fn ($h) => $h->where('homeworks.id', $this->editId)->where('teacher_id', $teacherId));
                }
            })
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
