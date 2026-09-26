<?php

namespace App\Models;

use App\Observers\HomeworkSubmissionObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([HomeworkSubmissionObserver::class])]
class HomeworkSubmission extends Model
{
    use HasFactory;

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_REVISION_REQUESTED = 'revision_requested';
    public const STATUS_GRADED = 'graded';

    protected $fillable = [
        'homework_id',
        'student_id',
        'status',
        'content',
        'attachments',
        'file_names',
        'annotated_files',
        'annotations',
        'annotated_images',
        'feedback',
        'feedback_attachments',
        'grade',
        'submitted_at',
    ];

    protected $casts = [
        'attachments' => 'array',
        'file_names' => 'array',
        'annotated_files' => 'array',
        'annotations' => 'array',
        'annotated_images' => 'array',
        'feedback_attachments' => 'array',
        'submitted_at' => 'datetime',
    ];

    /**
     * Задание
     */
    public function homework(): BelongsTo
    {
        return $this->belongsTo(Homework::class);
    }

    /**
     * Ученик
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * Сдана ли работа
     */
    public function getIsSubmittedAttribute(): bool
    {
        return $this->submitted_at !== null;
    }

    /**
     * Оценена ли работа
     */
    public function getIsGradedAttribute(): bool
    {
        return $this->grade !== null;
    }

    /**
     * Получить текстовый статус
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_GRADED => 'Оценено',
            self::STATUS_REVISION_REQUESTED => 'На доработке',
            self::STATUS_SUBMITTED => 'На проверке',
            default => 'Не сдано',
        };
    }

    /**
     * Получить цвет статуса
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_GRADED => 'success',
            self::STATUS_REVISION_REQUESTED => 'danger',
            self::STATUS_SUBMITTED => 'warning',
            default => 'gray',
        };
    }

    /**
     * Фото с пометками учителя: путь фото ученика → путь файла, который показываем с пометками.
     * Новые пометки лежат отдельным файлом (annotations), оригинал цел;
     * в старых записях пометки нарисованы поверх оригинала (annotated_files) — там путь указывает сам на себя.
     *
     * @return array<string, string>
     */
    public function markedFiles(): array
    {
        $map = [];

        foreach ($this->annotated_files ?? [] as $path) {
            if (is_string($path) && $path !== '') {
                $map[$path] = $path;
            }
        }

        foreach ($this->annotations ?? [] as $original => $marked) {
            if (is_string($original) && is_string($marked) && $marked !== '') {
                $map[$original] = $marked;
            }
        }

        return $map;
    }

    /** Ученик видит пометки только после проверки: работа оценена или возвращена на доработку. */
    public function marksVisibleToStudent(): bool
    {
        return in_array($this->status, [self::STATUS_GRADED, self::STATUS_REVISION_REQUESTED], true);
    }

    /**
     * История событий
     */
    public function activities(): HasMany
    {
        return $this->hasMany(HomeworkActivity::class, 'submission_id')->orderBy('created_at', 'desc');
    }

    // File cleanup on delete is handled by HomeworkSubmissionObserver::deleting().
}

