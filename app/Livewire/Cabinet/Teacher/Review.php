<?php

namespace App\Livewire\Cabinet\Teacher;

use App\Livewire\Cabinet\Teacher\Concerns\TeacherScreen;
use App\Models\HomeworkActivity;
use App\Models\HomeworkSubmission;
use App\Services\HomeworkSubmissionService as Hw;
use App\Support\HumanDate;
use App\Support\RichText;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Проверка работы ученика: ответ, пометки на фото, оценка баллами или возврат на доработку.
 * Макеты: «Учитель · Проверка» и «Учитель · Пометки» (docs/design/BRAND.md).
 * Оценка, возврат и уведомления — HomeworkSubmissionService (как в старом кабинете).
 */
#[Layout('components.layouts.cabinet', ['title' => 'Проверка работы', 'active' => 'tasks'])]
class Review extends Component
{
    use TeacherScreen;
    use WithFileUploads;

    public HomeworkSubmission $submission;

    /** Баллы (от 1 до максимального балла задания). */
    public $grade = null;

    /** Комментарий простым текстом (в базе — HTML). */
    public string $comment = '';

    /** Комментарий, как он был при открытии «Изменить оценку»: не меняли — сохраняем исходный HTML. */
    #[Locked]
    public ?string $commentOriginal = null;

    /** «Изменить оценку» у проверенной работы. */
    public bool $editing = false;

    public array $picked = [];

    public array $files = [];

    /** Фото, открытое для пометок. */
    #[Locked]
    public ?string $annotating = null;

    /** Окно пометок показывает список фото (когда их несколько): из него открывают холст и возвращаются к нему. */
    #[Locked]
    public bool $photoList = false;

    public function mount(HomeworkSubmission $submission): void
    {
        $this->authorizeTeacher();
        abort_unless(Hw::canReview(auth()->user(), $submission), 404);

        $this->submission = $submission;
    }

    protected function messages(): array
    {
        $max = $this->submission->homework->effective_max_score;

        return [
            'grade.required' => "Выберите оценку от 1 до {$max}",
            'grade.integer' => "Оценка — целое число от 1 до {$max}",
            'grade.min' => "Оценка — от 1 до {$max}",
            'grade.max' => "Оценка — от 1 до {$max}",
            'comment.required' => 'Напишите, что исправить — без комментария вернуть работу нельзя.',
            'comment.max' => 'Комментарий слишком длинный.',
            'picked.*.mimetypes' => 'Можно прикрепить PDF, Word, Excel, PowerPoint или фото.',
            'picked.*.max' => 'Файл больше 50 МБ — уменьшите его или разделите на части.',
            'files.*.mimetypes' => 'Можно прикрепить PDF, Word, Excel, PowerPoint или фото.',
            'files.*.max' => 'Файл больше 50 МБ — уменьшите его или разделите на части.',
        ];
    }

    /** Файлы к комментарию — как в старом кабинете (до 50 МБ). */
    private function fileRules(): array
    {
        return ['file', 'mimetypes:' . implode(',', Hw::TASK_MIMES), 'max:' . Hw::MAX_FILE_KB];
    }

    public function pickGrade(int $value): void
    {
        $this->grade = $value;
        $this->resetErrorBag('grade');
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

    /** «Принять и оценить» (или сохранить новую оценку). */
    public function accept(Hw $service): void
    {
        $s = $this->submission->fresh(['homework', 'student']);

        if ($s->status === HomeworkSubmission::STATUS_REVISION_REQUESTED) {
            $this->dispatch('toast', message: 'Работа на доработке — оценить её можно после пересдачи.');

            return;
        }

        $this->validate([
            'grade' => ['required', 'integer', 'min:1', 'max:' . $s->homework->effective_max_score],
            'comment' => ['nullable', 'string', 'max:20000'],
            'files.*' => $this->fileRules(),
        ]);

        [$files, $names] = $this->feedbackFiles($s);
        $service->grade($s, (int) $this->grade, $this->feedbackHtml($s), null, $files, $names);

        $this->submission = $s->fresh();
        $this->reset('editing', 'files', 'picked', 'commentOriginal');
        $this->dispatch('toast', message: 'Работа принята');
    }

    /** «Вернуть на доработку»: комментарий обязателен (как в старом кабинете). */
    public function giveBack(Hw $service): void
    {
        $s = $this->submission->fresh(['homework', 'student']);

        if ($s->status !== HomeworkSubmission::STATUS_SUBMITTED) {
            $this->dispatch('toast', message: 'Вернуть на доработку можно только работу на проверке.');

            return;
        }

        $this->resetErrorBag('grade');
        $this->validate([
            'comment' => ['required', 'string', 'max:20000', function ($attr, $value, $fail) {
                if (trim($value) === '') {
                    $fail('Напишите, что исправить — без комментария вернуть работу нельзя.');
                }
            }],
            'files.*' => $this->fileRules(),
        ]);

        [$files, $names] = $this->feedbackFiles($s);
        $service->requestRevision($s, $this->feedbackHtml($s), $files ?? ($s->feedback_attachments ?: null), $names);

        $this->submission = $s->fresh();
        $this->reset('files', 'picked', 'grade', 'commentOriginal');
        $this->dispatch('toast', message: 'Работа возвращена на доработку');
    }

    /** «Изменить оценку»: открыть форму с текущей оценкой и комментарием. */
    public function edit(): void
    {
        $this->grade = $this->submission->grade;
        $this->comment = RichText::toPlain($this->submission->feedback);
        $this->commentOriginal = $this->comment;
        $this->editing = true;
    }

    public function cancelEdit(): void
    {
        $this->reset('editing', 'grade', 'comment', 'commentOriginal', 'files', 'picked');
        $this->resetErrorBag();
    }

    /** Открыть фото для пометок: номер среди фото этой работы. */
    public function annotate(int $index): void
    {
        $this->annotating = $this->photoPaths()[$index] ?? null;
        $this->photoList = false;
    }

    /** Из холста — к списку фото (кнопка «Все фото»; только когда фото несколько). */
    public function showPhotos(): void
    {
        $this->annotating = null;
        $this->photoList = count($this->photoPaths()) > 1;
    }

    public function closeAnnotator(): void
    {
        $this->annotating = null;
        $this->photoList = false;
    }

    private function photoPaths(): array
    {
        return array_values(array_filter($this->submission->attachments ?? [], fn ($p) => is_string($p) && Hw::isImage($p)));
    }

    /** ImageAnnotator сохранил пометки (отдельным файлом, он уже среди файлов комментария — Hw::saveAnnotation). */
    #[On('imageAnnotated')]
    public function annotated(string $path): void
    {
        $this->submission = $this->submission->fresh();
        // Фото несколько — после сохранения возвращаемся к списку: сразу видно, где пометки уже есть
        $this->showPhotos();
        $this->dispatch('toast', message: 'Пометки сохранены');
    }

    public function render()
    {
        $s = $this->submission->loadMissing(['homework.room:id,name', 'student:id,name,first_name,avatar']);
        $h = $s->homework;
        $student = $s->student;
        $firstName = $student?->first_name ?: ($student?->name ?? 'Ученик');

        $mode = match (true) {
            $this->editing => 'form',
            $s->status === HomeworkSubmission::STATUS_REVISION_REQUESTED => 'returned',
            $s->grade !== null => 'graded',
            default => 'form',
        };

        // Учитель видит пометки сразу, ученик — после проверки
        $answer = Hw::answerFiles($s, true);
        // label — имя в тексте: «фото 1» (без исходного имени) или исходное имя файла
        $photos = array_values(array_map(
            fn ($f) => $f + ['label' => preg_match('/^Фото \d+$/u', $f['name']) ? mb_strtolower($f['name']) : $f['name']],
            array_filter($answer, fn ($f) => $f['image']),
        ));
        $current = $this->annotating ? collect($photos)->firstWhere('path', $this->annotating) : null;

        [$position, $total, $nextUrl] = $this->queue($s);
        $max = $h->effective_max_score;

        return view('livewire.cabinet.teacher.review', [
            'homework' => $h,
            'student' => $student,
            'firstName' => $firstName,
            'facts' => $this->facts($s, $mode),
            'content' => RichText::html($s->content),
            'feedback' => $mode === 'form' ? null : RichText::html($s->feedback),
            'photos' => $photos,
            'otherFiles' => array_values(array_filter($answer, fn ($f) => ! $f['image'])),
            'mode' => $mode,
            'canReturn' => $s->status === HomeworkSubmission::STATUS_SUBMITTED && ! $this->editing,
            'max' => $max,
            'scale' => $max <= 10 ? range(1, $max) : null,
            'gradeLabel' => $h->formatGrade($s->grade),
            'newFiles' => collect($this->files)->map(fn (TemporaryUploadedFile $f) => [
                'name' => $f->getClientOriginalName(),
                'meta' => Hw::kind($f->getClientOriginalName()) . ' · ' . Hw::size($f->getSize()),
            ])->all(),
            'position' => $position,
            'total' => $total,
            'nextUrl' => $nextUrl,
            'description' => RichText::html($h->description),
            'taskFiles' => Hw::fileViews($h->attachments ?? [], $h->file_names ?? []),
            'marksNote' => $s->marksVisibleToStudent()
                ? $firstName . ' увидит пометки сразу после сохранения'
                : $firstName . ' увидит пометки, когда вы проверите работу',
            'issued' => 'Выдано ' . HumanDate::date($h->created_at)
                . ($h->deadline ? ' · срок до ' . HumanDate::date($h->deadline) . ', ' . $h->deadline->format('H:i') : ''),
            'current' => $current,
            'backUrl' => route('cabinet.teacher.tasks'),
        ])->title($h->title);
    }

    /** Место работы в очереди на проверку и ссылка на следующую работу. */
    private function queue(HomeworkSubmission $s): array
    {
        $ids = Hw::toReview(auth()->id())->pluck('id')->all();
        $index = array_search($s->id, $ids, true);

        $next = $index === false
            ? ($ids[0] ?? null)
            : ($ids[$index + 1] ?? ($ids[0] !== $s->id ? $ids[0] : null));

        return [
            $index === false ? null : $index + 1,
            count($ids),
            $next ? route('cabinet.teacher.review', $next) : null,
        ];
    }

    /** Строка фактов: ученик · занятие · когда сдано · сколько ждёт (жирным). */
    private function facts(HomeworkSubmission $s, string $mode): HtmlString
    {
        $h = $s->homework;
        $at = $s->submitted_at;
        $resubmitted = $s->activities()->where('type', HomeworkActivity::TYPE_RESUBMITTED)->exists();

        $sent = match (true) {
            $resubmitted => 'исправлено ' . HumanDate::day($at),
            $h->deadline && $at->gt($h->deadline) => 'сдано позже срока, ' . HumanDate::day($at),
            (bool) $h->deadline => 'сдано вовремя, ' . HumanDate::day($at),
            default => 'сдано ' . HumanDate::day($at),
        };

        $parts = array_map('e', array_filter([$s->student?->name, $h->room?->name, $sent]));

        $days = (int) $at->copy()->startOfDay()->diffInDays(today(), true);
        if ($mode === 'form' && $s->grade === null && $days >= 1) {
            $parts[] = '<span class="font-semibold text-ink">ждёт ' . e(plural_ru($days, 'день', 'дня', 'дней')) . '</span>';
        }

        return new HtmlString(implode(' · ', $parts));
    }

    /** Комментарий в HTML: не меняли при «Изменить оценку» — оставляем как был (с оформлением). */
    private function feedbackHtml(HomeworkSubmission $s): ?string
    {
        if ($this->commentOriginal !== null && $this->comment === $this->commentOriginal) {
            return $s->feedback;
        }

        return RichText::fromPlain($this->comment);
    }

    /**
     * Новые файлы к комментарию добавляются к прежним (фото с пометками и т.п.).
     *
     * @return array{0: ?array, 1: array<string, string>} [все файлы комментария или null — файлов не добавляли, исходные имена новых]
     */
    private function feedbackFiles(HomeworkSubmission $s): array
    {
        if (empty($this->files)) {
            return [null, []];
        }

        $names = Hw::storeUploads($this->files, Hw::FEEDBACK_DIRECTORY);

        return [array_merge($s->feedback_attachments ?? [], array_keys($names)), $names];
    }
}
