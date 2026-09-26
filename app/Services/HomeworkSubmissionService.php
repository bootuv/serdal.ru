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
 * Задания глазами ученика: какие видны, в каком они состоянии, сдача работы.
 * Единая логика для старого кабинета (Filament, /student/homework) и нового (/cabinet/student/tasks).
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
