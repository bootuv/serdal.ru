<?php

namespace App\Models;

use App\Observers\HomeworkSubmissionObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([HomeworkSubmissionObserver::class])]
class HomeworkSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'homework_id',
        'student_id',
        'content',
        'attachments',
        'annotated_files',
        'annotated_images',
        'status',
        'feedback',
        'feedback_attachments',
        'grade',
        'submitted_at',
    ];

    protected $casts = [
        'attachments' => 'array',
        'annotated_files' => 'array',
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
        if ($this->is_graded) {
            return 'Оценено';
        }
        if ($this->is_submitted) {
            return 'На проверке';
        }
        return 'Не сдано';
    }
}
