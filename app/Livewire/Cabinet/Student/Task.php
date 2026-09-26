<?php

namespace App\Livewire\Cabinet\Student;

use App\Helpers\FileUploadHelper;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\User;
use App\Services\HomeworkSubmissionService as Hw;
use App\Support\HumanDate;
use Illuminate\Support\Facades\Cache;
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

    private const IMAGE_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic'];

    public Homework $homework;

    /** Текст ответа (в работе хранится HTML — как у редактора в старом кабинете). */
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
            $this->answer = $this->toText($submission->content);
            $this->kept = array_values(array_filter($submission->attachments ?? [], 'is_string'));
        }
    }

    protected function rules(): array
    {
        return [
            'answer' => ['nullable', 'string', 'max:20000'],
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

        if (trim($this->answer) === '' && empty($this->files) && empty($this->kept)) {
            $this->addError('answer', 'Напишите ответ или прикрепите файл.');

            return;
        }

        $paths = FileUploadHelper::processFiles($this->files, Hw::DIRECTORY);

        $service->submit($this->homework, auth()->user(), $this->toHtml($this->answer), array_merge($this->kept, $paths));

        $this->reset('files', 'picked', 'kept', 'answer');
        $this->justSent = true;
        $this->dispatch('toast', message: 'Работа отправлена учителю');
    }

    public function render()
    {
        $homework = $this->homework->loadMissing(['teacher:id,name', 'room:id,name']);
        $submission = $this->submission();
        $state = Hw::state($homework, $submission);
        $canSubmit = Hw::canSubmit($submission);
        $annotated = $submission?->annotated_files ?? [];

        return view('livewire.cabinet.student.task', [
            'state' => $state,
            'canSubmit' => $canSubmit,
            'facts' => $this->facts($homework, $state),
            'description' => $this->rich($homework->description),
            'teacherFiles' => $this->storedFiles($homework->attachments ?? [], [], 'Файл'),
            'submission' => $submission,
            'grade' => $submission?->grade !== null ? $homework->formatGrade($submission->grade) : null,
            'content' => $this->rich($submission?->content),
            'feedback' => $this->rich($submission?->feedback),
            'feedbackFiles' => $this->storedFiles($submission?->feedback_attachments ?? [], [], 'Файл'),
            'myFiles' => $this->storedFiles($submission?->attachments ?? [], $annotated),
            'keptFiles' => $this->storedFiles($this->kept, $annotated),
            'newFiles' => collect($this->files)->map(fn (TemporaryUploadedFile $f) => [
                'name' => $f->getClientOriginalName(),
                'meta' => $this->kind($f->getClientOriginalName()) . ' · ' . $this->size($f->getSize()),
            ])->all(),
            'sentAt' => $submission?->submitted_at ? HumanDate::at($submission->submitted_at) : null,
            'checkedAt' => $submission ? HumanDate::date($submission->updated_at) : null,
            'previous' => $state === Hw::STATE_TODO || $state === Hw::STATE_OVERDUE || $state === Hw::STATE_REVIEW
                ? $this->previousComment($homework) : null,
            'messengerUrl' => url('/student/messenger' . ($homework->room_id ? '?room=' . $homework->room_id : '')),
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

    /**
     * Файлы на s3 для плиток: имя по типу («Фото 1», «Файл 2»), тип и размер, ссылка на скачивание.
     * Исходных имён в заданиях нет — храним только пути.
     */
    private function storedFiles(array $paths, array $annotated, ?string $noun = null): array
    {
        return collect($paths)->filter(fn ($p) => is_string($p) && $p !== '')->values()
            ->map(function (string $path, int $i) use ($annotated, $noun) {
                $size = Cache::get("file_size_{$path}");
                $kind = $this->kind($path);
                $isImage = in_array(mb_strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::IMAGE_EXT, true);

                return [
                    'path' => $path,
                    'name' => ($noun ?? ($isImage ? 'Фото' : 'Файл')) . ' ' . ($i + 1),
                    'meta' => $kind . ($size ? ' · ' . $this->size((int) $size) : ''),
                    'annotated' => in_array($path, $annotated, true),
                    'url' => Hw::fileUrl($path),
                ];
            })->all();
    }

    private function kind(string $name): string
    {
        $ext = mb_strtolower(pathinfo($name, PATHINFO_EXTENSION));

        return match (true) {
            in_array($ext, ['doc', 'docx'], true) => 'Word',
            $ext === 'jpeg' => 'JPG',
            $ext === 'pdf' => 'PDF',
            $ext !== '' => mb_strtoupper($ext),
            default => 'Файл',
        };
    }

    private function size(int $bytes): string
    {
        return $bytes >= 1048576
            ? str_replace('.', ',', (string) round($bytes / 1048576, 1)) . ' МБ'
            : max(1, (int) round($bytes / 1024)) . ' КБ';
    }

    /**
     * Текст из редактора учителя — очищаем так же, как старый кабинет (Filament sanitizeHtml).
     * Оформление — класс `.rich` во вьюхе; ссылки открываются в новой вкладке.
     */
    private function rich(?string $html): ?HtmlString
    {
        if ($html === null || trim(strip_tags($html)) === '') {
            return null;
        }

        return new HtmlString(preg_replace('/<a(?=\s)/i', '<a target="_blank" rel="noopener"', Str::sanitizeHtml($html)));
    }

    /** Простой текст ответа → HTML для хранения (абзацы и переносы строк). */
    private function toHtml(string $text): ?string
    {
        $text = trim(str_replace("\r\n", "\n", $text));

        if ($text === '') {
            return null;
        }

        return collect(preg_split("/\n{2,}/", $text))
            ->map(fn (string $p) => '<p>' . nl2br(e(trim($p)), false) . '</p>')
            ->implode('');
    }

    /** HTML ответа → простой текст для поля при пересдаче. */
    private function toText(?string $html): string
    {
        if (! $html) {
            return '';
        }

        $text = preg_replace(['/<br\s*\/?>/i', '/<\/(p|div|li|h[1-6])>/i'], ["\n", "\n\n"], $html);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace("/\n{3,}/", "\n\n", $text));
    }
}
