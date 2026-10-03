<?php

namespace App\Livewire\Cabinet\Student;

use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\User;
use App\Services\HomeworkSubmissionService as Hw;
use App\Support\HumanDate;
use App\Support\RichText;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/** Задание ученика: условие, файлы учителя, ответ и проверка. Макет: «Ученик · Задание» (docs/design/BRAND.md). */
#[Layout('components.layouts.cabinet', ['title' => 'Задание', 'active' => 'tasks'])]
class Task extends Component
{
    use WithFileUploads;

    public Homework $homework;

    /** Ответ — HTML из редактора (x-ui.editor), при сохранении чистится RichText::clean(). */
    public string $answer = '';

    /** Только что выбранные файлы (wire:model), после проверки переносятся в $files. */
    public array $picked = [];

    /** Новые файлы ответа (временные). */
    public array $files = [];

    /** Уже загруженные файлы работы, которые ученик оставляет при пересдаче. */
    #[Locked]
    public array $kept = [];

    /** Работа только что отправлена — показываем «Отправлено». */
    public bool $justSent = false;

    public function mount(Homework $homework): void
    {
        abort_unless(auth()->user()?->role === User::ROLE_STUDENT, 403);
        abort_unless(Hw::visibleTo(auth()->id())->whereKey($homework->id)->exists(), 404);

        $this->homework = $homework;

        $submission = $this->submission();
        if ($submission && Hw::canSubmit($submission)) {
            // При пересдаче — прежний ответ с оформлением
            $this->answer = (string) RichText::clean($submission->content);
            $this->kept = array_values(array_filter($submission->attachments ?? [], 'is_string'));
        }
    }

    protected function rules(): array
    {
        return [
            'answer' => ['nullable', 'string', 'max:60000'],
            'files.*' => $this->fileRules(),
        ];
    }

    protected function messages(): array
    {
        return [
            'picked.*.mimetypes' => 'Можно прикрепить PDF, Word, JPG, PNG или GIF.',
            'picked.*.max' => 'Файл больше 50 МБ — уменьшите его или разделите на части.',
            'files.*.mimetypes' => 'Можно прикрепить PDF, Word, JPG, PNG или GIF.',
            'files.*.max' => 'Файл больше 50 МБ — уменьшите его или разделите на части.',
            'answer.max' => 'Ответ слишком длинный — сократите его или прикрепите файлом.',
        ];
    }

    private function fileRules(): array
    {
        return ['file', 'mimetypes:' . implode(',', Hw::ACCEPTED_MIMES), 'max:' . Hw::MAX_FILE_KB];
    }

    /** Выбрали файлы — проверяем и добавляем к уже выбранным. */
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

    /** Отправить работу на проверку (первый раз или после доработки). */
    public function submit(Hw $service): void
    {
        $submission = $this->submission();

        if (! Hw::canSubmit($submission)) {
            $this->dispatch('toast', message: 'Работа уже у учителя — изменить её можно, только если учитель вернёт её на доработку.');

            return;
        }

        $this->validate();

        $content = RichText::clean($this->answer);

        if ($content === null && empty($this->files) && empty($this->kept)) {
            $this->addError('answer', 'Напишите ответ или прикрепите файл.');

            return;
        }

        $names = Hw::storeUploads($this->files, Hw::DIRECTORY);

        $service->submit($this->homework, auth()->user(), $content, array_merge($this->kept, array_keys($names)), $names);

        $this->reset('files', 'picked', 'kept', 'answer');
        $this->justSent = true;
        $this->dispatch('toast', message: 'Работа отправлена учителю');
    }

    public function render()
    {
        $homework = $this->homework->loadMissing(['teacher:id,name,avatar', 'room:id,name']);
        $submission = $this->submission();
        $state = Hw::state($homework, $submission);
        $canSubmit = Hw::canSubmit($submission);
        // Пометки учителя на фото — только после проверки (оценка или «на доработку»)
        $marks = (bool) $submission?->marksVisibleToStudent();
        $answerPaths = $canSubmit ? $this->kept : ($submission?->attachments ?? []);

        return view('livewire.cabinet.student.task', [
            'state' => $state,
            'canSubmit' => $canSubmit,
            'facts' => $this->facts($homework, $state),
            'description' => RichText::html($homework->description),
            'teacherFiles' => Hw::fileViews($homework->attachments ?? [], $homework->file_names ?? []),
            'submission' => $submission,
            'grade' => $submission?->grade !== null ? $homework->formatGrade($submission->grade) : null,
            'content' => RichText::html($submission?->content),
            'feedback' => RichText::html($submission?->feedback),
            'feedbackFiles' => $submission ? Hw::feedbackFiles($submission, $marks ? $answerPaths : []) : [],
            'myFiles' => $submission && ! $canSubmit ? Hw::answerFiles($submission, $marks) : [],
            'keptFiles' => $submission ? Hw::answerFiles($submission, $marks, $this->kept) : [],
            'newFiles' => collect($this->files)->map(fn (TemporaryUploadedFile $f) => [
                'name' => $f->getClientOriginalName(),
                'meta' => Hw::kind($f->getClientOriginalName()) . ' · ' . Hw::size($f->getSize()),
            ])->all(),
            'sentAt' => $submission?->submitted_at ? HumanDate::at($submission->submitted_at) : null,
            'checkedAt' => $submission ? HumanDate::date($submission->updated_at) : null,
            'previous' => $state === Hw::STATE_TODO || $state === Hw::STATE_OVERDUE || $state === Hw::STATE_REVIEW
                ? $this->previousComment($homework) : null,
            'messengerUrl' => \App\Services\MessengerService::url(auth()->user(), $homework->room_id),
        ])->title($homework->title);
    }

    private function submission(): ?HomeworkSubmission
    {
        return $this->homework->submissions()->where('student_id', auth()->id())->first();
    }

    /** Строка фактов под заголовком: занятие · учитель · срок (срочное — жирным). */
    private function facts(Homework $h, string $state): HtmlString
    {
        $parts = array_map('e', array_filter([$h->room?->name, $h->teacher?->name]));
        $deadline = $h->deadline ? HumanDate::at($h->deadline) : null;
        $em = fn (string $text) => '<span class="font-semibold text-ink">' . e($text) . '</span>';

        $parts[] = match ($state) {
            Hw::STATE_TODO => $deadline ? $em('сдать до ' . $deadline) : 'без срока',
            Hw::STATE_OVERDUE => $em('срок прошёл ' . $deadline),
            Hw::STATE_REVISION => $deadline && ! $h->is_overdue ? $em('пересдать до ' . $deadline) : e('вернули на доработку'),
            // Отправлено/проверено — в фокус-блоке, здесь не повторяем
            default => null,
        };

        return new HtmlString(implode(' · ', array_filter($parts)));
    }

    /** Комментарий учителя к прошлой проверенной работе у того же учителя. */
    private function previousComment(Homework $h): ?array
    {
        $prev = HomeworkSubmission::query()
            ->where('student_id', auth()->id())
            ->where('homework_id', '!=', $h->id)
            ->whereNotNull('grade')
            ->whereNotNull('feedback')
            ->whereHas('homework', fn ($q) => Hw::scopeVisible($q, auth()->id())->where('teacher_id', $h->teacher_id))
            ->with('homework')
            ->latest('updated_at')
            ->first();

        $text = $prev ? trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($prev->feedback)))) : '';

        return $text === '' ? null : [
            'title' => $prev->homework->title,
            'grade' => $prev->homework->formatGrade($prev->grade),
            'text' => Str::limit($text, 240),
            'url' => route('cabinet.student.task', $prev->homework),
        ];
    }
}
