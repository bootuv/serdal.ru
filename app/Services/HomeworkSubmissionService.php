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
 * Единая логика для старых кабинетов (Filament, /student/homework, /tutor/homework) и новых (/cabinet/…/tasks).
 */
class HomeworkSubmissionService
{
    /** Что можно прикрепить к ответу (как в форме сдачи Filament). */
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
     */
    public function submit(Homework $homework, User $student, ?string $content, array $attachments): HomeworkSubmission
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
                'submitted_at' => now(),
                'status' => HomeworkSubmission::STATUS_SUBMITTED,
            ]
        );

        // Файлы, которые ученик убрал из ответа при пересдаче, больше не нужны
        foreach (array_diff($existing?->attachments ?? [], $attachments) as $removed) {
            if (is_string($removed) && $removed !== '') {
                try {
                    Storage::disk('s3')->delete($removed);
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        $homework->teacher?->notify(new HomeworkSubmitted($homework, $student));

        return $submission;
    }

    /* ───────────── Учитель: выдача заданий и проверка работ ───────────── */

    /** Что можно прикрепить к заданию (как в форме задания Filament). */
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

    /** Работы, которые учитель может открыть: сданные ученикам его заданий (как getEloquentQuery в Filament). */
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
     * Изменить задание. Удалённые из задания файлы стираются с s3 (как при удалении файла в форме Filament).
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
     * attachments — файлы к комментарию (null — не менять).
     */
    public function grade(HomeworkSubmission $submission, int $grade, ?string $feedback, ?int $maxScore = null, ?array $attachments = null): HomeworkSubmission
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
        ], $attachments !== null ? ['feedback_attachments' => array_values($attachments)] : []));

        $submission->student->notify(new \App\Notifications\HomeworkGraded($homework, $grade));

        return $submission;
    }

    /**
     * Вернуть работу на доработку (только сданную и не оценённую) с обязательным комментарием и уведомить ученика.
     * attachments — файлы к комментарию, заменяют прежние (как в старом кабинете).
     */
    public function requestRevision(HomeworkSubmission $submission, string $feedback, ?array $attachments = null): HomeworkSubmission
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
        ]);

        $submission->student->notify(new \App\Notifications\HomeworkRevisionRequested($submission->homework, $feedback));

        return $submission;
    }

    /**
     * Учитель сохранил пометки на фото (событие imageAnnotated от ImageAnnotator):
     * фото с пометками добавляется к файлам комментария.
     */
    public function addAnnotation(HomeworkSubmission $submission, string $path): void
    {
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
     * Файлы на s3 для плиток кабинета: «Фото 1», «Файл 2» (исходных имён не храним), тип и размер, ссылка.
     *
     * @return array<int, array{path: string, name: string, meta: string, image: bool, annotated: bool, url: string}>
     */
    public static function fileViews(array $paths, array $annotated = []): array
    {
        return collect($paths)->filter(fn ($p) => is_string($p) && $p !== '')->values()
            ->map(function (string $path, int $i) use ($annotated) {
                $size = \Illuminate\Support\Facades\Cache::get("file_size_{$path}");
                $image = static::isImage($path);

                return [
                    'path' => $path,
                    'name' => ($image ? 'Фото' : 'Файл') . ' ' . ($i + 1),
                    'meta' => static::kind($path) . ($size ? ' · ' . static::size((int) $size) : ''),
                    'image' => $image,
                    'annotated' => in_array($path, $annotated, true),
                    'url' => static::fileUrl($path),
                ];
            })->all();
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
            if (config('filesystems.default') === 's3' || Str::startsWith($path, ['homework-submissions/', 'homework-feedback/'])) {
                return Storage::disk('s3')->temporaryUrl($path, now()->addMinutes(30));
            }

            return Storage::url($path);
        } catch (\Throwable $e) {
            return Storage::url($path);
        }
    }
}
