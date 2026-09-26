<?php

namespace App\Services;

use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\User;
use App\Notifications\HomeworkSubmitted;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Задания: глазами ученика (какие видны, в каком состоянии, сдача работы)
 * и учителя (выдача задания, проверка, оценка, возврат на доработку).
 * Используется в кабинетах учителя и ученика (/cabinet/…/tasks).
 */
class HomeworkSubmissionService
{
    /** Что можно прикрепить к ответу. */
    public const ACCEPTED_MIMES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'image/jpeg',
        'image/png',
        'image/gif',
    ];

    /** Максимальный размер файла ответа, КБ (50 МБ). */
    public const MAX_FILE_KB = 51200;

    /** Каталог на s3: файлы ложатся в homework-submissions/{id ученика}/… */
    public const DIRECTORY = 'homework-submissions';

    /** Состояния задания для ученика. */
    public const STATE_TODO = 'todo';
    public const STATE_OVERDUE = 'overdue';
    public const STATE_REVISION = 'revision';
    public const STATE_REVIEW = 'review';
    public const STATE_GRADED = 'graded';

    /** Задания, которые видит ученик: опубликованные и назначенные ему. */
    public static function visibleTo(int $studentId): Builder
    {
        return static::scopeVisible(Homework::query(), $studentId);
    }

    public static function scopeVisible(Builder $query, int $studentId): Builder
    {
        return $query
            ->where('is_visible', true)
            ->whereHas('students', fn ($q) => $q->where('users.id', $studentId));
    }

    /**
     * Фильтр по статусу работы ученика.
     * pending — не сдано, submitted — на проверке, revision_requested — на доработке, graded — оценено;
     * actual — нужно сдать (не сдано или на доработке), done — сдано (на проверке или оценено).
     */
    public static function filterByStatus(Builder $query, int $studentId, ?string $status): Builder
    {
        $submitted = fn ($q) => $q->where('student_id', $studentId)->whereNotNull('submitted_at');
        $revision = fn ($q) => $q->where('student_id', $studentId)->where('status', HomeworkSubmission::STATUS_REVISION_REQUESTED);

        return match ($status) {
            'pending' => $query->whereDoesntHave('submissions', $submitted),
            'submitted' => $query->whereHas('submissions', fn ($q) => $q->where('student_id', $studentId)
                ->where('status', HomeworkSubmission::STATUS_SUBMITTED)),
            'revision_requested' => $query->whereHas('submissions', $revision),
            'graded' => $query->whereHas('submissions', fn ($q) => $q->where('student_id', $studentId)->whereNotNull('grade')),
            'actual' => $query->where(fn ($q) => $q->whereDoesntHave('submissions', $submitted)
                ->orWhereHas('submissions', $revision)),
            'done' => $query->whereHas('submissions', fn ($q) => $submitted($q)
                ->where('status', '!=', HomeworkSubmission::STATUS_REVISION_REQUESTED)),
            default => $query,
        };
    }

    /** Порядок списка: сначала ждущие проверки, затем на доработке, остальные — новые сверху. */
    public static function orderForStudent(Builder $query, int $studentId): Builder
    {
        return $query
            ->leftJoin('homework_submissions as hs', function ($join) use ($studentId) {
                $join->on('hs.homework_id', '=', 'homeworks.id')
                    ->where('hs.student_id', '=', $studentId);
            })
            ->orderByRaw("CASE
                WHEN hs.status = 'submitted' AND hs.grade IS NULL THEN 0
                WHEN hs.status = 'revision_requested' THEN 1
                ELSE 2
            END")
            ->orderByDesc('homeworks.created_at')
            ->select('homeworks.*');
    }

    /** Состояние задания для ученика по его работе. */
    public static function state(Homework $homework, ?HomeworkSubmission $submission): string
    {
        return match (true) {
            ! $submission?->submitted_at => $homework->is_overdue ? self::STATE_OVERDUE : self::STATE_TODO,
            $submission->status === HomeworkSubmission::STATUS_REVISION_REQUESTED => self::STATE_REVISION,
            $submission->grade !== null => self::STATE_GRADED,
            default => self::STATE_REVIEW,
        };
    }

    /** Сдать можно, пока работа не отправлена, и повторно — если учитель вернул её на доработку. */
    public static function canSubmit(?HomeworkSubmission $submission): bool
    {
        return ! $submission
            || ! $submission->submitted_at
            || $submission->status === HomeworkSubmission::STATUS_REVISION_REQUESTED;
    }

    /**
     * Отправить работу на проверку (первый раз или после доработки) и уведомить учителя.
     * История (HomeworkActivity) пишется в HomeworkSubmissionObserver по смене статуса.
     *
     * @param  array<int, string>  $attachments  пути файлов на s3 (уже загруженных)
     * @param  array<string, string>  $fileNames  исходные имена новых файлов: путь → имя (storeUploads)
     */
    public function submit(Homework $homework, User $student, ?string $content, array $attachments, array $fileNames = []): HomeworkSubmission
    {
        $existing = $homework->submissions()->where('student_id', $student->id)->first();

        if (! static::canSubmit($existing)) {
            throw new \DomainException('Работа уже на проверке или проверена.');
        }

        $attachments = array_values(array_filter($attachments, 'is_string'));

        $submission = HomeworkSubmission::updateOrCreate(
            [
                'homework_id' => $homework->id,
                'student_id' => $student->id,
            ],
            [
                'content' => $content,
                'attachments' => $attachments,
                'file_names' => static::namesFor(
                    array_merge($attachments, $existing?->feedback_attachments ?? [], array_values($existing?->annotations ?? [])),
                    array_merge($existing?->file_names ?? [], $fileNames),
                ),
                'submitted_at' => now(),
                'status' => HomeworkSubmission::STATUS_SUBMITTED,
            ]
        );

        // Файлы, которые ученик убрал из ответа при пересдаче, больше не нужны.
        // Кроме тех, на которые ссылается комментарий учителя (в старых записях фото с пометками — это сам оригинал).
        $keep = array_merge($existing?->feedback_attachments ?? [], array_values($existing?->annotations ?? []));
        foreach (array_diff($existing?->attachments ?? [], $attachments, $keep) as $removed) {
            if (is_string($removed) && $removed !== '') {
                try {
                    Storage::disk('s3')->delete($removed);
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        $homework->teacher?->notify(new HomeworkSubmitted($homework, $student, $submission));

        return $submission;
    }

    /* ───────────── Файлы: загрузка с исходными именами ───────────── */

    /**
     * Загрузить файлы на s3 (как FileUploadHelper: сжатие фото, каталог {каталог}/{id пользователя})
     * и запомнить их исходные имена.
     *
     * @return array<string, string> путь на s3 → исходное имя файла
     */
    public static function storeUploads(array $files, string $directory): array
    {
        $stored = [];

        foreach ($files as $file) {
            if (! $file instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                continue;
            }

            $name = static::cleanName($file->getClientOriginalName());
            $path = \App\Helpers\FileUploadHelper::processAndStoreFile($file, $directory);

            if ($path) {
                $stored[$path] = $name;
            }
        }

        return $stored;
    }

    /** Исходное имя файла для показа: без каталогов и управляющих символов, до 200 символов. */
    public static function cleanName(?string $name): string
    {
        $name = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', basename(str_replace('\\', '/', (string) $name))));

        return mb_substr($name, 0, 200);
    }

    /**
     * Имена только тех файлов, что ещё есть в списке (остальные забываем).
     *
     * @param  array<int, string>  $paths
     * @param  array<string, string>  $names
     * @return array<string, string>|null
     */
    public static function namesFor(array $paths, array $names): ?array
    {
        $kept = array_intersect_key($names, array_flip(array_filter($paths, 'is_string')));

        return $kept ?: null;
    }

    /* ───────────── Учитель: выдача заданий и проверка работ ───────────── */

    /** Что можно прикрепить к заданию. */
    public const TASK_MIMES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'image/jpeg',
        'image/png',
        'image/gif',
    ];

    /** Максимальный размер файла задания, КБ (100 МБ). */
    public const TASK_MAX_FILE_KB = 102400;

    /** Каталог файлов задания на s3: homework-attachments/{id учителя}/… */
    public const TASK_DIRECTORY = 'homework-attachments';

    /** Каталог файлов к комментарию учителя: feedback-attachments/{id учителя}/… */
    public const FEEDBACK_DIRECTORY = 'feedback-attachments';

    /** Работы, которые учитель может открыть: сданные ученикам его заданий. */
    public static function submissionsOf(int $teacherId): Builder
    {
        return HomeworkSubmission::query()
            ->whereNotNull('submitted_at')
            ->whereHas('homework', fn ($q) => $q->where('teacher_id', $teacherId));
    }

    /** Работы, ждущие проверки: сданы и ещё не оценены (не на доработке). Сначала самые давние. */
    public static function toReview(int $teacherId): Builder
    {
        return static::submissionsOf($teacherId)
            ->where('status', HomeworkSubmission::STATUS_SUBMITTED)
            ->whereNull('grade')
            ->orderBy('submitted_at')
            ->orderBy('id');
    }

    /**
     * Задания с числами по назначенным ученикам (работы учеников, которых убрали из задания, не считаем):
     * students_count, submitted_count (сдано), graded_count (оценено), review_count (ждёт проверки), revision_count (на доработке).
     */
    public static function withProgress(Builder $query): Builder
    {
        $assigned = fn ($q) => $q->whereExists(fn ($e) => $e->selectRaw('1')->from('homework_student')
            ->whereColumn('homework_student.homework_id', 'homework_submissions.homework_id')
            ->whereColumn('homework_student.student_id', 'homework_submissions.student_id'));

        return $query->withCount([
            'students',
            'submissions as submitted_count' => fn ($q) => $assigned($q->whereNotNull('submitted_at')),
            'submissions as graded_count' => fn ($q) => $assigned($q->whereNotNull('grade')),
            'submissions as review_count' => fn ($q) => $assigned($q->whereNotNull('submitted_at')
                ->where('status', HomeworkSubmission::STATUS_SUBMITTED)->whereNull('grade')),
            'submissions as revision_count' => fn ($q) => $assigned($q->where('status', HomeworkSubmission::STATUS_REVISION_REQUESTED)),
        ]);
    }

    /** Задания, которые ещё не закрыты: черновики, без учеников и те, где не все назначенные ученики оценены. */
    public static function notDone(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q->where('is_visible', false)
            ->orWhereDoesntHave('students')
            ->orWhereExists(fn ($e) => $e->selectRaw('1')->from('homework_student as hs')
                ->whereColumn('hs.homework_id', 'homeworks.id')
                ->whereNotExists(fn ($g) => $g->selectRaw('1')->from('homework_submissions as g')
                    ->whereColumn('g.homework_id', 'hs.homework_id')
                    ->whereColumn('g.student_id', 'hs.student_id')
                    ->whereNotNull('g.grade'))));
    }

    /** Опубликованные задания, которые сдали ещё не все назначенные ученики. */
    public static function awaitingSubmissions(Builder $query): Builder
    {
        return $query->where('is_visible', true)
            ->whereExists(fn ($e) => $e->selectRaw('1')->from('homework_student as hs')
                ->whereColumn('hs.homework_id', 'homeworks.id')
                ->whereNotExists(fn ($g) => $g->selectRaw('1')->from('homework_submissions as g')
                    ->whereColumn('g.homework_id', 'hs.homework_id')
                    ->whereColumn('g.student_id', 'hs.student_id')
                    ->whereNotNull('g.submitted_at')));
    }

    /** Балл по умолчанию для нового задания: как в последнем задании учителя, иначе 10. */
    public static function defaultMaxScore(int $teacherId): int
    {
        return (int) (Homework::where('teacher_id', $teacherId)
            ->whereNotNull('max_score')
            ->latest()
            ->value('max_score') ?? 10);
    }

    /**
     * Создать задание (teacher_id — автор) и уведомить учеников.
     *
     * @param  array<int>  $studentIds
     */
    public function createTask(User $teacher, array $data, array $studentIds): Homework
    {
        $homework = Homework::create(array_merge(
            ['type' => Homework::TYPE_HOMEWORK],
            $data,
            ['teacher_id' => $teacher->id],
        ));
        $homework->students()->sync($studentIds);

        $this->notifyAssigned($homework, $homework->students()->get());

        return $homework;
    }

    /**
     * Изменить задание. Удалённые из задания файлы стираются с s3.
     * Уведомляем тех, кто увидел задание впервые: при публикации черновика — всех, иначе — добавленных учеников.
     *
     * @param  array<int>  $studentIds
     */
    public function updateTask(Homework $homework, array $data, array $studentIds): Homework
    {
        $wasVisible = $homework->is_visible;
        $oldFiles = $homework->attachments ?? [];
        $oldStudents = $homework->students()->pluck('users.id')->all();

        unset($data['teacher_id']);
        $homework->update($data);
        $homework->students()->sync($studentIds);

        foreach (array_diff($oldFiles, $homework->attachments ?? []) as $removed) {
            if (is_string($removed) && $removed !== '') {
                try {
                    Storage::disk('s3')->delete($removed);
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        $newIds = $wasVisible ? array_diff($studentIds, $oldStudents) : $studentIds;
        if ($newIds) {
            $this->notifyAssigned($homework, User::whereIn('id', $newIds)->get());
        }

        return $homework;
    }

    /** Уведомление «Новое задание» (NewHomework). Черновик ученики не видят — о нём не сообщаем. */
    public function notifyAssigned(Homework $homework, iterable $students): void
    {
        if (! $homework->is_visible) {
            return;
        }

        foreach ($students as $student) {
            $student->notify(new \App\Notifications\NewHomework($homework));
        }
    }

    /**
     * Оценить работу (или изменить оценку) и уведомить ученика — как действие «Оценить» в старом кабинете.
     * maxScore — новый максимальный балл для всего задания (null — не менять);
     * attachments — файлы к комментарию (null — не менять); fileNames — исходные имена новых файлов.
     */
    public function grade(HomeworkSubmission $submission, int $grade, ?string $feedback, ?int $maxScore = null, ?array $attachments = null, array $fileNames = []): HomeworkSubmission
    {
        if ($submission->status === HomeworkSubmission::STATUS_REVISION_REQUESTED) {
            throw new \DomainException('Работа на доработке — оценить её можно после пересдачи.');
        }

        $homework = $submission->homework;

        if ($maxScore !== null) {
            $homework->update(['max_score' => $maxScore]);
        }

        $submission->update(array_merge([
            'grade' => $grade,
            'feedback' => $feedback,
            'status' => HomeworkSubmission::STATUS_GRADED,
        ], $attachments !== null ? [
            'feedback_attachments' => array_values($attachments),
            'file_names' => static::namesFor(
                array_merge($submission->attachments ?? [], array_values($submission->annotations ?? []), $attachments),
                array_merge($submission->file_names ?? [], $fileNames),
            ),
        ] : []));

        $submission->student->notify(new \App\Notifications\HomeworkGraded($homework, $grade));

        return $submission;
    }

    /**
     * Вернуть работу на доработку (только сданную и не оценённую) с обязательным комментарием и уведомить ученика.
     * attachments — файлы к комментарию, заменяют прежние (как в старом кабинете); fileNames — исходные имена новых файлов.
     */
    public function requestRevision(HomeworkSubmission $submission, string $feedback, ?array $attachments = null, array $fileNames = []): HomeworkSubmission
    {
        if ($submission->status !== HomeworkSubmission::STATUS_SUBMITTED) {
            throw new \DomainException('Вернуть на доработку можно только работу на проверке.');
        }

        if (trim(strip_tags($feedback)) === '') {
            throw new \InvalidArgumentException('Комментарий обязателен.');
        }

        $submission->update([
            'status' => HomeworkSubmission::STATUS_REVISION_REQUESTED,
            'feedback' => $feedback,
            'feedback_attachments' => $attachments ? array_values($attachments) : null,
            'file_names' => static::namesFor(
                array_merge($submission->attachments ?? [], array_values($submission->annotations ?? []), $attachments ?? []),
                array_merge($submission->file_names ?? [], $fileNames),
            ),
        ]);

        $submission->student->notify(new \App\Notifications\HomeworkRevisionRequested($submission->homework, $feedback));

        return $submission;
    }

    /**
     * Сохранить пометки учителя на фото из работы (ImageAnnotator — старый и новый кабинет).
     * Оригинал фото ученика не трогаем: пометки — отдельный файл в feedback-attachments/{id учителя}/,
     * при повторных пометках перезаписывается тот же файл. Файл с пометками добавляется к файлам комментария,
     * поэтому ссылка на него живёт, даже если ученик уберёт оригинал при пересдаче.
     *
     * @return string путь файла с пометками
     */
    public function saveAnnotation(HomeworkSubmission $submission, string $original, string $image): string
    {
        $submission->refresh();
        $map = $submission->annotations ?? [];
        $names = $submission->file_names ?? [];

        $target = $map[$original] ?? self::FEEDBACK_DIRECTORY . '/' . ($submission->homework?->teacher_id ?? auth()->id() ?? 'system')
            . '/' . uniqid() . '_' . time() . '_marks.png';

        Storage::disk('s3')->put($target, $image, 'public');
        \Illuminate\Support\Facades\Cache::put("file_size_{$target}", strlen($image), 86400 * 30);

        $map[$original] = $target;

        // Имя файла с пометками — по имени фото ученика
        $photoIndex = array_search($original, array_values(array_filter($submission->attachments ?? [], fn ($p) => is_string($p) && static::isImage($p))), true);
        $base = isset($names[$original])
            ? pathinfo($names[$original], PATHINFO_FILENAME)
            : 'Фото' . ($photoIndex === false ? '' : ' ' . ($photoIndex + 1));
        $names[$target] = $base . ' с пометками.png';

        $feedback = $submission->feedback_attachments ?? [];
        if (! in_array($target, $feedback, true)) {
            $feedback[] = $target;
        }

        $marked = $submission->annotated_files ?? [];
        if (! in_array($original, $marked, true)) {
            $marked[] = $original;
        }

        $submission->update([
            'annotations' => $map,
            'annotated_files' => $marked,
            'feedback_attachments' => $feedback,
            'file_names' => $names,
        ]);

        \App\Models\HomeworkActivity::log(
            $submission->id,
            \App\Models\HomeworkActivity::TYPE_ANNOTATED,
            auth()->id(),
            ['filename' => $names[$original] ?? basename($original)]
        );

        return $target;
    }

    /**
     * Старый кабинет после события imageAnnotated: фото с пометками — к файлам комментария.
     * saveAnnotation уже добавил файл; здесь только страховка для записей без annotations.
     * Работу перечитываем: у экрана может быть устаревшая копия.
     */
    public function addAnnotation(HomeworkSubmission $submission, string $path): void
    {
        $submission->refresh();
        $path = ($submission->annotations ?? [])[$path] ?? $path;
        $files = $submission->feedback_attachments ?? [];

        if (! in_array($path, $files, true)) {
            $files[] = $path;
            $submission->update(['feedback_attachments' => $files]);
        }
    }

    /** Может ли пользователь проверять работу (работа по его заданию и сдана). */
    public static function canReview(?User $user, HomeworkSubmission $submission): bool
    {
        return $user !== null
            && $submission->submitted_at !== null
            && (int) $submission->homework?->teacher_id === (int) $user->id;
    }

    /** Расширения фото (открываются для пометок, показываются миниатюрой). */
    public const IMAGE_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    public static function isImage(string $path): bool
    {
        return in_array(mb_strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::IMAGE_EXT, true);
    }

    /**
     * Файлы на s3 для плиток кабинета: исходное имя (для старых записей — «Фото 1», «Файл 2»), тип и размер, ссылка.
     * marked — фото с пометками (путь → файл с пометками): ссылка ведёт на файл с пометками.
     *
     * @param  array<string, string>  $names  путь → исходное имя
     * @param  array<string, string>  $marked  путь → путь файла с пометками
     * @return array<int, array{path: string, name: string, meta: string, image: bool, annotated: bool, url: string}>
     */
    public static function fileViews(array $paths, array $names = [], array $marked = []): array
    {
        return collect($paths)->filter(fn ($p) => is_string($p) && $p !== '')->values()
            ->map(function (string $path, int $i) use ($names, $marked) {
                $size = \Illuminate\Support\Facades\Cache::get("file_size_{$path}");
                $image = static::isImage($path);
                $name = isset($names[$path]) && $names[$path] !== '' ? $names[$path] : null;

                return [
                    'path' => $path,
                    'name' => $name ?? (($image ? 'Фото' : 'Файл') . ' ' . ($i + 1)),
                    'meta' => static::kind($path) . ($size ? ' · ' . static::size((int) $size) : ''),
                    'image' => $image,
                    'annotated' => isset($marked[$path]),
                    'url' => static::fileUrl($marked[$path] ?? $path),
                ];
            })->all();
    }

    /**
     * Файлы ответа ученика. withMarks — показывать фото с пометками (учителю — всегда, ученику — после проверки).
     *
     * @param  array<int, string>|null  $paths  какие файлы показать (по умолчанию — все файлы работы)
     */
    public static function answerFiles(HomeworkSubmission $submission, bool $withMarks, ?array $paths = null): array
    {
        return static::fileViews($paths ?? $submission->attachments ?? [], $submission->file_names ?? [], $withMarks ? $submission->markedFiles() : []);
    }

    /**
     * Файлы комментария учителя. Фото с пометками, которые уже показаны среди файлов ответа (shown), не повторяем;
     * если ученик убрал оригинал, файл с пометками остаётся здесь.
     *
     * @param  array<int, string>  $shown  пути файлов ответа на том же экране (с пометками)
     */
    public static function feedbackFiles(HomeworkSubmission $submission, array $shown = []): array
    {
        $marked = $submission->markedFiles();
        $hidden = array_values(array_intersect_key($marked, array_flip($shown)));
        $paths = array_values(array_filter($submission->feedback_attachments ?? [], fn ($p) => ! in_array($p, $hidden, true)));

        return static::fileViews($paths, $submission->file_names ?? []);
    }

    /** Тип файла по имени: PDF, Word, JPG… */
    public static function kind(string $name): string
    {
        $ext = mb_strtolower(pathinfo($name, PATHINFO_EXTENSION));

        return match (true) {
            in_array($ext, ['doc', 'docx'], true) => 'Word',
            in_array($ext, ['xls', 'xlsx'], true) => 'Excel',
            in_array($ext, ['ppt', 'pptx'], true) => 'PowerPoint',
            $ext === 'jpeg' => 'JPG',
            $ext !== '' => mb_strtoupper($ext),
            default => 'Файл',
        };
    }

    /** Размер файла: «1,2 МБ», «340 КБ». */
    public static function size(int $bytes): string
    {
        return $bytes >= 1048576
            ? str_replace('.', ',', (string) round($bytes / 1048576, 1)) . ' МБ'
            : max(1, (int) round($bytes / 1024)) . ' КБ';
    }

    /** Ссылка на файл задания или работы (временная для s3). */
    public static function fileUrl(string $path): string
    {
        try {
            // Файлы заданий, работ и комментариев всегда лежат на s3 (FileUploadHelper)
            if (config('filesystems.default') === 's3' || Str::startsWith($path, ['homework-submissions/', 'homework-feedback/', 'homework-attachments/', 'feedback-attachments/'])) {
                return Storage::disk('s3')->temporaryUrl($path, now()->addMinutes(30));
            }

            return Storage::url($path);
        } catch (\Throwable $e) {
            return Storage::url($path);
        }
    }
}
